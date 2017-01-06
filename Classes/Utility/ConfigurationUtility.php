<?php
namespace GAYA\CasAuthenticate\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ConfigurationUtility
 */
class ConfigurationUtility implements \TYPO3\CMS\Core\SingletonInterface {
	/**
	 * @var array
	 */
	protected $configuration = [];

	/**
	 * ConfigurationUtility constructor.
	 */
	public function __construct() {
		$extConfVars = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['cas_authenticate']);

		// Initialize server CAS parameters.
		$this->configuration = $extConfVars;
		$this->configuration['fixedServiceUrl'] = GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST') . '/?logintype=login';
		$this->configuration['fixedServiceLogoutUrl'] = GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST');

		$domain = GeneralUtility::getIndpEnv('HTTP_HOST');

		foreach ($this->configuration as $key => $value) {
			$values = explode('|', $value);
			$newValues = [];
			foreach ($values as $subValue) {
				$subValue = trim($subValue);
				if (strpos($subValue, '::') === false) {
					$newValues[] = $subValue;
				} elseif (preg_match('#^('.preg_quote($domain).'::)(.+)$#i', $subValue, $match)) {
					$newValues[] = $match[2];
				}
			}

			if (count($newValues) > 1) {
				GeneralUtility::sysLog("Duplicate entry for domain " . $domain . " for key" . $key . ". Using " . $this->configuration[$key], 'gaya_httperror', GeneralUtility::SYSLOG_SEVERITY_WARNING);
			}

			$newValue = array_shift($newValues);
			if (is_numeric($newValue)) {
				$newValue = (int)$newValue;
			}
			$this->configuration[$key] = $newValue;
		}

		return $this;
	}

	/**
	 * @param string $variable
	 * @return string
	 */
	public function get($variable) {
		if (!isset($this->configuration[$variable])) {
			return '';
		}

		return $this->configuration[$variable];
	}
}