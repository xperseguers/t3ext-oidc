<?php

declare(strict_types=1);

namespace Causal\Oidc\Service;

use Causal\Oidc\AuthenticationContext;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

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

    public function isAuthenticationRequest(ServerRequestInterface $request): bool
    {
        $language = $request->getAttribute('language');
        return $language && $request->getUri()->getPath() === $language->getBase()->getPath() . $this->config['authenticationUrlRoute'];
    }

    public function getAuthenticationRequestUrl(): ?UriInterface
    {
        /** @var TypoScriptFrontendController $tsfe */
        $tsfe = $GLOBALS['TSFE'] ?? null;
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($tsfe && $request) {
            $loginUrl = GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL');
            $redirectUrl = $request->getParsedBody()['redirect_url'] ?? $request->getQueryParams()['redirect_url'] ?? '';
            $query = GeneralUtility::implodeArrayForUrl('', [
                'login_url' => $loginUrl,
                'redirect_url' => $redirectUrl,
                'validation_hash' => GeneralUtility::hmac($loginUrl . $redirectUrl, 'oidc'),
            ]);
            return $tsfe->getLanguage()->getBase()
                ->withPath($tsfe->getLanguage()->getBase()->getPath() . $this->config['authenticationUrlRoute'])
                ->withQuery($query);
        }
        return null;
    }

    public function generateAuthenticationContext(ServerRequestInterface $request, array $authorizationUrlOptions = []): AuthenticationContext
    {
        if (empty($this->config['oidcClientKey'])
            || empty($this->config['oidcClientSecret'])
            || empty($this->config['oidcEndpointAuthorize'])
            || empty($this->config['oidcEndpointToken'])
        ) {
            throw new InvalidArgumentException('Missing extension configuration', 1715775147);
        }

        $loginUrl = $request->getQueryParams()['login_url'] ?? '';
        $redirectUrl = $request->getQueryParams()['redirect_url'] ?? '';
        $hash = $request->getQueryParams()['validation_hash'] ?? '';
        if (($loginUrl || $redirectUrl) && GeneralUtility::hmac($loginUrl . $redirectUrl, 'oidc') !== $hash) {
            throw new InvalidArgumentException('Invalid query string', 1719003567);
        }

        $requestId = $this->getUniqueId();
        $codeVerifier = null;
        if ($this->config['enableCodeVerifier']) {
            $codeVerifier = $this->generateCodeVerifier();
            $codeChallenge = $this->convertVerifierToChallenge($codeVerifier);
            $authorizationUrlOptions = array_merge($authorizationUrlOptions, $this->getCodeChallengeOptions($codeChallenge));
        }

        $this->OAuthService->setSettings($this->config);

        $authorizationUrl = $this->OAuthService->getAuthorizationUrl($authorizationUrlOptions);
        $state = $this->OAuthService->getState();

        $this->authContext = new AuthenticationContext(
            $state,
            (string)$this->getLoginUrlForContext($loginUrl),
            $authorizationUrl,
            $requestId,
            $redirectUrl,
            $codeVerifier
        );

        $this->logger->debug('Generated new Authentication Context', ['authContext' => $this->authContext]);

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
        if ($this->authContext->redirectUrl && strpos($this->authContext->getLoginUrl(), 'redirect_url=') === false) {
            $loginUrlParams['redirect_url'] = $this->authContext->redirectUrl;
        }
        $loginUrl = new Uri($this->authContext->getLoginUrl());

        $query = $loginUrl->getQuery() . GeneralUtility::implodeArrayForUrl('', $loginUrlParams);

        return $loginUrl->withQuery(ltrim($query, '&'));
    }

    protected function getLoginUrlForContext(string $loginUrl): Uri
    {
        $loginUrl = new Uri($loginUrl);

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
