<?php
namespace Visol\Vifbauth\Controller;

use TYPO3\CMS\Core\Utility\GeneralUtility;

// Composer Autoloader
require_once(PATH_site . 'Packages/Libraries/autoload.php');

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
 * Abstract base controller for the vifbauth extension
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
		$fb = new \Facebook\Facebook([
			'app_id' => $this->settings['facebookAppId'],
			'app_secret' => $this->settings['facebookAppSecret'],
		]);

		$helper = $fb->getRedirectLoginHelper();

		$permissions = ['email', 'basic_info', 'user_birthday'];
		$redirectUri = $this->uriBuilder->setTargetPageUid($redirectPageUid)->setArguments(array('logintype' => 'login'))->setAbsoluteUriScheme('http')->setCreateAbsoluteUri(TRUE)->setUseCacheHash(FALSE)->build();
		$loginUrl = $helper->getLoginUrl($redirectUri, $permissions);

		$this->redirectToUri($loginUrl);
	}

	/**
	 * @param string $redirectUri
	 */
	public function revokePermissionsAction($redirectUri) {
		$fb = new \Facebook\Facebook([
			'app_id' => $this->settings['facebookAppId'],
			'app_secret' => $this->settings['facebookAppSecret'],
		]);

		$helper = $fb->getRedirectLoginHelper();
		// http://stackoverflow.com/a/37693105/1517316
		$_SESSION['FBRLH_state'] = $_GET['state'];
		$accessToken = $helper->getAccessToken();

		$response = $fb->delete('/me/permissions', $accessToken);
		$this->redirectToUri($redirectUri);
	}

}

?>
