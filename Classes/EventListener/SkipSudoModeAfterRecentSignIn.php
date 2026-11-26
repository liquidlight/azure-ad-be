<?php

declare(strict_types=1);

namespace LiquidLight\EntraIdBe\EventListener;

use Symfony\Component\HttpFoundation\Cookie;
use TYPO3\CMS\Backend\Security\SudoMode\Access\AccessClaim;
use TYPO3\CMS\Backend\Security\SudoMode\Event\SudoModeRequiredEvent;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Entra ID users don't know their TYPO3 password, so they can't pass the sudo
 * mode password dialog. Skip it if they signed in through Entra ID recently.
 */
final class SkipSudoModeAfterRecentSignIn
{
    /**
     * Sudo mode group of the "Enter admin mode" route in liquidlight/typo3-elevate-to-admin
     */
    public const ELEVATE_TO_ADMIN_GROUP = 'll_elevate_to_admin';

    /**
     * Makes the next Entra ID sign-in ask for credentials again
     */
    public const PROMPT_LOGIN_COOKIE = 'll_entra_id_be_prompt_login';

    private const DEFAULT_WINDOW_MINUTES = 15;

    private const PROMPT_LOGIN_COOKIE_LIFETIME = 900;

    public function __invoke(SudoModeRequiredEvent $event): void
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof BackendUserAuthentication) {
            return;
        }

        // Sessions which were not signed in through Entra ID keep the default behaviour
        $signedInAt = (int)($backendUser->getSessionData(RecordEntraIdSignIn::SESSION_KEY) ?? 0);
        if ($signedInAt === 0) {
            return;
        }

        if ($GLOBALS['EXEC_TIME'] - $signedInAt <= $this->getWindowMinutes() * 60) {
            $event->setVerificationRequired(false);
            return;
        }

        if ($this->isAdminElevation($event->getClaim())) {
            throw new PropagateResponseException($this->buildSignInAgainResponse());
        }

        // Admins can already do everything sudo mode protects, apart from entering admin mode
        if ($backendUser->isAdmin()) {
            $event->setVerificationRequired(false);
        }
    }

    /**
     * Builds the Set-Cookie header value, a lifetime of 0 removes the cookie
     */
    public static function buildPromptLoginCookie(int $lifetime = self::PROMPT_LOGIN_COOKIE_LIFETIME): string
    {
        return (string)Cookie::create(self::PROMPT_LOGIN_COOKIE)
            ->withValue($lifetime > 0 ? '1' : '')
            ->withExpires($lifetime > 0 ? $GLOBALS['EXEC_TIME'] + $lifetime : 0)
            ->withPath('/')
            ->withSecure((bool)GeneralUtility::getIndpEnv('TYPO3_SSL'))
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }

    private function getWindowMinutes(): int
    {
        return (int)($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ll_entra_id_be']['sudoModeWindow'] ?? self::DEFAULT_WINDOW_MINUTES);
    }

    private function isAdminElevation(AccessClaim $claim): bool
    {
        foreach ($claim->subjects as $subject) {
            if ($subject->getGroup() === self::ELEVATE_TO_ADMIN_GROUP) {
                return true;
            }
        }

        return false;
    }

    /**
     * Uses the response format of liquidlight/typo3-elevate-to-admin, which shows the message as an error
     */
    private function buildSignInAgainResponse(): JsonResponse
    {
        $message = sprintf(
            $GLOBALS['LANG']->sL('LLL:EXT:ll_entra_id_be/Resources/Private/Language/locallang.xlf:sudoMode.signInAgain'),
            $this->getWindowMinutes()
        );

        return (new JsonResponse(['success' => false, 'message' => $message]))
            ->withAddedHeader('Set-Cookie', self::buildPromptLoginCookie());
    }
}
