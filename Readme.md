# Microsoft Entra ID - TYPO3 Backend Login
Former title: Azure Active Directory - TYPO3 Backend Login

## Setup

### Env

Add the following env parameters:

```
TYPO3_AZURE_AD_BE_CLIENT_ID=<your-client-id>
TYPO3_AZURE_AD_BE_CLIENT_SECRET=<your-secret>
TYPO3_AZURE_AD_BE_URL_AUTHORIZE=https://login.microsoftonline.com/<see-your-endpoints>/oauth2/v2.0/authorize
TYPO3_AZURE_AD_BE_URL_ACCESS_TOKEN=https://login.microsoftonline.com/<see-your-endpoints>/oauth2/v2.0/token
```

### Cookies

In TYPO3, set [`cookieSameSite`](https://docs.typo3.org/m/typo3/reference-coreapi/11.5/en-us/Configuration/Typo3ConfVars/BE.html#typo3ConfVars_be_cookieSameSite) to `none`

On your server, ensure `session.cookie_samesite =` is set to nothing.

### Group permissions

You may wish to affect the users permissions or properties depending on which Entra ID / Azure AD group they are in.

Ensure your application has `Directory.Read.All` permissions.

In your site_package `ext_localconf.php`, create an array where the group display name is the index and the affected `be_user` properties are the values. This array gets merged in order from top to bottom for each group the user is a member of.

For example:

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['azure_ad_be']['groups'] = [
	'admin-group-name' => [
		'admin' => 1
	],
	'editor-group' => [
		'usergroup' => 12
	]
];
```

#### Append Group permissions

If you want to append `usergroups` instead of replacing, add an array of `usergroupAppend` to each item:

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['azure_ad_be']['groups'] = [
	'Group 1' => [
		'usergroup' => '61',
		'options' => 3,
	],
	'Group 2' => [
		'usergroupAppend' => ['60'],
	],
	'Group 3' => [
		'usergroupAppend' => ['18'],
	],
];
```

#### Configure Group Identifier

If you wish to use a different identifier for the groups (e.g. the `id` instead of the `displayName`), you can configure this in your `ext_localconf.php`

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['azure_ad_be'] = [
    'groupsKeyIdentifier' => 'id'
];
```

### Disable TYPO3 login

If you want to disable logging in via username and password, add the following to your `ext_localconf.php`

```php
unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['backend']['loginProviders'][1433416747]);
```

### Disable User Editing

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
