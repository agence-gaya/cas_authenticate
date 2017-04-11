<?php
namespace GAYA\CasAuthenticate\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;

require_once \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('cas_authenticate') . 'Contrib/phpCAS-1.2.2/CAS.php';

/**
 * Class CasAuthenticateService
 */
class CasAuthenticateService extends \TYPO3\CMS\Sv\AbstractAuthenticationService {

	/**
	 * @var \GAYA\CasAuthenticate\Utility\ConfigurationUtility
	 */
	protected $configurationUtility;

	/**
	 * @var \TYPO3\CMS\Extbase\SignalSlot\Dispatcher
	 */
	protected $signalSlotDispatcher;

	/**
	 * CasAuthenticateService constructor.
	 */
	public function __construct() {
		$this->configurationUtility = GeneralUtility::makeInstance(\GAYA\CasAuthenticate\Utility\ConfigurationUtility::class);
		$this->signalSlotDispatcher = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class);
	}

	/**
	 * @param array $user
	 * @return bool|int
	 */
	public function authUser($user) {
		$OK = false;

		if ($this->mode == 'authUserFE') {
			// user is the user data we read above, so if it's filled with data, it means
			// the login worked great and that we have a valid user, so all we need to do now is
			// authenticate him... 200 means the user is good to go
			if ($user) {
				$OK = 200;

				$signalArguments = array($OK, $user, $this->configurationUtility->get('userGroupIdList'));
				$signalArguments = $this->signalSlotDispatcher->dispatch(__CLASS__, 'onAfterUserAuthenticatedByCas', $signalArguments);
				$OK = $signalArguments[0];
			}
		}

		return $OK;
	}

	/**
	 * @return bool|mixed
	 */
	public function getUser() {
		$user = false;
		// Enable debugging
		if ($this->configurationUtility->get('phpCasSetDebug') === '1') {
			\phpCAS::setDebug();
		}

		// Check if we're loged on CAS server, so we log in TYPO3 too
		if ($this->mode == 'getUserFE' && !$GLOBALS['TSFE']->fe_user->user) {
			// Configuration des paramètres pour la connexion au serveur CAS
			\phpCAS::client(CAS_VERSION_2_0, $this->configurationUtility->get('serverHostname'), $this->configurationUtility->get('serverPort'), $this->configurationUtility->get('serverUri'), $this->configurationUtility->get('changeSessionID'));
			\phpCAS::setServerServiceValidateURL($this->configurationUtility->get('serverServiceValidateUrl'));
			\phpCAS::setCasServerCACert($this->configurationUtility->get('certificateDirectory') . $this->configurationUtility->get('casServerCACertificate'));
			// setNoCasServerValidation() à utiliser si on ne veut pas utliser le certificat pour valider le serveur CAS. A NE PAS FAIRE EN PROD.
//			\phpCAS::setNoCasServerValidation(); //A utiliser si vous avez un certificat auto-signé par exemple
			\phpCAS::setFixedServiceUrl($this->configurationUtility->get('fixedServiceUrl'));

			\phpCAS::forceAuthentication(); // On veut que l'utilisateur soit connecté
			$username = \phpCAS::getUser();
			if ($username) {
				// Un pid est défini pour l'utilisateur, on met à jour la condition de la requête la concernant
				if ($this->configurationUtility->get('userPid')) {
					$this->db_user['check_pid_clause'] = ' AND pid = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($this->configurationUtility->get('userPid'), 'fe_users');
				}
				// Vérifier l'existence du compte Typo3 pour l'utilisateur (username ou uid identique ? à voir suivant les informations fournies pas le CAS de l'utc)
				$user = $this->fetchUserRecord($username);
				if (!$user) {
					// Le compte Typo3 n'existe pas il faut le créer
					// Récupération du groupe
					$groupIdList = explode(',', $this->configurationUtility->get('userGroupIdList'));
					$userGroupList = $this->getValidGroupUidListForUidList($groupIdList);
					$user['usergroup'] = $userGroupList;
					$user['username'] = $username;
					$user['pid'] = $this->configurationUtility->get('userPid');
				}

				$casAttributes = \phpCAS::getAttributes();

				// On met à jour les information du user avec celles du cas
				$this->updateFrontendUserWithCasServerData($user, $casAttributes);

				$signalArguments = array($user, $casAttributes, $this->configurationUtility->get('userGroupIdList'));
				$signalArguments = $this->signalSlotDispatcher->dispatch(__CLASS__, 'onBeforeWriteFrontendUserInDatabase', $signalArguments);
				$user = $signalArguments[0];

				$this->writeFrontendUserInDatabase($user);
			}
		}

		return $user;
	}

	/**
	 * Met à jour les propriétés de user front avec les informations reçues du compte utilisé pour se connecter au serveur CAS
	 *
	 * @param array $user
	 * @param array $data
	 */
	protected function updateFrontendUserWithCasServerData(&$user, $data) {
		$user['email'] = $data['mail'] ? $data['mail'] : '';
		$user['first_name'] = $data['givenName'] ? $data['givenName'] : '';
		$user['last_name'] = $data['sn'] ? $data['sn'] : '';
		$user['name'] = $data['cn'] ? $data['cn'] : '';
	}

	/**
	 * Enregistre un utilisateur front dans la BDD
	 *
	 * @param $user
	 * @return void
	 */
	protected function writeFrontendUserInDatabase($user) {
		$fieldsName = ['username', 'last_name', 'first_name', 'name', 'email', 'usergroup', 'pid'];
		foreach ($fieldsName as $fieldKey) {
			$fieldsValues[$fieldKey] = $user[$fieldKey];
		}
		// Timestamp de connexion
		$fieldsValues['tstamp'] = time();

		if ($user['uid']) {
			$where = 'uid = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($user['uid'], 'fe_users');
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery('fe_users', $where, $fieldsValues);
		} else {
			// Timestamp de création du compte
			$fieldsValues['crdate'] = time();
			$GLOBALS['TYPO3_DB']->exec_INSERTquery('fe_users', $fieldsValues);
		}
	}

	/**
	 * @param array $uidList
	 *
	 * @return array
	 */
	protected function getValidGroupUidListForUidList($uidList) {
		$results = [];

		$selectFields = '*';
		$fromTable = 'fe_groups';
		$whereClause = ' uid IN (' . implode(',', $GLOBALS['TYPO3_DB']->fullQuoteArray($uidList, 'fe_groups')) . ')';
		$groupBy = '';
		$orderBy = '';
		$limit = '';

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($selectFields, $fromTable, $whereClause, $groupBy, $orderBy, $limit);
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$results[] = $row['uid'];
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($res);

		return implode(',', $results);
	}
}
