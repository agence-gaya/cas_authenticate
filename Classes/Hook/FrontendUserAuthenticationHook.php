<?php
namespace GAYA\CasAuthenticate\Hook;

use GAYA\CasAuthenticate\Service\CasAuthenticateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

require_once \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('cas_authenticate') . 'Contrib/phpCAS-1.2.2/CAS.php';

class FrontendUserAuthenticationHook {

	public function logoff() {
		$funcArgs = func_get_args();
		$loginType = $funcArgs[1]->loginType;

		if ($loginType === 'FE') {
			/** @var \GAYA\CasAuthenticate\Utility\ConfigurationUtility $configurationUtility */
			$configurationUtility = GeneralUtility::makeInstance(\GAYA\CasAuthenticate\Utility\ConfigurationUtility::class);

			// we read the conf data stored in ext_conf_template.txt
			\phpCAS::client(CAS_VERSION_2_0, $configurationUtility->get('serverHostname'), $configurationUtility->get('serverPort'), $configurationUtility->get('serverUri'));
			\phpCAS::setCasServerCACert($configurationUtility->get('certificateDirectory') . $configurationUtility->get('casServerCACertificate'));
			$auth = \phpCAS::checkAuthentication();
			// if the user is loged in, it's time we log him out of CAS
			if ($auth) {
				\phpCAS::logoutWithUrl($configurationUtility->get('fixedServiceLogoutUrl'));
			}
		}
	}
}