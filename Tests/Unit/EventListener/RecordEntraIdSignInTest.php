<?php

declare(strict_types=1);

namespace LiquidLight\EntraIdBe\Tests\Unit\EventListener;

use LiquidLight\EntraIdBe\EventListener\RecordEntraIdSignIn;
use LiquidLight\EntraIdBe\Service\EntraIdBeService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Event\AfterUserLoggedInEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class RecordEntraIdSignInTest extends UnitTestCase
{
    private const EXEC_TIME = 1700000000;

    protected bool $resetSingletonInstances = true;

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['EXEC_TIME'] = self::EXEC_TIME;
    }

    private function setAuthenticatedThroughEntraId(bool $authenticated): void
    {
        $service = $this->createMock(EntraIdBeService::class);
        $service->method('hasAuthenticatedUser')->willReturn($authenticated);
        GeneralUtility::setSingletonInstance(EntraIdBeService::class, $service);
    }

    #[Test]
    public function storesSignInTimeForEntraIdLogin(): void
    {
        $this->setAuthenticatedThroughEntraId(true);
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->expects(self::once())
            ->method('setAndSaveSessionData')
            ->with(RecordEntraIdSignIn::SESSION_KEY, self::EXEC_TIME);

        (new RecordEntraIdSignIn())(new AfterUserLoggedInEvent($backendUser));
    }

    #[Test]
    public function ignoresLoginsThroughOtherServices(): void
    {
        $this->setAuthenticatedThroughEntraId(false);
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->expects(self::never())->method('setAndSaveSessionData');

        (new RecordEntraIdSignIn())(new AfterUserLoggedInEvent($backendUser));
    }

    #[Test]
    public function ignoresNonBackendLogins(): void
    {
        $this->setAuthenticatedThroughEntraId(true);
        $user = $this->createMock(AbstractUserAuthentication::class);
        $user->expects(self::never())->method('setAndSaveSessionData');

        (new RecordEntraIdSignIn())(new AfterUserLoggedInEvent($user));
    }
}
