<?php
namespace Visol\Vifbauth\Service;

use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Core\Utility\ResourceUtility;

require_once(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('vifbauth') . 'Resources/PHP/facebook.php');

class FacebookAuthService extends \TYPO3\CMS\Sv\AbstractAuthenticationService {

	/**
	 * @var \TYPO3\CMS\Core\Authentication\AbstractUserAuthentication
	 */
	public $pObj;

	/**
	 * @var array
	 */
	public $login = array();

	/**
	 * @var array
	 */
	public $authInfo = array();

	/**
	 * @var array
	 */
	public $db_user = array();

	/**
	 * @var array
	 */
	public $db_groups = array();

	/**
	 * @var boolean
	 */
	public $writeAttemptLog = TRUE;

	/**
	 * @var boolean
	 */
	public $writeDevLog = TRUE;

	/**
	 * @var array
	 */
	protected $response;

	// Subtype of the service which is used to call the service.
	public $mode = 'authUserFe';

	/**
	 * @var array
	 */
	protected $extensionConfiguration;

	/**
	 * @var \TYPO3\CMS\Core\Database\DatabaseConnection
	 */
	protected $databaseHandle;

	const LANGUAGE_GERMAN = 1;
	const LANGUAGE_FRENCH = 2;
	const LANGUAGE_ITALIAN = 3;

	/**
	 * CONSTRUCTOR
	 */
	public function __construct() {
		$sysPageObject = GeneralUtility::makeInstance('TYPO3\CMS\Frontend\Page\PageRepository');
		$rootLine = $sysPageObject->getRootLine(1);
		$typoscriptParser = GeneralUtility::makeInstance('TYPO3\CMS\Core\TypoScript\ExtendedTemplateService');
		$typoscriptParser->tt_track = 0;
		$typoscriptParser->init();
		$typoscriptParser->runThroughTemplates($rootLine);
		$typoscriptParser->generateConfig();
		$typoScriptService = GeneralUtility::makeInstance('TYPO3\CMS\Extbase\Service\TypoScriptService');
		$this->extensionConfiguration = $typoScriptService->convertTypoScriptArrayToPlainArray($typoscriptParser->setup['plugin.']['tx_vifbauth.']);
		$this->databaseHandle = $GLOBALS['TYPO3_DB'];
	}

	/**
	 * Inits some variables
	 *
	 * @return	void
	 */
	public function init() {
		return parent::init();
	}

	/**
	 * Initializes authentication for this service.
	 *
	 * @param string $mode: Subtype for authentication (either "getUserFE" or "getUserBE")
	 * @param array $loginData: Login data submitted by user and preprocessed by AbstractUserAuthentication
	 * @param array $authInfo: Additional TYPO3 information for authentication services (unused here)
	 * @param \TYPO3\CMS\Core\Authentication\AbstractUserAuthentication $pObj Calling object
	 * @return void
	 */
	public function initAuth($mode, array $loginData, array $authInfo, \TYPO3\CMS\Core\Authentication\AbstractUserAuthentication $pObj) {
		if (defined('TYPO3_cliMode')) {
			return parent::initAuth($mode, $loginData, $authInfo, $pObj);
		}
		if (array_key_exists('pass', $_POST)) {
			return parent::initAuth($mode, $loginData, $authInfo, $pObj);
		}
		// Store login and authentication data
		$this->loginData = $loginData;
		$this->authInfo = $authInfo;
		$this->pObj = &$pObj;
	}

	/**
	 * @param array $user
	 * @return array
	 */
	public function authUser(array $user) {
		$result = 100;

		if (!array_key_exists('pass', GeneralUtility::_POST()) || !empty($user)) {
			if ($this->isFacebookLogin()) {
				$result = 200;
			}
		}
		return $result;
	}

	public function isFacebookLogin() {
		$facebookConfiguration = array(
			'appId'  => $this->extensionConfiguration['settings']['facebookAppId'],
			'secret' => $this->extensionConfiguration['settings']['facebookAppSecret'],
		);
		/** @var \Facebook $facebook */
		$facebook = GeneralUtility::makeInstance('\Facebook', $facebookConfiguration);
		return $facebook->getUser() > 0 ? TRUE : FALSE;
	}

	/**
	 * @return array User Array or FALSE
	 */
	public function getUser() {
		$user = FALSE;
		if (array_key_exists('pass', $_POST)) {
			// a password was submitted, so this is no Facebook login
			return $user;
		}

		$facebookConfiguration = array(
			'appId'  => $this->extensionConfiguration['settings']['facebookAppId'],
			'secret' => $this->extensionConfiguration['settings']['facebookAppSecret'],
		);
		/** @var \Facebook $facebook */
		$facebook = GeneralUtility::makeInstance('\Facebook', $facebookConfiguration);
		$facebook->destroySession();
		if (array_key_exists('token', GeneralUtility::_GET())) {
			$facebook->setAccessToken(GeneralUtility::_GET('token'));
		}
		$facebookUserId = $facebook->getUser();

		if ($facebookUserId > 0) {
			$facebookUserInformation = $facebook->api('/' . $facebookUserId);
			//\TYPO3\CMS\Core\Utility\DebugUtility::debug($facebookUserInformation);
//			die();
			// we have an authenticated Facebook user
			$user = $this->getFrontendUser($facebookUserId);
			if (!is_array($user) || empty($user)) {
				$this->createFrontendUser($facebookUserInformation);
				// TODO check for existing mobilizeCommunityUser and remove

			} else {
				$this->updateFrontendUser($user['uid'], $facebookUserInformation);
			}
			$user = $this->getFrontendUser($facebookUserId);
		}

		return $user;
	}

	/**
	 * Get a frontend user
	 *
	 * @param $userId
	 * @return array
	 */
	protected function getFrontendUser($userId) {
		//$this->databaseHandle->store_lastBuiltQuery = TRUE;
		$row = $this->databaseHandle->exec_SELECTgetSingleRow('*', 'fe_users', 'disable=0 AND deleted=0 AND pid=' . $this->extensionConfiguration['persistence']['storagePid'] . ' AND username=\'' . $userId . '\'');
		return $row;

	}

	/**
	 * @param $userInformation
	 */
	protected function createFrontendUser($userInformation) {
		$user = $this->getUserDataArrayForDataHandler($userInformation);
		$user['crdate'] = time();
		$user['pid'] = $this->extensionConfiguration['persistence']['storagePid'];
		$user['usergroup'] = (string)$this->extensionConfiguration['settings']['defaultFrontendUserGroupUid'];

		if (array_key_exists('birthday', $userInformation) && !empty($userInformation['birthday'])) {
			$user['birthdate'] = \DateTime::createFromFormat('m/d/Y', $userInformation['birthday'])->getTimestamp();
		}
		$user['password'] = md5(GeneralUtility::shortMD5(uniqid(rand(), TRUE)));
		if (array_key_exists('email', $userInformation) && !empty($userInformation['email'])) {
			$user['email'] = $userInformation['email'];
		} else {
			$user['email'] = '';
		}
		$user['notification_mail_active'] = 1;

		// generate auth token for community user
		$user['auth_token'] = \Visol\Easyvote\Utility\Algorithms::generateRandomToken(20);

		$this->databaseHandle->exec_INSERTquery('fe_users', $user);
	}

	/**
	 * Update an existing FE user
	 *
	 * @param $userId
	 * @param $userInformation
	 * @return void
	 */
	protected function updateFrontendUser($userId, $userInformation) {
		$where = "uid = " . $userId;
		$user = $this->getUserDataArrayForDataHandler($userInformation);
		$this->databaseHandle->exec_UPDATEquery('fe_users', $where, $user);
	}

	/**
	 * Get the data that is updated on every login
	 *
	 * @param array $userInformation
	 * @return array
	 */
	protected function getUserDataArrayForDataHandler($userInformation) {
		$userLanguage = FacebookAuthService::LANGUAGE_GERMAN;
		if (GeneralUtility::isFirstPartOfStr($userInformation['locale'], 'fr')) {
			$userLanguage = FacebookAuthService::LANGUAGE_FRENCH;
		}
		if (GeneralUtility::isFirstPartOfStr($userInformation['locale'], 'it')) {
			$userLanguage = FacebookAuthService::LANGUAGE_ITALIAN;
		}

		$user = array(
			'tstamp' => time(),
			'username' => $userInformation['id'],
			'gender' => $userInformation['gender'] === 'male' ? 1 : 2,
			'first_name' => $userInformation['first_name'],
			'last_name' => $userInformation['last_name'],
			'tx_extbase_type' => 'Tx_Easyvote_CommunityUser',
			'user_language' => $userLanguage,
		);

		return $user;
	}

}
?>