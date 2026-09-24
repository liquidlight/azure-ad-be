<?php
declare(strict_types=1);

defined('TYPO3') or die();

(function () {
    /**
     * Default EXTCONF configuration
     */
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ll_entra_id_be'] = [
        // What key should be used to identify groups
        'groupsKeyIdentifier' => 'displayName'
    ];

    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['backend']['loginProviders'][1650912385] = [
        'provider' => \LiquidLight\EntraIdBe\LoginProvider\EntraIdLoginProvider::class,
        'sorting' => 100,
		'iconIdentifier' => 'actions-brand-windows',
        'label' => 'LLL:EXT:ll_entra_id_be/Resources/Private/Language/locallang.xlf:login.link'
    ];

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addService(
        'll_entra_id_be',
        'auth',
        'tx_entraidbe',
        [
            'title' => 'Entra ID Authentication',
            'description' => 'Entra ID service for backend',
            'subtype' => 'processLoginDataBE,getUserBE,authUserBE',
            'available' => true,
            'priority' => 100,
            // Must be lower than for \TYPO3\CMS\Sv\AuthenticationService (50) to let other processing take place before
            'quality' => 50,
            'os' => '',
            'exec' => '',
            'className' => \LiquidLight\EntraIdBe\Service\EntraIdBeService::class
        ]
    );
})();
