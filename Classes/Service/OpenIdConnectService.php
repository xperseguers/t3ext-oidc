<?php

declare(strict_types=1);

namespace Causal\Oidc\Service;

use Causal\Oidc\AuthenticationContext;
use Causal\Oidc\OidcConfiguration;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class OpenIdConnectService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        protected OAuthService $OAuthService,
        protected AuthenticationContextService $authenticationContextService,
        protected OidcConfiguration $config
    ) {}

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

            $query = GeneralUtility::implodeArrayForUrl('', [
                'login_url' => $loginUrl,
                'redirect_url' => $redirectUrl,
                'validation_hash' => $this->calculateUrlHash($loginUrl . $redirectUrl),
            ]);

            $language = $request->getAttribute('language', $request->getAttribute('site')->getDefaultLanguage());
            return $language->getBase()
                ->withPath($this->getAuthenticationUrlRoutePath($language))
                ->withQuery($query);
        }
        return null;
    }

    /**
     * Generate an authentication context for a given frontend request
     * The login URL has to be provided as login_url query parameter in the
     * given request.
     * A redirect URL may be provided either as part of the login URL or as
     * a separate redirect_url query parameter. If the login URL contains a
     * redirect URL already, the separate redirect_url query parameter will
     * not get evaluated.
     * If the login URL does not contain a redirect_url query parameter and
     * a separate redirect_url is provided within the requet, the redirect
     * URL will be added to the login URL. There will be no cHash though.
     *
     * The login URL and the optional redirect URL need to be signed with a
     * validation hash, provided as the validation_hash parameter of the
     * given request.
     */
    public function generateAuthenticationContext(ServerRequestInterface $request, array $authorizationUrlOptions = []): AuthenticationContext
    {
        if (!$this->config->oidcClientKey
            || !$this->config->oidcClientSecret
            || !$this->config->endpointAuthorize
            || !$this->config->endpointToken
        ) {
            throw new InvalidArgumentException('Missing extension configuration', 1715775147);
        }

        $loginUrl = $request->getQueryParams()['login_url'] ?? '';
        if (!GeneralUtility::isValidUrl($loginUrl)) {
            throw new InvalidArgumentException('Missing or invalid login_url: ' . $loginUrl, 1759845557572);
        }
        $redirectUrl = $request->getQueryParams()['redirect_url'] ?? '';
        $hash = $request->getQueryParams()['validation_hash'] ?? '';

        if ($this->calculateUrlHash($loginUrl . $redirectUrl) !== $hash) {
            throw new InvalidArgumentException('Invalid query string', 1719003567);
        }

        // Add logintype to login URL
        $loginUrlParams = ['logintype' => 'login'];
        if ($redirectUrl != '' && !str_contains($loginUrl, 'redirect_url=')) {
            $loginUrlParams['redirect_url'] = $redirectUrl;
        }
        $loginUrl = \GuzzleHttp\Psr7\Uri::withQueryValues(new Uri($loginUrl), $loginUrlParams)->__toString();

        $authContext = $this->buildAuthenticationContext($request, $authorizationUrlOptions, $loginUrl);
        $this->logger->debug('Generated new Authentication Context', ['authContext' => $authContext]);

        return $authContext;
    }

    public function buildAuthenticationContext(
        ServerRequestInterface $request,
        array $authorizationUrlOptions = [],
        string $loginUrl = '',
    ): AuthenticationContext {
        $requestId = $this->getUniqueId();
        $codeVerifier = null;
        if ($this->config->enableCodeVerifier) {
            $codeVerifier = $this->generateCodeVerifier();
            $codeChallenge = $this->convertVerifierToChallenge($codeVerifier);
            $authorizationUrlOptions = array_merge($authorizationUrlOptions, $this->getCodeChallengeOptions($codeChallenge));
        }

        $authorizationUrl = $this->OAuthService->getAuthorizationUrl($request, $authorizationUrlOptions);
        $state = $this->OAuthService->getState();

        $normalizedParams = $request->getAttribute('normalizedParams');
        $isHttps = $normalizedParams instanceof NormalizedParams && $normalizedParams->isHttps();

        return new AuthenticationContext(
            $state,
            $loginUrl,
            $authorizationUrl,
            $requestId,
            $isHttps,
            $codeVerifier
        );
    }

    public function getAuthorizationRedirect(AuthenticationContext $authContext)
    {
        $url = new Uri($authContext->authorizationUrl);
        $cookie = $this->authenticationContextService->getCookieForAuthenticationContext($authContext);
        return GeneralUtility::makeInstance(RedirectResponse::class, $url)
            ->withAddedHeader('Set-Cookie', (string)$cookie);
    }

    public function getFinalLoginUrl(AuthenticationContext $authenticationContext, string $code): UriInterface
    {
        $loginUrl = new Uri($authenticationContext->loginUrl);
        return \GuzzleHttp\Psr7\Uri::withQueryValue($loginUrl, 'tx_oidc[code]', $code);
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
        return $language->getBase()->getPath() . $this->config->authenticationUrlRoute;
    }

    protected function calculateUrlHash(string $value): string
    {
        if (class_exists(\TYPO3\CMS\Core\Crypto\HashService::class)) {
            // TYPO3 v13
            $calculatedHash = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Crypto\HashService::class)->hmac($value, 'oidc');
        } else {
            // TYPO3 v12
            $calculatedHash = GeneralUtility::hmac($value, 'oidc');
        }
        return $calculatedHash;
    }
}
