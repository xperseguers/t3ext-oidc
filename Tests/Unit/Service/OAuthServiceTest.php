<?php

declare(strict_types=1);

namespace Causal\Oidc\Tests\Unit\Service;

use Causal\Oidc\Service\OAuthService;
use Causal\Oidc\Tests\Unit\AbstractUnitTest;
use DateTimeImmutable;
use League\OAuth2\Client\Grant\RefreshToken;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;

#[CoversClass(OAuthService::class)]
final class OAuthServiceTest extends AbstractUnitTest
{
    protected bool $resetSingletonInstances = true;

    protected OAuthService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupOidcConfiguration();
        $this->subject = $this->createOAuthService();
    }

    #[Test]
    public function getFreshAccessTokenReturnsExistingAccessTokenIfNotExpired(): void
    {
        $accessToken = $this->createAccessTokenWithExpire((new DateTimeImmutable())->modify('+30 seconds'));

        $result = $this->subject->getFreshAccessToken(json_encode($accessToken));

        self::assertSame(
            json_encode($accessToken),
            json_encode($result)
        );
    }

    #[Test]
    public function getFreshAccessTokenReturnsFreshAccessTokenIfExpired(): void
    {
        $accessToken = $this->createAccessTokenWithExpire((new DateTimeImmutable())->modify('-30 seconds'));
        $newAccessToken = $this->createAccessTokenWithExpire((new DateTimeImmutable())->modify('+1 minutes'));

        $provider = $this->createMock(AbstractProvider::class);
        $provider->expects(self::once())
            ->method('getAccessToken')
            ->with(
                self::isInstanceOf(RefreshToken::class),
                [
                    'refresh_token' => 'refresh_token_value',
                ]
            )
            ->willReturn($newAccessToken);

        $this->setProperty($this->subject, 'provider', $provider);

        $result = $this->subject->getFreshAccessToken(json_encode($accessToken));

        self::assertSame(
            $newAccessToken,
            $result,
        );
    }

    #[Test]
    public function getFreshAccessTokenReturnsNullIfAccessTokenIsInvalid(): void
    {
        $result = $this->subject->getFreshAccessToken('');

        self::assertNull($result);
    }

    #[Test]
    public function getFreshAccessTokenReturnsNullIfRefreshThrowsIdentityProviderException(): void
    {
        $accessToken = $this->createAccessTokenWithExpire((new DateTimeImmutable())->modify('-30 seconds'));

        $provider = self::createStub(AbstractProvider::class);
        $provider->method('getAccessToken')->willThrowException(new IdentityProviderException('message', 10, 'response'));

        $this->setProperty($this->subject, 'provider', $provider);

        $result = $this->subject->getFreshAccessToken(json_encode($accessToken));

        self::assertNull($result);
    }

    private function createAccessTokenWithExpire(DateTimeImmutable $expires): AccessToken
    {
        return new AccessToken([
            'access_token' => 'access_token_value',
            'refresh_token' => 'refresh_token_value',
            'expires' => (int)$expires->format('U'),
        ]);
    }

    private function setProperty(object $subject, string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty($subject, $property);
        $reflection->setValue($subject, $value);
    }
}
