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

declare(strict_types=1);

namespace Causal\Oidc\EventListener;

use Causal\Oidc\Service\OAuthService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\FrontendLogin\Event\ModifyLoginFormViewEvent;

class FrontendLoginEventListener implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function modifyLoginFormView(ModifyLoginFormViewEvent $event): void
    {
        $settings = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('oidc') ?? [];

        if (empty($settings['oidcClientKey'])
            || empty($settings['oidcClientSecret'])
            || empty($settings['oidcEndpointAuthorize'])
            || empty($settings['oidcEndpointToken'])
        ) {
            return;
        }

        $requestId = $this->getUniqueId();
        $this->logger->debug('Post-processing felogin form', ['request' => $requestId]);

        if (session_id() === '') { // If no session exists, start a new one
            $this->logger->debug('No PHP session found');
            session_start();
        }

        if (empty($_SESSION['requestId']) || $_SESSION['requestId'] !== $requestId) {
            $this->prepareAuthorizationUrl($settings);
            $_SESSION['requestId'] = $requestId;
            $_SESSION['oidc_redirect_url'] = GeneralUtility::_GP('redirect_url');

            $this->logger->debug('PHP session is available', [
                'id' => session_id(),
                'data' => $_SESSION,
            ]);
        } else {
            $this->logger->debug('Reusing same authorization URL and state');
        }

        $event->getView()->assign('openidConnectUri', $_SESSION['oidc_authorization_url']);
    }

    /**
     * Prepares the authorization URL and corresponding expected state (to mitigate CSRF attack)
     * and stores information into the session.
     *
     * @param array $settings
     */
    protected function prepareAuthorizationUrl(array $settings): void
    {
        /** @var OAuthService $service */
        $service = GeneralUtility::makeInstance(OAuthService::class);
        $service->setSettings($settings);
        $authorizationUrl = $service->getAuthorizationUrl();

        // Store the state
        $state = $service->getState();

        $this->logger->debug('Generating authorization URL', [
            'url' => $authorizationUrl,
            'state' => $state,
        ]);

        $loginUrl = GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL');
        // Sanitize the URL
        $parts = parse_url($loginUrl);
        $queryParts = array_filter(explode('&', $parts['query'] ?? ''), function ($v) {
            list ($k,) = explode('=', $v, 2);

            return !in_array($k, ['logintype', 'tx_oidc[code]']);
        });
        $parts['query'] = implode('&', $queryParts);
        $loginUrl = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port']) && !in_array((int)$parts['port'], [80, 443], true)) {
            $loginUrl .= ':' . $parts['port'];
        }
        $loginUrl .= $parts['path'];
        if (!empty($parts['query'])) {
            $loginUrl .= '?' . $parts['query'];
        }

        $_SESSION['oidc_state'] = $state;
        $_SESSION['oidc_login_url'] = $loginUrl;
        $_SESSION['oidc_authorization_url'] = $authorizationUrl;
    }

    /**
     * Returns a unique ID for the current processed request.
     *
     * This is supposed to be independent of the actual web server (Nginx or Apache) and
     * the way PHP was built and unique enough for our use case, as opposed to using:
     *
     * - zend_thread_id() which requires PHP to be built with Zend Thread Safety - ZTS - support and debug mode
     * - apache_getenv('UNIQUE_ID') which requires Apache as web server and mod_unique_id
     *
     * @return string
     */
    protected function getUniqueId(): string
    {
        return sprintf('%08x', abs(crc32($_SERVER['REMOTE_ADDR'] . $_SERVER['REQUEST_TIME'] . $_SERVER['REMOTE_PORT'])));
    }
}
