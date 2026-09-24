<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'Microsoft Entra ID - TYPO3 Backend Login',
    'description' => 'Authenticate backend users against Microsoft Entra ID',
    'category' => 'plugin',
    'author' => 'Liquid Light',
    'author_email' => 'info@liquidlight.co.uk',
    'author_company' => 'Liquid Light Ltd',
    'state' => 'stable',
    'clearCacheOnLoad' => 1,
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
