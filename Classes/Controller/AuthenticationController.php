<?php
namespace Visol\Vifbauth\Controller;

use TYPO3\CMS\Core\Utility\GeneralUtility;

require_once(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('vifbauth') . 'Resources/PHP/facebook.php');

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2014 Lorenz Ulrich <lorenz.ulrich@visol.ch>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Abstract base controller for the StaticInfoTables extension
 */
class AuthenticationController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController {

	/**
	 * Empty login that always returns false
	 * This is the default action for the controller and it enables us to embed to Auth
	 * plugin to every page to accept logins from every page
	 *
	 * @return bool
	 */
	public function acceptLoginAction() {
		return FALSE;
	}

	/**
	 * action login
	 *
	 * @param null|integer $redirectPageUid
	 */
	public function loginAction($redirectPageUid = NULL) {
		$facebookConfiguration = array(
			'appId'  => $this->settings['facebookAppId'],
			'secret' => $this->settings['facebookAppSecret'],
		);
		/** @var \Facebook $facebook */
		$facebook = GeneralUtility::makeInstance('\Facebook', $facebookConfiguration);

		$redirectUri = $this->uriBuilder->setTargetPageUid($redirectPageUid)->setArguments(array('logintype' => 'login'))->setAbsoluteUriScheme('https')->setCreateAbsoluteUri(TRUE)->setUseCacheHash(FALSE)->build();
		$loginUrlParameters = array(
			'redirect_uri' => $redirectUri,
			'scope' => 'basic_info,email,user_birthday'
		);
		$loginUrl = $facebook->getLoginUrl($loginUrlParameters);
		$this->redirectToUri($loginUrl);
	}

	/**
	 * @param string $redirectUri
	 */
	public function revokePermissionsAction($redirectUri) {
		$facebookConfiguration = array(
			'appId'  => $this->settings['facebookAppId'],
			'secret' => $this->settings['facebookAppSecret'],
		);
		/** @var \Facebook $facebook */
		$facebook = GeneralUtility::makeInstance('\Facebook', $facebookConfiguration);
		$facebookUserId = $facebook->getUser();

		if ($facebookUserId > 0) {
			$response = $facebook->api(
				"/me/permissions",
				"DELETE"
			);
		}
		$this->redirectToUri($redirectUri);
	}

}

?>
