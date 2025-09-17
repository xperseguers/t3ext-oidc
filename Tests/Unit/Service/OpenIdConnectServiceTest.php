<?php

declare(strict_types=1);

namespace Causal\Oidc\Tests\Unit\Service;

use Causal\Oidc\AuthenticationContext;
use Causal\Oidc\Service\OAuthService;
use Causal\Oidc\Service\OpenIdConnectService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class OpenIdConnectServiceTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('getFinalLoginUrlReturnsExpectedUrlDataProvider')]
    public function getFinalLoginUrlReturnsExpectedUrl(string $loginUrl, string $expected): void
    {
        $oauthService = new OAuthService(self::createStub(EventDispatcher::class));
        $service = new OpenIdConnectService($oauthService, ['dummy']);
        $service->setAuthenticationContext(new AuthenticationContext('', $loginUrl, '', '', 'https://example.com/redirect'));
        self::assertSame($expected, (string)$service->getFinalLoginUrl('somecode'));
    }

    public static function getFinalLoginUrlReturnsExpectedUrlDataProvider(): array
    {
        return [
            'default' => [
                'loginUrl' => 'https://example.com/login',
                'expected' => 'https://example.com/login?logintype=login&tx_oidc%5Bcode%5D=somecode&redirect_url=https%3A%2F%2Fexample.com%2Fredirect',
            ],
            'preserves params' => [
                'loginUrl' => 'https://example.com/login?otherparam=foo',
                'expected' => 'https://example.com/login?otherparam=foo&logintype=login&tx_oidc%5Bcode%5D=somecode&redirect_url=https%3A%2F%2Fexample.com%2Fredirect',
            ],
            'preserves redirect_url' => [
                'loginUrl' => 'https://example.com/login?redirect_url=http%3A%2F%2Fexample.com%2Fother',
                'expected' => 'https://example.com/login?redirect_url=http%3A%2F%2Fexample.com%2Fother&logintype=login&tx_oidc%5Bcode%5D=somecode',
            ],
        ];
    }

    public static function loginUrlProvider(): array
    {
        return [
            'removes logintype' => [
                'loginUrl' => 'https://example.com/login?redirect_url=http%3A%2F%2Fexample.com%2Fother&logintype=logout',
                'expected' => 'https://example.com/login?redirect_url=http%3A%2F%2Fexample.com%2Fother',
            ],
            'removes cHash' => [
                'loginUrl' => 'https://example.com/login?redirect_url=http%3A%2F%2Fexample.com%2Fother&cHash=1232',
                'expected' => 'https://example.com/login?redirect_url=http%3A%2F%2Fexample.com%2Fother',
            ],
            'removes oidc code' => [
                'loginUrl' => 'https://example.com/login?redirect_url=http%3A%2F%2Fexample.com%2Fother&tx_oidc[code]=1232',
                'expected' => 'https://example.com/login?redirect_url=http%3A%2F%2Fexample.com%2Fother',
            ],
        ];
    }

    #[Test]
    #[DataProvider('loginUrlProvider')]
    public function cleanLoginUrl(string $loginUrl, string $expected): void
    {
        $service = $this->getAccessibleMock(OpenIdConnectService::class, null, [], '', false);
        $cleanedUrl = $service->_call('getLoginUrlForContext', $loginUrl);
        self::assertSame($expected, (string)$cleanedUrl);
    }
}
