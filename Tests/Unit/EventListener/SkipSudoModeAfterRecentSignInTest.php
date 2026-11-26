<?php

declare(strict_types=1);

namespace LiquidLight\EntraIdBe\Tests\Unit\EventListener;

use LiquidLight\EntraIdBe\EventListener\RecordEntraIdSignIn;
use LiquidLight\EntraIdBe\EventListener\SkipSudoModeAfterRecentSignIn;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Security\SudoMode\Access\AccessClaim;
use TYPO3\CMS\Backend\Security\SudoMode\Access\RouteAccessSubject;
use TYPO3\CMS\Backend\Security\SudoMode\Access\ServerRequestInstruction;
use TYPO3\CMS\Backend\Security\SudoMode\Access\TableAccessSubject;
use TYPO3\CMS\Backend\Security\SudoMode\Event\SudoModeRequiredEvent;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class SkipSudoModeAfterRecentSignInTest extends UnitTestCase
{
    private const EXEC_TIME = 1700000000;

    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['EXEC_TIME'] = self::EXEC_TIME;
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ll_entra_id_be'] = [
            'sudoModeWindow' => 15,
        ];

        $languageService = $this->createMock(LanguageService::class);
        $languageService->method('sL')->willReturn('Signed in more than %d minutes ago');
        $GLOBALS['LANG'] = $languageService;
    }

    private function setBackendUser(?int $signedInAt, bool $isAdmin = false): void
    {
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->method('getSessionData')->willReturnMap([
            [RecordEntraIdSignIn::SESSION_KEY, $signedInAt],
        ]);
        $backendUser->method('isAdmin')->willReturn($isAdmin);
        $GLOBALS['BE_USER'] = $backendUser;
    }

    private function createElevateToAdminEvent(): SudoModeRequiredEvent
    {
        return $this->createEvent(new RouteAccessSubject(
            '/ajax/elevate-to-admin/elevate',
            null,
            SkipSudoModeAfterRecentSignIn::ELEVATE_TO_ADMIN_GROUP
        ));
    }

    private function createEditUserEvent(): SudoModeRequiredEvent
    {
        return $this->createEvent(new TableAccessSubject('be_users.password.2', null, null, false));
    }

    private function createEvent(RouteAccessSubject|TableAccessSubject $subject): SudoModeRequiredEvent
    {
        $instruction = ServerRequestInstruction::createForServerRequest(new ServerRequest('https://example.com/typo3/'));

        return new SudoModeRequiredEvent(new AccessClaim($instruction, self::EXEC_TIME + 300, null, null, $subject));
    }

    #[Test]
    public function keepsVerificationForSessionsNotSignedInThroughEntraId(): void
    {
        $this->setBackendUser(null, true);
        $event = $this->createEditUserEvent();

        (new SkipSudoModeAfterRecentSignIn())($event);

        self::assertTrue($event->isVerificationRequired());
    }

    #[Test]
    public function keepsVerificationWithoutBackendUser(): void
    {
        unset($GLOBALS['BE_USER']);
        $event = $this->createElevateToAdminEvent();

        (new SkipSudoModeAfterRecentSignIn())($event);

        self::assertTrue($event->isVerificationRequired());
    }

    #[Test]
    public function skipsVerificationWithinWindowOfEntraIdSignIn(): void
    {
        $this->setBackendUser(self::EXEC_TIME - 15 * 60);
        $event = $this->createElevateToAdminEvent();

        (new SkipSudoModeAfterRecentSignIn())($event);

        self::assertFalse($event->isVerificationRequired());
    }

    #[Test]
    public function usesConfiguredWindow(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ll_entra_id_be']['sudoModeWindow'] = 60;
        $this->setBackendUser(self::EXEC_TIME - 30 * 60);
        $event = $this->createEditUserEvent();

        (new SkipSudoModeAfterRecentSignIn())($event);

        self::assertFalse($event->isVerificationRequired());
    }

    #[Test]
    public function defaultsToFifteenMinuteWindowWhenNotConfigured(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ll_entra_id_be']['sudoModeWindow']);
        $this->setBackendUser(self::EXEC_TIME - 16 * 60);
        $event = $this->createEditUserEvent();

        (new SkipSudoModeAfterRecentSignIn())($event);

        self::assertTrue($event->isVerificationRequired());
    }

    #[Test]
    public function asksToSignInAgainForAdminElevationAfterWindow(): void
    {
        $this->setBackendUser(self::EXEC_TIME - 16 * 60);

        try {
            (new SkipSudoModeAfterRecentSignIn())($this->createElevateToAdminEvent());
            self::fail('Expected a response asking to sign in again');
        } catch (PropagateResponseException $exception) {
            $response = $exception->getResponse();
        }

        self::assertSame(
            ['success' => false, 'message' => 'Signed in more than 15 minutes ago'],
            json_decode((string)$response->getBody(), true)
        );
        self::assertStringStartsWith(
            SkipSudoModeAfterRecentSignIn::PROMPT_LOGIN_COOKIE . '=1;',
            $response->getHeaderLine('Set-Cookie')
        );
    }

    #[Test]
    public function skipsVerificationForAdminsAfterWindow(): void
    {
        $this->setBackendUser(self::EXEC_TIME - 60 * 60, true);
        $event = $this->createEditUserEvent();

        (new SkipSudoModeAfterRecentSignIn())($event);

        self::assertFalse($event->isVerificationRequired());
    }

    #[Test]
    public function keepsVerificationForNonAdminsAfterWindow(): void
    {
        $this->setBackendUser(self::EXEC_TIME - 60 * 60);
        $event = $this->createEditUserEvent();

        (new SkipSudoModeAfterRecentSignIn())($event);

        self::assertTrue($event->isVerificationRequired());
    }
}
