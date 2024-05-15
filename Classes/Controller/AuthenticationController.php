<?php

declare(strict_types=1);

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

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use RuntimeException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Class AuthenticationController
 *
 * @package Causal\Oidc\Controller
 */
class AuthenticationController extends ActionController implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected array $globalSettings = [];

    /**
     * Initializes the controller before invoking an action method.
     *
     * @return void
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function initializeAction()
    {
        $this->globalSettings = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('oidc') ?? [];
    }

    /**
     * Initiates the silent authentication action.
     */
    public function connectAction(): ResponseInterface
    {
        $this->logger->debug('Initiating the silent authentication');
        if ((empty($_GET['state']) || empty($_GET['code']))) {
            $this->logger->error('No state or code detected', ['GET' => $_GET]);
            throw new RuntimeException('No state or code detected', 1487001047);
        }

        if (session_id() === '') {
            $this->logger->debug('No PHP session found');
            session_start();
        }
        $this->logger->debug('PHP session is available', [
            'id' => session_id(),
            'data' => $_SESSION,
        ]);

        if ($_GET['state'] !== ($_SESSION['oidc_state'] ?? null)) {
            $this->logger->error('Invalid returning state detected', [
                'expected' => $_SESSION['oidc_state'] ?? null,
                'actual' => $_GET['state'],
            ]);
            if (!$this->globalSettings['oidcDisableCSRFProtection']) {
                throw new RuntimeException('Invalid state', 1489658206);
            }
            $this->logger->warning('Bypassing CSRF attack mitigation protection according to the extension configuration');
        }

        $loginUrl = $_SESSION['oidc_login_url'];
        $loginUrl .= strpos($loginUrl, '?') !== false ? '&' : '?';
        $loginUrl .= 'logintype=login&tx_oidc[code]=' . $_GET['code'];
        if (!empty($_SESSION['oidc_redirect_url']) && strpos($loginUrl, 'redirect_url=') === false) {
            $loginUrl .= '&redirect_url=' . urlencode($_SESSION['oidc_redirect_url']);
        }

        $this->logger->info('Redirecting to login URL', ['url' => $loginUrl]);
        return new RedirectResponse($this->addBaseUriIfNecessary($loginUrl), 303);
    }
}
