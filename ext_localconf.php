<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

/** @var \GAYA\CasAuthenticate\Utility\ConfigurationUtility configurationUtility */
$configurationUtility = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\GAYA\CasAuthenticate\Utility\ConfigurationUtility::class);
if ($configurationUtility->get('enabled')) {
	// Service de connexion du frontend user via un serveur CAS
	TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addService(
		'GAYA.' . $_EXTKEY,
		'auth',
		\GAYA\CasAuthenticate\Service\CasAuthenticateService::class,
		[
			'title' => 'Authentication with a CAS server',
			'description' => 'Permit to authenticate frontend users through a CAS server',
			'subtype' => 'getUserFE,authUserFE',
			'available' => true,
			'priority' => 100,
			'quality' => 100,
			'os' => '',
			'exec' => '',
			'className' => \GAYA\CasAuthenticate\Service\CasAuthenticateService::class,
		]
	);

	$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_userauth.php']['logoff_post_processing'][] = 'GAYA\\CasAuthenticate\\Hook\\FrontendUserAuthenticationHook->logoff';
}

