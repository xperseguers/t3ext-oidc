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
     * @var array
     */
    protected $globalSettings;

    /**
     * Initializes the controller before invoking an action method.
     *
     * @return void
     */
    public function initializeAction()
    {
        $typo3Branch = class_exists(\TYPO3\CMS\Core\Information\Typo3Version::class)
            ? (new \TYPO3\CMS\Core\Information\Typo3Version())->getBranch()
            : TYPO3_branch;
        if (version_compare($typo3Branch, '9.0', '<')) {
            $this->globalSettings = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['oidc']);
        } else {
            $this->globalSettings = $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['oidc'] ?? [];
        }
    }

    /**
     * Initiates the silent authentication action.
     *
     * @return void
     */
    public function connectAction()
    {
        static::getLogger()->debug('Initiating the silent authentication');
        if ((empty($_GET['state']) || empty($_GET['code']))) {
            static::getLogger()->error('No state or code detected', ['GET' => $_GET]);
            throw new \RuntimeException('No state or code detected', 1487001047);
        }

        if (session_id() === '') {
            static::getLogger()->debug('No PHP session found');
            session_start();
        }
        static::getLogger()->debug('PHP session is available', [
            'id' => session_id(),
            'data' => $_SESSION,
        ]);

        if ($_GET['state'] !== ($_SESSION['oidc_state'] ?? null)) {
            static::getLogger()->error('Invalid returning state detected', [
                'expected' => $_SESSION['oidc_state'] ?? null,
                'actual' => $_GET['state'],
            ]);
            if (!(bool)$this->globalSettings['oidcDisableCSRFProtection']) {
                throw new \RuntimeException('Invalid state', 1489658206);
            }
            static::getLogger()->warning('Bypassing CSRF attack mitigation protection according to the extension configuration');
        }

        $loginUrl = $_SESSION['oidc_login_url'];
        $loginUrl .= strpos($loginUrl, '?') !== false ? '&' : '?';
        $loginUrl .= 'logintype=login&tx_oidc[code]=' . $_GET['code'];
        if (!empty($_SESSION['oidc_redirect_url']) && strpos($loginUrl, 'redirect_url=') === false) {
            $loginUrl .= '&redirect_url=' . urlencode($_SESSION['oidc_redirect_url']);
        }

        static::getLogger()->info('Redirecting to login URL', ['url' => $loginUrl]);
        $this->redirectToUri($loginUrl);
    }

    /**
     * Returns a logger.
     *
     * @return \TYPO3\CMS\Core\Log\Logger
     */
    protected static function getLogger()
    {
        /** @var \TYPO3\CMS\Core\Log\Logger $logger */
        static $logger = null;
        if ($logger === null) {
            $logger = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Log\LogManager::class)->getLogger(__CLASS__);
        }

        return $logger;
    }

}
