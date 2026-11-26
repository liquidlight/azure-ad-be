<?php

declare(strict_types=1);

namespace LiquidLight\EntraIdBe\EventListener;

use LiquidLight\EntraIdBe\Service\EntraIdBeService;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Event\AfterUserLoggedInEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Stores when the current backend session was signed in through Entra ID,
 * so sudo mode can be skipped shortly after (see SkipSudoModeAfterRecentSignIn)
 */
final class RecordEntraIdSignIn
{
    public const SESSION_KEY = 'll_entra_id_be.signed_in_at';

    public function __invoke(AfterUserLoggedInEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof BackendUserAuthentication) {
            return;
        }

        // The authentication service is a singleton, so this is the instance which handled this login
        if (!GeneralUtility::makeInstance(EntraIdBeService::class)->hasAuthenticatedUser()) {
            return;
        }

        $user->setAndSaveSessionData(self::SESSION_KEY, $GLOBALS['EXEC_TIME']);
    }
}
