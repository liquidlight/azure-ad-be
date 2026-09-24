<?php

defined('TYPO3') or die();

$columns = [
	'tx_entraidbe_payload_user' => [
		'label' => 'LLL:EXT:ll_entra_id_be/Resources/Private/Language/locallang.xlf:be_users.tx_entraidbe_payload_user',
		'exclude' => 1,
		'config' => [
			'type' => 'text',
			'cols' => 30,
			'rows' => 10,
			'readOnly' => true
		],
	],
	'tx_entraidbe_payload_groups' => [
		'label' => 'LLL:EXT:ll_entra_id_be/Resources/Private/Language/locallang.xlf:be_users.tx_entraidbe_payload_groups',
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
	'--div--;LLL:EXT:ll_entra_id_be/Resources/Private/Language/locallang.xlf:be_users.entra,
		tx_entraidbe_payload_user,
		--linebreak--,
		tx_entraidbe_payload_groups
	',
	'',
	'after:description'
);
