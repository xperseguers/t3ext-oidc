<?php

declare(strict_types=1);

namespace Causal\Oidc\Tests\Unit\Service;

use Causal\Oidc\AuthenticationContext;
use Causal\Oidc\Service\OAuthService;
use Causal\Oidc\Service\OpenIdConnectService;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class OpenIdConnectServiceTest extends UnitTestCase
{
    protected OpenIdConnectService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $oauthService = new OAuthService($this->createStub(EventDispatcher::class));
        $this->service = new OpenIdConnectService($oauthService, ['dummy']);
    }

    /**
     * @test
     * @dataProvider getFinalLoginUrlReturnsExpectedUrlDataProvider
     */
    public function getFinalLoginUrlReturnsExpectedUrl(string $loginUrl, string $expected): void
    {
        $this->service->setAuthenticationContext(new AuthenticationContext('', $loginUrl, '', '', 'https://example.com/redirect'));
        self::assertSame($expected, (string)$this->service->getFinalLoginUrl('somecode'));
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
}
