<?php

defined('TYPO3') or die();

$columns = [
	'tx_azure_ad_be_payload_user' => [
		'label' => 'LLL:EXT:azure_ad_be/Resources/Private/Language/locallang.xlf:be_users.tx_azure_ad_be_payload_user',
		'exclude' => 1,
		'config' => [
			'type' => 'text',
			'cols' => 30,
			'rows' => 10,
			'readOnly' => true
		],
	],
	'tx_azure_ad_be_payload_groups' => [
		'label' => 'LLL:EXT:azure_ad_be/Resources/Private/Language/locallang.xlf:be_users.tx_azure_ad_be_payload_groups',
		'exclude' => 1,
		'config' => [
			'type' => 'text',
			'cols' => 30,
			'rows' => 10,
			'readOnly' => true
		],
	],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('be_users', $columns);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
	'be_users',
	'--div--;LLL:EXT:azure_ad_be/Resources/Private/Language/locallang.xlf:be_users.azure,
		tx_azure_ad_be_payload_user,
		--linebreak--,
		tx_azure_ad_be_payload_groups
	',
	'',
	'after:description'
);
