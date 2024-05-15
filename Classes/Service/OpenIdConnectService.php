<?php

declare(strict_types=1);

namespace Causal\Oidc\Service;

use Causal\Oidc\AuthenticationContext;
use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class OpenIdConnectService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected OAuthService $OAuthService;

    protected ?AuthenticationContext $authContext = null;

    /**
     * Global extension configuration
     */
    protected array $config;

    public function __construct(OAuthService $OAuthService, array $config = [])
    {
        $this->OAuthService = $OAuthService;
        $this->config = $config ?: GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('oidc') ?? [];
    }

    public function generateAuthenticationContext(ServerRequest $request, array $authorizationUrlOptions = []): AuthenticationContext
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

        if (!$this->authContext || $this->authContext->requestId !== $requestId) {
            $codeVerifier = null;
            if ($this->config['enableCodeVerifier']) {
                $codeVerifier = $this->generateCodeVerifier();
                $codeChallenge = $this->convertVerifierToChallenge($codeVerifier);
                $authorizationUrlOptions = array_merge($authorizationUrlOptions, $this->getCodeChallengeOptions($codeChallenge));
            }

            $redirectUrl = $request->getParsedBody()['redirect_url'] ?? $request->getQueryParams()['redirect_url'] ?? '';

            $this->authContext = $this->prepareAuthorizationContext($authorizationUrlOptions);
            $this->authContext->requestId = $requestId;
            $this->authContext->redirectUrl = $redirectUrl;
            $this->authContext->codeVerifier = $codeVerifier;
        } else {
            $this->logger->debug('Reusing same authorization URL and state');
        }
        return $this->authContext;
    }

    public function setAuthenticationContext(AuthenticationContext $authContext)
    {
        $this->authContext = $authContext;
    }

    public function getAuthenticationContext(): ?AuthenticationContext
    {
        return $this->authContext;
    }

    public function getFinalLoginUrl(string $code): Uri
    {
        $loginUrlParams = [
            'logintype' => 'login',
            'tx_oidc' => ['code' => $code],
        ];
        if ($this->authContext->redirectUrl && strpos($this->authContext->loginUrl, 'redirect_url=') === false) {
            $loginUrlParams['redirect_url'] = $this->authContext->redirectUrl;
        }
        $loginUrl = new Uri($this->authContext->loginUrl);

        $query = $loginUrl->getQuery() . GeneralUtility::implodeArrayForUrl('', $loginUrlParams);

        return $loginUrl->withQuery(ltrim($query, '&'));
    }

    /**
     * Prepares the authorization URL and corresponding expected state (to mitigate CSRF attack)
     */
    protected function prepareAuthorizationContext(array $authorizationUrlOptions): AuthenticationContext
    {
        $this->OAuthService->setSettings($this->config);

        $authorizationUrl = $this->OAuthService->getAuthorizationUrl($authorizationUrlOptions);
        $state = $this->OAuthService->getState();

        $this->logger->debug('Generating authorization URL', [
            'url' => $authorizationUrl,
            'state' => $state,
        ]);

        return new AuthenticationContext($state, (string)$this->getLoginUrlForContext(), $authorizationUrl);
    }

    protected function getLoginUrlForContext(): Uri
    {
        $loginUrl = new Uri(GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL'));

        // filter query string
        $queryParts = array_filter(explode('&', $loginUrl->getQuery()), function ($k) {
            return $k !== 'logintype' && $k !== 'tx_oidc[code]';
        }, ARRAY_FILTER_USE_KEY);

        return $loginUrl->withQuery(implode('&', $queryParts));
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
