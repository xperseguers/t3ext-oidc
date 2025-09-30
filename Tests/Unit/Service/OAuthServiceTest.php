<?php

declare(strict_types=1);

namespace Causal\Oidc\Tests\Unit\Service;

use Causal\Oidc\Service\OAuthService;
use DateTimeImmutable;
use League\OAuth2\Client\Grant\RefreshToken;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionProperty;

#[CoversClass(OAuthService::class)]
final class OAuthServiceTest extends TestCase
{
    #[Test]
    public function getFreshAccessTokenReturnsExistingAccessTokenIfNotExpired(): void
    {
        $accessToken = $this->createAccessTokenWithExpire((new DateTimeImmutable())->modify('+30 seconds'));

        $subject = new OAuthService(self::createStub(EventDispatcherInterface::class));
        $subject->setSettings([
            'access_token' => json_encode($accessToken),
        ]);

        $result = $subject->getFreshAccessToken();

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

        $subject = new OAuthService(self::createStub(EventDispatcherInterface::class));

        $this->setProperty($subject, 'provider', $provider);

        $subject->setSettings([
            'access_token' => json_encode($accessToken),
        ]);

        $result = $subject->getFreshAccessToken();

        self::assertSame(
            $newAccessToken,
            $result,
        );
    }

    #[Test]
    public function getFreshAccessTokenReturnsNullIfAccessTokenIsInvalid(): void
    {
        $subject = new OAuthService(self::createStub(EventDispatcherInterface::class));
        $subject->setSettings([
            'access_token' => '',
        ]);

        $result = $subject->getFreshAccessToken();

        self::assertNull($result);
    }

    #[Test]
    public function getFreshAccessTokenReturnsNullIfRefreshThrowsIdentityProviderException(): void
    {
        $accessToken = $this->createAccessTokenWithExpire((new DateTimeImmutable())->modify('-30 seconds'));

        $provider = self::createStub(AbstractProvider::class);
        $provider->method('getAccessToken')->willThrowException(new IdentityProviderException('message', 10, 'response'));

        $subject = new OAuthService(self::createStub(EventDispatcherInterface::class));

        $this->setProperty($subject, 'provider', $provider);

        $subject->setSettings([
            'access_token' => json_encode($accessToken),
        ]);

        $result = $subject->getFreshAccessToken();

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
