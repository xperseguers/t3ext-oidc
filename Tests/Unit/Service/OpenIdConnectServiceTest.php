<?php

declare(strict_types=1);

namespace Causal\Oidc\Tests\Unit\Service;

use Causal\Oidc\AuthenticationContext;
use Causal\Oidc\Service\AuthenticationContextService;
use Causal\Oidc\Service\OpenIdConnectService;
use Causal\Oidc\Tests\Unit\AbstractUnitTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class OpenIdConnectServiceTest extends AbstractUnitTestBase
{
    protected bool $resetSingletonInstances = true;

    #[Test]
    #[DataProvider('getFinalLoginUrlReturnsExpectedUrlDataProvider')]
    public function getFinalLoginUrlReturnsExpectedUrl(string $loginUrl, string $expected): void
    {
        $service = new OpenIdConnectService(
            $this->createOAuthService(),
            new AuthenticationContextService(),
            $this->setupOidcConfiguration(),
        );

        $authenticationContext = new AuthenticationContext('', $loginUrl, '', '', false);
        self::assertSame($expected, (string)$service->getFinalLoginUrl($authenticationContext, 'somecode'));
    }

    public static function getFinalLoginUrlReturnsExpectedUrlDataProvider(): array
    {
        return [
            'default' => [
                'loginUrl' => 'https://example.com/login?logintype=login',
                'expected' => 'https://example.com/login?logintype=login&tx_oidc%5Bcode%5D=somecode',
            ],
            'preserves params' => [
                'loginUrl' => 'https://example.com/login?logintype=login&otherparam=foo',
                'expected' => 'https://example.com/login?logintype=login&otherparam=foo&tx_oidc%5Bcode%5D=somecode',
            ],
            'preserves redirect_url' => [
                'loginUrl' => 'https://example.com/login?logintype=login&redirect_url=http%3A%2F%2Fexample.com%2Fother',
                'expected' => 'https://example.com/login?logintype=login&redirect_url=http%3A%2F%2Fexample.com%2Fother&tx_oidc%5Bcode%5D=somecode',
            ],
        ];
    }
}
