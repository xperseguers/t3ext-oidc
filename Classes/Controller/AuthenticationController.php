<?php
/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Causal\Oidc\Controller;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class AuthenticationController
 *
 * @package Causal\Oidc\Controller
 */
class AuthenticationController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{

    /**
     * Initiates the silent authentication action.
     *
     * @return void
     */
    public function connectAction()
    {
        if ((empty($_GET['state']) || empty($_GET['code']))) {
            throw new \RuntimeException('No state or code detected', 1487001047);
        }

        if (session_id() === '') {
            session_start();
        }

        if ($_GET['state'] !== $_SESSION['oidc_state']) {
            throw new \RuntimeException('Invalid state', 1489658206);
        }

        $loginUrl = $_SESSION['oidc_login_url'];
        $loginUrl .= strpos($loginUrl, '?') !== false ? '&' : '?';
        $loginUrl .= 'logintype=login&tx_oidc[code]=' . $_GET['code'];

        $this->redirectToUri($loginUrl);
    }

}
