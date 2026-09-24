<?php
/** @var string $_EXTKEY */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Azure Active Directory - TYPO3 Backend Login',
    'description' => 'Authenticate backend users against Azure AD',
    'category' => 'plugin',
    'author' => 'different.technology',
    'author_email' => 'typo3@markus-hoelzle.de',
    'state' => 'stable',
    'clearCacheOnLoad' => 1,
    'version' => '',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
