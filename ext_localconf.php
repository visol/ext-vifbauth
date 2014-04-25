<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
	'Visol.' . $_EXTKEY,
	'Auth',
	array(
		'Authentication' => 'login,revokePermissions',
	),
	// non-cacheable actions
	array(
		'Authentication' => 'login,revokePermissions',
	)
);

// Register opauth as authentication service
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addService($_EXTKEY, 'auth', 'Visol\\Opauth\\Service\\OpauthService',
	array(
		'title' => 'Facebook Authentication',
		'description' => 'Facebook Authentication for Frontend',
		'subtype' => 'getUserFE,authUserFE',
		'available' => TRUE,
		// Must be higher than for tx_sv_auth (50) or tx_sv_auth will deny request unconditionally
		'priority' => 90,
		'quality' => 90,
		'os' => '',
		'exec' => '',
		'className' => 'Visol\\Vifbauth\\Service\\FacebookAuthService',
		'classFile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'Classes/Service/FacebookAuthService.php'
	)
);

?>