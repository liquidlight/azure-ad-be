# Microsoft Entra ID - TYPO3 Backend Login

Log in to the TYPO3 backend with Microsoft Entra ID (formerly Azure Active Directory).

> [!TIP]
> This was originally forked from [different-technology/azure-ad-be](https://github.com/different-technology/azure-ad-be) - big thanks to them for the original code.
> Moving from that package? See [Migrating from different-technology/azure-ad-be](#migrating-from-different-technologyazure-ad-be).

## Installation

```
composer require liquidlight/typo3-entra-id-be
```

## Setup

### Env

Add the following env parameters:

```
TYPO3_ENTRA_ID_BE_CLIENT_ID=<your-client-id>
TYPO3_ENTRA_ID_BE_CLIENT_SECRET=<your-secret>
TYPO3_ENTRA_ID_BE_URL_AUTHORIZE=https://login.microsoftonline.com/<see-your-endpoints>/oauth2/v2.0/authorize
TYPO3_ENTRA_ID_BE_URL_ACCESS_TOKEN=https://login.microsoftonline.com/<see-your-endpoints>/oauth2/v2.0/token
```

### Cookies

In TYPO3, set [`cookieSameSite`](https://docs.typo3.org/m/typo3/reference-coreapi/11.5/en-us/Configuration/Typo3ConfVars/BE.html#typo3ConfVars_be_cookieSameSite) to `none`

On your server, ensure `session.cookie_samesite =` is set to nothing.

## User Permissions

If you would like to set some default permissions for all users logging in via Entra ID, this can be done with the `be_user_defaults` array in the `EXTCONF` configuration.

For example, if you wish to set a group & other fields for everyone logging in via Entra ID, you can add the following:

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ll_entra_id_be']['be_user_defaults'] = [
    // * Allows us to identify who is signed in via Entra ID (and apply TS config)
    'append' => [
        'usergroup' => '61',
    ],

    // * options = 3 - this enables the "Mount from groups" options for DB & filemounts
    'options' => 3,
];
```

## Group permissions

You may wish to affect the users permissions or properties depending on which Entra ID group they are in.

Ensure your application has `Directory.Read.All` permissions.

In your site_package `ext_localconf.php`, create an array where the group display name is the index and the affected `be_user` properties are the values. This array gets merged in order from top to bottom for each group the user is a member of.

For example:

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ll_entra_id_be']['groups'] = [
    'admin-group-name' => [
        'admin' => 1
    ],
    'editor-group' => [
        'usergroup' => 12
    ]
];
```

### Append Group permissions

If you want to append items to a user instead of replacing them, you can use the `append` array item.

- `usergroup`s can be an array or a comma separated list,
- everything else in the `append` item is concatenated together with no further transforms

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ll_entra_id_be']['groups'] = [
    'Group 1' => [
        'usergroup' => '61',
        'options' => 3,
    ],

    'Group 2' => [
        'append' => [
            'usergroup' => '60',
        ]
    ],

    'Group 3' => [
        'append' => [
            'usergroup' => ['18', '19'],
        ]
    ],

    'Group 4' => [
        'append' => [
            'usergroup' => '60, 62',
        ]
    ],
];
```

### Configure Group Identifier

If you wish to use a different identifier for the groups (e.g. the `id` instead of the `displayName`), you can configure this in your `ext_localconf.php`

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ll_entra_id_be'] = [
    'groupsKeyIdentifier' => 'id'
];
```

## Disable TYPO3 login

If you want to disable logging in via username and password, add the following to your `ext_localconf.php`

```php
unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['backend']['loginProviders'][1433416747]);
```

## Disable User Editing

With logging in via Microsoft, you don't want the user changing their username and/or password within TYPO3 itself.

Add the following `user.tsconfig` to disallow these changes:

```
setup {
    fields {
        realName.disabled = 1
        email.disabled = 1
        avatar.disabled = 1
        lang.disabled = 1

        passwordCurrent.disabled = 1
        password.disabled = 1
        password2.disabled = 1
        mfaProviders.disabled = 1
    }
}
```

These can be wrapped in a condition based on user group, if you have a mix of SSO and normal users

## Migrating from different-technology/azure-ad-be

This package replaces `different-technology/azure-ad-be` (and the Liquid Light fork of it). Everything has been renamed, so:

1. Remove the old package and any `repositories` entry pointing at the fork, then install this one:
   ```
   composer remove different-technology/azure-ad-be
   composer require liquidlight/typo3-entra-id-be
   ```
2. Rename the env variables from `TYPO3_AZURE_AD_BE_*` to `TYPO3_ENTRA_ID_BE_*`
3. Rename any configuration from `$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['azure_ad_be']` to `$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['ll_entra_id_be']`
4. Replace any references to `EXT:azure_ad_be` or the `DifferentTechnology\AzureAdBe` namespace
5. Run the database compare. The `be_users` columns are now `tx_entraidbe_payload_user` and `tx_entraidbe_payload_groups`. These hold a copy of the Entra ID data and are rewritten on every login, so the old `tx_azure_ad_be_*` columns can be dropped
