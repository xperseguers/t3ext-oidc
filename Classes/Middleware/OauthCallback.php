<?php

declare(strict_types=1);

namespace Causal\Oidc\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class OauthCallback implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * see https://github.com/thephpleague/oauth2-client
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $code = $queryParams['code'] ?? '';
        if (!$code) {
            return $handler->handle($request);
        }

        // A code was supplied, we start the OIDC handling

        $state = $queryParams['state'] ?? '';
        if (!$state) {
            return (new Response())->withStatus(400, 'Invalid state');
        }
        $this->logger->debug('Initiating the silent authentication');

        if (session_id() === '') {
            $this->logger->debug('No PHP session found');
            session_start();
        }
        $this->logger->debug('PHP session is available', [
            'id' => session_id(),
            'data' => $_SESSION,
        ]);

        $stateFromSession = $_SESSION['oidc_state'] ?? null;
        $loginUrl = $_SESSION['oidc_login_url'] ?? '';
        $redirectUrlFromSession = $_SESSION['oidc_redirect_url'] ?? '';

        if ($state !== $stateFromSession) {
            $globalSettings = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('oidc') ?? [];
            if (!$globalSettings['oidcDisableCSRFProtection']) {
                $this->logger->error('Invalid returning state detected', [
                    'expected' => $stateFromSession,
                    'actual' => $state,
                ]);
                return (new Response())->withStatus(400, 'Invalid state');
            }
            $this->logger->info('State mismatch. Bypassing CSRF attack mitigation protection according to the extension configuration', [
                'expected' => $stateFromSession,
                'actual' => $state,
            ]);
        }

        $loginUrlParams = [
            'logintype' => 'login',
            'tx_oidc' => ['code' => $code],
        ];
        if ($redirectUrlFromSession && strpos($loginUrl, 'redirect_url=') === false) {
            $loginUrlParams['redirect_url'] = $redirectUrlFromSession;
        }

        $loginUrl .= strpos($loginUrl, '?') !== false ? '&' : '?';
        $loginUrl .= ltrim(GeneralUtility::implodeArrayForUrl('', $loginUrlParams), '&');

        $this->logger->info('Redirecting to login URL', ['url' => $loginUrl]);

        return new RedirectResponse(GeneralUtility::locationHeaderUrl($loginUrl), 303);
    }
}
