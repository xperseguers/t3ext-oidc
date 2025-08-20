<?php

declare(strict_types=1);

namespace Causal\Oidc\Service;

use Causal\Oidc\AuthenticationContext;
use Causal\Oidc\Http\CookieService;
use Causal\Oidc\LoginProvider\OidcLoginProvider;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
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
    protected CookieService $cookieService;

    public function __construct(OAuthService $OAuthService, CookieService $cookieService, array $config = [])
    {
        $this->OAuthService = $OAuthService;
        $this->cookieService = $cookieService;
        $this->config = $config ?: GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('oidc') ?? [];
    }

    public function isAuthenticationRequest(ServerRequestInterface $request): bool
    {
        /** @var SiteLanguage $language */
        $language = $request->getAttribute('language');
        return $language && $request->getUri()->getPath() === $this->getAuthenticationUrlRoutePath($language);
    }

    public function getAuthenticationRequestUrl(): ?UriInterface
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($request) {
            $loginUrl = GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL');
            $redirectUrl = $request->getParsedBody()['redirect_url'] ?? $request->getQueryParams()['redirect_url'] ?? '';

            // TYPO3 v13
            if (class_exists(\TYPO3\CMS\Core\Crypto\HashService::class)) {
                $hash = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Crypto\HashService::class)->hmac($loginUrl . $redirectUrl, 'oidc');
            } else {
                $hash = GeneralUtility::hmac($loginUrl . $redirectUrl, 'oidc');
            }

            $query = GeneralUtility::implodeArrayForUrl('', [
                'login_url' => $loginUrl,
                'redirect_url' => $redirectUrl,
                'validation_hash' => $hash,
            ]);

            $language = $request->getAttribute('language', $request->getAttribute('site')->getDefaultLanguage());
            return $language->getBase()
                ->withPath($this->getAuthenticationUrlRoutePath($language))
                ->withQuery($query);
        }
        return null;
    }

    public function getAuthorizationResponseRedirect(ServerRequestInterface $request): RedirectResponse
    {
        $cookie = $this->cookieService->getCookieForAuthenticationContext(
            $authContext = $this->buildAuthenticationContext($request, [], (string)$request->getUri()),
            $request->getAttribute('normalizedParams')->isHttps(),
            '',
        );

        return GeneralUtility::makeInstance(RedirectResponse::class, $authContext->getAuthorizationUrl())
                             ->withAddedHeader('Set-Cookie', (string)$cookie);
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
        // TYPO3 v13
        if (class_exists(\TYPO3\CMS\Core\Crypto\HashService::class)) {
            $calculatedHash = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Crypto\HashService::class)->hmac($loginUrl . $redirectUrl, 'oidc');
        } else {
            $calculatedHash = GeneralUtility::hmac($loginUrl . $redirectUrl, 'oidc');
        }
        if (($loginUrl || $redirectUrl) && $calculatedHash !== $hash) {
            throw new InvalidArgumentException('Invalid query string', 1719003567);
        }

        return $this->authContext = $this->buildAuthenticationContext($request, $authorizationUrlOptions, $loginUrl, $redirectUrl);
    }

    public function buildAuthenticationContext(ServerRequestInterface $request, array $authorizationUrlOptions = [], string $loginUrl = '', string $redirectUrl = ''): AuthenticationContext
    {
        $requestId = $this->getUniqueId();
        $codeVerifier = null;
        if ($this->config['enableCodeVerifier']) {
            $codeVerifier = $this->generateCodeVerifier();
            $codeChallenge = $this->convertVerifierToChallenge($codeVerifier);
            $authorizationUrlOptions = array_merge($authorizationUrlOptions, $this->getCodeChallengeOptions($codeChallenge));
        }

        $this->OAuthService->setSettings($this->config);

        $authorizationUrl = $this->OAuthService->getAuthorizationUrl($request, $authorizationUrlOptions);

        $authContext = new AuthenticationContext(
            $this->OAuthService->getState(),
            (string)$this->getLoginUrlForContext($loginUrl),
            $authorizationUrl,
            $requestId,
            $redirectUrl,
            $codeVerifier
        );

        $this->logger->debug('Generated new Authentication Context', ['authContext' => $authContext]);

        return $authContext;
    }

    public function setAuthenticationContext(AuthenticationContext $authContext): void
    {
        $this->authContext = $authContext;
    }

    public function getAuthenticationContext(): ?AuthenticationContext
    {
        return $this->authContext;
    }

    public function getFinalLoginUrl(string $code): Uri
    {
        $loginUrl = new Uri($this->authContext->getLoginUrl());

        $loginUrlParams = ['logintype' => 'login'];
        if ($loginUrl->getPath() === '/typo3/login') {
            $loginUrlParams = [
                'login_status' => 'login',
                'loginProvider' => OidcLoginProvider::IDENTIFIER,
            ];
        }

        $loginUrlParams['tx_oidc'] = ['code' => $code];

        if ($this->authContext->redirectUrl && !str_contains($this->authContext->getLoginUrl(), 'redirect_url=')) {
            $loginUrlParams['redirect_url'] = $this->authContext->redirectUrl;
        }

        $query = $loginUrl->getQuery() . GeneralUtility::implodeArrayForUrl('', $loginUrlParams);

        return $loginUrl->withQuery(ltrim($query, '&'));
    }

    protected function getLoginUrlForContext(string $loginUrl): Uri
    {
        $loginUrl = new Uri($loginUrl);

        // filter query string
        $queryParts = array_filter(explode('&', $loginUrl->getQuery()), function ($param) {
            return !str_starts_with($param, 'logintype') && !str_starts_with($param, 'login_status') && !str_starts_with($param, 'loginProvider') && !str_starts_with($param, 'tx_oidc%5Bcode%5D') && !str_starts_with($param, 'cHash');
        });

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

    protected function getAuthenticationUrlRoutePath(SiteLanguage $language): string
    {
        return $language->getBase()->getPath() . ($this->config['authenticationUrlRoute'] ?? 'oidc/authentication');
    }
}
