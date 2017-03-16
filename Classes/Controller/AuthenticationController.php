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

class AuthenticationController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{

    public function connectAction()
    {
        if ((empty($_GET['state']) || empty($_GET['code']))) {
            throw new \RuntimeException('No state or code detected', 1487001047);
        }

        $frontendController = $this->getTypoScriptFrontendController();
        $type = $frontendController->loginUser ? 'user' : 'ses';
        $state = $frontendController->fe_user->getKey($type, 'state');

        if ($state !== $_GET['state']) {
            throw new \RuntimeException('Invalid state', 1489658206);
        }

        $loginUrl = $frontendController->fe_user->getKey($type, 'loginUrl');
        $loginUrl .= strpos($loginUrl, '?') !== false ? '&' : '?';
        $loginUrl .= 'logintype=login&tx_oidc[code]=' . $_GET['code'];

        $this->redirectToUri($loginUrl);
    }

    /**
     * @return \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController
     */
    protected function getTypoScriptFrontendController()
    {
        return $GLOBALS['TSFE'];
    }

}
