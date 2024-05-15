<?php

declare(strict_types=1);

namespace Causal\Oidc\Service;

use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class OpenIdConnectService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected OAuthService $OAuthService;

    /**
     * Global extension configuration
     */
    protected array $config;

    public function __construct(OAuthService $OAuthService)
    {
        $this->OAuthService = $OAuthService;
        $this->config = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('oidc') ?? [];
    }

    public function generateOpenidConnectUri(array $authorizationUrlOptions = []): string
    {
        if (empty($this->config['oidcClientKey'])
            || empty($this->config['oidcClientSecret'])
            || empty($this->config['oidcEndpointAuthorize'])
            || empty($this->config['oidcEndpointToken'])
        ) {
            throw new InvalidArgumentException('Missing extension configuration', 1715775147);
        }

        $requestId = $this->getUniqueId();

        $this->logger->debug('Generating OpenID Connect URI', ['request' => $requestId]);

        if (session_id() === '') { // If no session exists, start a new one
            $this->logger->debug('No PHP session found');
            session_start();
        }

        if (empty($_SESSION['requestId']) || $_SESSION['requestId'] !== $requestId) {
            $codeVerifier = null;
            if ($this->config['enableCodeVerifier']) {
                $codeVerifier = $this->generateCodeVerifier();
                $codeChallenge = $this->convertVerifierToChallenge($codeVerifier);
                $authorizationUrlOptions = array_merge($authorizationUrlOptions, $this->getCodeChallengeOptions($codeChallenge));
            }

            $request = $GLOBALS['TYPO3_REQUEST'];
            $redirectUrl = $request->getParsedBody()['redirect_url'] ?? $request->getQueryParams()['redirect_url'] ?? '';
            $data = $this->prepareAuthorizationUrl($authorizationUrlOptions);

            $_SESSION['oidc_state'] = $data['state'];
            $_SESSION['oidc_login_url'] = $data['login_url'];
            $_SESSION['oidc_authorization_url'] = $data['authorization_url'];
            $_SESSION['requestId'] = $requestId;
            $_SESSION['oidc_redirect_url'] = $redirectUrl;
            $_SESSION['oidc_code_verifier'] = $codeVerifier;

            $this->logger->debug('PHP session is available', [
                'id' => session_id(),
                'data' => $_SESSION,
            ]);
        } else {
            $this->logger->debug('Reusing same authorization URL and state');
        }
        return $_SESSION['oidc_authorization_url'] ?? '';
    }

    /**
     * Prepares the authorization URL and corresponding expected state (to mitigate CSRF attack)
     * and stores information into the session.
     */
    protected function prepareAuthorizationUrl(array $authorizationUrlOptions): array
    {
        $this->OAuthService->setSettings($this->config);

        $authorizationUrl = $this->OAuthService->getAuthorizationUrl($authorizationUrlOptions);
        $state = $this->OAuthService->getState();

        $this->logger->debug('Generating authorization URL', [
            'url' => $authorizationUrl,
            'state' => $state,
        ]);

        $loginUrl = new Uri(GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL'));

        // filter query string
        $queryParts = array_filter(explode('&', $loginUrl->getQuery()), function ($k) {
            return $k !== 'logintype' && $k !== 'tx_oidc[code]';
        }, ARRAY_FILTER_USE_KEY);
        $loginUrl = $loginUrl->withQuery(implode('&', $queryParts));

        return [
            'state' => $state,
            'login_url' => (string)$loginUrl,
            'authorization_url' => $authorizationUrl
        ];
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

    protected function generateCodeVerifier(): string
    {
        return bin2hex(random_bytes(64));
    }

    protected function convertVerifierToChallenge($codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }

    protected function getCodeChallengeOptions($codeChallenge): array
    {
        return [
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];
    }
}
