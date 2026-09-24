<?php

declare(strict_types=1);

namespace DifferentTechnology\AzureAdBe\Tests\Unit\Service;

use DifferentTechnology\AzureAdBe\Service\AzureAdBeService;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\RequestInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class AzureAdBeServiceTest extends UnitTestCase
{
    private const EXEC_TIME = 1700000000;

    private const AUTH_INFO = [
        'db_user' => [
            'table' => 'be_users',
            'enable_clause' => '',
        ],
    ];

    private const ENV_KEYS = [
        'TYPO3_AZURE_AD_BE_CLIENT_ID',
        'TYPO3_AZURE_AD_BE_CLIENT_SECRET',
        'TYPO3_AZURE_AD_BE_URL_AUTHORIZE',
        'TYPO3_AZURE_AD_BE_URL_ACCESS_TOKEN',
    ];

    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['EXEC_TIME'] = self::EXEC_TIME;
        // Mirrors the default set in ext_localconf.php
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['azure_ad_be'] = [
            'groupsKeyIdentifier' => 'displayName',
        ];

        foreach (self::ENV_KEYS as $key) {
            $this->originalEnv[$key] = $_ENV[$key] ?? null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }

        parent::tearDown();
    }

    /**
     * @template T of \ReflectionProperty|\ReflectionMethod
     * @param T $reflection
     * @return T
     */
    private function makeAccessible(\Reflector $reflection): \Reflector
    {
        // Required before PHP 8.1, deprecated since PHP 8.5
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }
        return $reflection;
    }

    /**
     * @param mixed $value
     */
    private function setProperty(object $object, string $property, $value): void
    {
        $this->makeAccessible(new \ReflectionProperty(AzureAdBeService::class, $property))->setValue($object, $value);
    }

    /**
     * @return mixed
     */
    private function callMethod(object $object, string $method, array $arguments = [])
    {
        return $this->makeAccessible(new \ReflectionMethod(AzureAdBeService::class, $method))->invokeArgs($object, $arguments);
    }

    /**
     * @return array The resulting $userFields
     */
    private function mergeUserFields(array $userFields, array $configuration): array
    {
        $method = $this->makeAccessible(new \ReflectionMethod(AzureAdBeService::class, 'mergeUserFields'));
        $method->invokeArgs(new AzureAdBeService(), [&$userFields, $configuration]);
        return $userFields;
    }

    /**
     * @test
     */
    public function mergeUserFieldsOverridesPlainFields(): void
    {
        $result = $this->mergeUserFields(
            ['realName' => 'Jane Doe', 'lang' => 'default'],
            ['lang' => 'de', 'options' => 3]
        );

        self::assertSame(['realName' => 'Jane Doe', 'lang' => 'de', 'options' => 3], $result);
    }

    /**
     * @test
     */
    public function mergeUserFieldsAppendsUsergroupStringAndRemovesDuplicates(): void
    {
        $result = $this->mergeUserFields(
            ['usergroup' => '1,2'],
            ['append' => ['usergroup' => '2, 3']]
        );

        self::assertSame('1,2,3', $result['usergroup']);
    }

    /**
     * @test
     */
    public function mergeUserFieldsAppendsUsergroupArray(): void
    {
        $result = $this->mergeUserFields(
            ['usergroup' => '1'],
            ['append' => ['usergroup' => [1, 4]]]
        );

        self::assertSame('1,4', $result['usergroup']);
    }

    /**
     * @test
     */
    public function mergeUserFieldsAppendsUsergroupWithoutLeadingCommaWhenNoneExist(): void
    {
        $result = $this->mergeUserFields([], ['append' => ['usergroup' => '5,6']]);

        self::assertSame('5,6', $result['usergroup']);
    }

    /**
     * @test
     */
    public function mergeUserFieldsConcatenatesOtherAppendedFields(): void
    {
        $result = $this->mergeUserFields(
            ['db_mountpoints' => '10,'],
            ['append' => ['db_mountpoints' => '20', 'file_mountpoints' => '1']]
        );

        self::assertSame('10,20', $result['db_mountpoints']);
        self::assertSame('1', $result['file_mountpoints']);
    }

    /**
     * @test
     */
    public function mergeUserFieldsAppliesOverridesBeforeAppends(): void
    {
        $result = $this->mergeUserFields(
            ['usergroup' => '1'],
            ['usergroup' => '7', 'append' => ['usergroup' => '8']]
        );

        self::assertSame('7,8', $result['usergroup']);
    }

    /**
     * @test
     */
    public function getGroupsFollowsNextLinkUntilAllPagesAreFetched(): void
    {
        $firstUrl = 'https://graph.microsoft.com/v1.0/me/memberOf';
        $nextUrl = 'https://graph.microsoft.com/v1.0/me/memberOf?$skiptoken=page2';
        $accessToken = $this->createMock(AccessTokenInterface::class);

        $requestedUrls = [];
        $provider = $this->createMock(GenericProvider::class);
        $provider->expects(self::exactly(2))
            ->method('getAuthenticatedRequest')
            ->willReturnCallback(function (string $method, string $url, $token) use (&$requestedUrls, $accessToken): RequestInterface {
                self::assertSame('get', $method);
                self::assertSame($accessToken, $token);
                $requestedUrls[] = $url;
                return $this->createMock(RequestInterface::class);
            });
        $provider->expects(self::exactly(2))
            ->method('getParsedResponse')
            ->willReturnOnConsecutiveCalls(
                [
                    'value' => [['displayName' => 'Editors'], ['displayName' => 'Admins']],
                    '@odata.nextLink' => $nextUrl,
                ],
                [
                    'value' => [['displayName' => 'Marketing']],
                ]
            );

        $subject = new AzureAdBeService();
        $this->setProperty($subject, 'oAuthProvider', $provider);
        $this->setProperty($subject, 'accessToken', $accessToken);

        $groups = $this->callMethod($subject, 'getGroups');

        self::assertSame([$firstUrl, $nextUrl], $requestedUrls);
        self::assertSame(
            [['displayName' => 'Editors'], ['displayName' => 'Admins'], ['displayName' => 'Marketing']],
            $groups
        );
    }

    /**
     * @test
     */
    public function getGroupsReturnsEmptyArrayWhenResponseHasNoValue(): void
    {
        $provider = $this->createMock(GenericProvider::class);
        $provider->expects(self::once())->method('getAuthenticatedRequest')
            ->willReturn($this->createMock(RequestInterface::class));
        $provider->expects(self::once())->method('getParsedResponse')->willReturn([]);

        $subject = new AzureAdBeService();
        $this->setProperty($subject, 'oAuthProvider', $provider);
        $this->setProperty($subject, 'accessToken', $this->createMock(AccessTokenInterface::class));

        self::assertSame([], $this->callMethod($subject, 'getGroups'));
    }

    /**
     * @return AzureAdBeService&MockObject
     */
    private function createUserServiceMock(): AzureAdBeService
    {
        $subject = $this->getMockBuilder(AzureAdBeService::class)
            ->onlyMethods(['getUserRecord', 'getGroups', 'generateHashedPassword'])
            ->getMock();
        $subject->initAuth('getUserBE', ['status' => 'login'], self::AUTH_INFO, null);
        $this->setProperty($subject, 'loginIdentifier', 'jane.doe@example.com');
        $this->setProperty($subject, 'jsonAccessTokenPayload', ['name' => 'Jane Doe', 'preferred_username' => 'Jane.Doe@example.com']);
        $subject->method('generateHashedPassword')->willReturn('hashed-password');
        return $subject;
    }

    /**
     * Registers a ConnectionPool whose be_users connection records the data passed to insert() or update()
     */
    private function expectWrite(string $expectedMethod, ?array &$writtenFields, ?array &$identifier = null): void
    {
        $connection = $this->createMock(Connection::class);
        $unexpectedMethod = $expectedMethod === 'insert' ? 'update' : 'insert';
        $connection->expects(self::never())->method($unexpectedMethod);

        if ($expectedMethod === 'insert') {
            $connection->expects(self::once())->method('insert')
                ->willReturnCallback(function (string $table, array $data) use (&$writtenFields): int {
                    self::assertSame('be_users', $table);
                    $writtenFields = $data;
                    return 1;
                });
        } else {
            $connection->expects(self::once())->method('update')
                ->willReturnCallback(function (string $table, array $data, array $where) use (&$writtenFields, &$identifier): int {
                    self::assertSame('be_users', $table);
                    $writtenFields = $data;
                    $identifier = $where;
                    return 1;
                });
        }

        $connectionPool = $this->createMock(ConnectionPool::class);
        $connectionPool->method('getConnectionForTable')->with('be_users')->willReturn($connection);
        GeneralUtility::addInstance(ConnectionPool::class, $connectionPool);
    }

    /**
     * @test
     */
    public function getUserInsertsNewUserAndReturnsCreatedRecord(): void
    {
        $record = ['uid' => 12, 'username' => 'jane.doe@example.com'];
        $subject = $this->createUserServiceMock();
        $subject->expects(self::exactly(2))->method('getUserRecord')
            ->willReturnOnConsecutiveCalls(false, $record);
        $subject->expects(self::never())->method('getGroups');
        $this->expectWrite('insert', $writtenFields);

        self::assertSame($record, $subject->getUser());
        self::assertSame(
            [
                'realName' => 'Jane Doe',
                'tstamp' => self::EXEC_TIME,
                'tx_azure_ad_be_payload_user' => json_encode(['name' => 'Jane Doe', 'preferred_username' => 'Jane.Doe@example.com']),
                'username' => 'jane.doe@example.com',
                'email' => 'jane.doe@example.com',
                'password' => 'hashed-password',
                'admin' => 0,
                'crdate' => self::EXEC_TIME,
            ],
            $writtenFields
        );
    }

    /**
     * @test
     */
    public function getUserUpdatesExistingUserWithoutTouchingCredentials(): void
    {
        $record = ['uid' => 12, 'username' => 'jane.doe@example.com'];
        $subject = $this->createUserServiceMock();
        $subject->expects(self::exactly(2))->method('getUserRecord')->willReturn($record);
        $subject->expects(self::never())->method('generateHashedPassword');
        $this->expectWrite('update', $writtenFields, $identifier);

        self::assertSame($record, $subject->getUser());
        self::assertSame(['username' => 'jane.doe@example.com'], $identifier);
        self::assertSame(['realName', 'tstamp', 'tx_azure_ad_be_payload_user'], array_keys($writtenFields));
    }

    /**
     * @test
     */
    public function getUserMergesBeUserDefaults(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['azure_ad_be']['be_user_defaults'] = [
            'lang' => 'de',
            'append' => ['usergroup' => '1,2'],
        ];
        $subject = $this->createUserServiceMock();
        $subject->method('getUserRecord')->willReturn(['uid' => 12]);
        $this->expectWrite('update', $writtenFields);

        $subject->getUser();

        self::assertSame('de', $writtenFields['lang']);
        self::assertSame('1,2', $writtenFields['usergroup']);
    }

    /**
     * @test
     */
    public function getUserAppliesConfigurationOfMatchingEntraIdGroupsOnly(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['azure_ad_be']['be_user_defaults'] = [
            'append' => ['usergroup' => '1'],
        ];
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['azure_ad_be']['groups'] = [
            'Editors' => ['append' => ['usergroup' => '2,3']],
            'Admins' => ['admin' => 1, 'append' => ['usergroup' => '3,4']],
            'Unassigned' => ['append' => ['usergroup' => '99']],
        ];
        $entraGroups = [
            ['displayName' => 'Editors', 'id' => 'a'],
            ['displayName' => 'Admins', 'id' => 'b'],
            ['displayName' => 'Not configured', 'id' => 'c'],
        ];
        $subject = $this->createUserServiceMock();
        $subject->method('getUserRecord')->willReturn(['uid' => 12]);
        $subject->expects(self::once())->method('getGroups')->willReturn($entraGroups);
        $this->expectWrite('update', $writtenFields);

        $subject->getUser();

        self::assertSame('1,2,3,4', $writtenFields['usergroup']);
        self::assertSame(1, $writtenFields['admin']);
        self::assertSame(json_encode($entraGroups), $writtenFields['tx_azure_ad_be_payload_groups']);
    }

    /**
     * @test
     */
    public function getUserMatchesGroupsOnConfiguredKeyIdentifier(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['azure_ad_be']['groupsKeyIdentifier'] = 'id';
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['azure_ad_be']['groups'] = [
            'Editors' => ['append' => ['usergroup' => '2']],
            'group-guid' => ['append' => ['usergroup' => '5']],
        ];
        $subject = $this->createUserServiceMock();
        $subject->method('getUserRecord')->willReturn(['uid' => 12]);
        $subject->method('getGroups')->willReturn([['displayName' => 'Editors', 'id' => 'group-guid']]);
        $this->expectWrite('update', $writtenFields);

        $subject->getUser();

        self::assertSame('5', $writtenFields['usergroup']);
    }

    /**
     * @test
     */
    public function getUserReturnsNullWhenNotLoggingIn(): void
    {
        $subject = new AzureAdBeService();
        $subject->initAuth('getUserBE', ['status' => 'logout'], self::AUTH_INFO, null);
        $this->setProperty($subject, 'loginIdentifier', 'jane.doe@example.com');

        self::assertNull($subject->getUser());
    }

    /**
     * @test
     */
    public function getUserReturnsNullWithoutLoginIdentifier(): void
    {
        $subject = new AzureAdBeService();
        $subject->initAuth('getUserBE', ['status' => 'login'], self::AUTH_INFO, null);

        self::assertNull($subject->getUser());
    }

    /**
     * @test
     */
    public function authUserReturnsContinueCodeWithoutLoginIdentifier(): void
    {
        self::assertSame(100, (new AzureAdBeService())->authUser([]));
    }

    /**
     * @test
     */
    public function authUserAuthenticatesOnceLoginIdentifierIsSet(): void
    {
        $subject = new AzureAdBeService();
        $this->setProperty($subject, 'loginIdentifier', 'jane.doe@example.com');

        self::assertSame(300, $subject->authUser([]));
    }

    /**
     * @test
     */
    public function processLoginDataSkipsEntraIdWhenPasswordWasSubmitted(): void
    {
        $loginData = ['uname' => 'admin', 'uident' => 'password'];

        self::assertFalse((new AzureAdBeService())->processLoginData($loginData, 'normal'));
        self::assertSame(['uname' => 'admin', 'uident' => 'password'], $loginData);
    }

    private function getAuthorizationScopes(): array
    {
        $_ENV['TYPO3_AZURE_AD_BE_CLIENT_ID'] = 'client-id';
        $_ENV['TYPO3_AZURE_AD_BE_CLIENT_SECRET'] = 'client-secret';
        $_ENV['TYPO3_AZURE_AD_BE_URL_AUTHORIZE'] = 'https://login.example.com/authorize';
        $_ENV['TYPO3_AZURE_AD_BE_URL_ACCESS_TOKEN'] = 'https://login.example.com/token';

        /** @var GenericProvider $provider */
        $provider = $this->callMethod(new AzureAdBeService(), 'getOAuthProvider', ['https://example.com/typo3/']);
        parse_str((string)parse_url($provider->getAuthorizationUrl(), PHP_URL_QUERY), $query);

        return explode(' ', $query['scope']);
    }

    /**
     * @test
     */
    public function oAuthProviderRequestsDefaultScopes(): void
    {
        self::assertSame(['User.Read', 'profile', 'openid', 'email'], $this->getAuthorizationScopes());
    }

    /**
     * @test
     */
    public function oAuthProviderRequestsDirectoryScopeWhenGroupsAreConfigured(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['azure_ad_be']['groups'] = [];

        self::assertSame(
            ['User.Read', 'profile', 'openid', 'email', 'Directory.Read.All'],
            $this->getAuthorizationScopes()
        );
    }
}
