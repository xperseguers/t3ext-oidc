<?php

declare(strict_types=1);

namespace Causal\Oidc\Provider;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Client as GuzzleClient;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * OpenID Connect provider with automatic endpoint discovery.
 *
 * Fetches the provider's .well-known/openid-configuration document
 * and fills empty endpoint URLs from it. Manually configured URLs
 * always take precedence over discovered values.
 */
class GenericOpenIdDiscoveryProvider extends GenericOpenIdProvider
{
    /**
     * Parsed OIDC discovery document.
     *
     * @var array<string, mixed>
     */
    private array $discoveryDocument = [];

    /**
     * Maps league option keys to OIDC discovery document keys.
     */
    private const DISCOVERY_MAP = [
        'urlAuthorize' => 'authorization_endpoint',
        'urlAccessToken' => 'token_endpoint',
        'urlResourceOwnerDetails' => 'userinfo_endpoint',
    ];

    /**
     * @param array<string, mixed> $options Provider options, including 'discoveryUrl'
     * @param array<string, mixed> $collaborators Provider collaborators (httpClient, requestFactory)
     */
    public function __construct(array $options = [], array $collaborators = [])
    {
        $discoveryUrl = $options['discoveryUrl'] ?? '';
        unset($options['discoveryUrl']);

        if ($discoveryUrl !== '') {
            $httpClient = $collaborators['httpClient'] ?? new GuzzleClient();

            try {
                $this->discoveryDocument = $this->fetchDiscoveryDocument(
                    $this->normalizeDiscoveryUrl($discoveryUrl),
                    $httpClient
                );

                foreach (self::DISCOVERY_MAP as $optionKey => $discoveryKey) {
                    if (empty($options[$optionKey]) && !empty($this->discoveryDocument[$discoveryKey])) {
                        $options[$optionKey] = $this->discoveryDocument[$discoveryKey];
                    }
                }
            } catch (\Throwable $e) {
                // Discovery failure is non-fatal; manually configured
                // endpoints (if any) remain untouched.
                $this->getLogger()->warning(
                    'OIDC Discovery failed: ' . $e->getMessage(),
                    ['discoveryUrl' => $discoveryUrl]
                );
            }
        }

        parent::__construct($options, $collaborators);
    }

    /**
     * Returns the parsed OIDC discovery document.
     *
     * @return array<string, mixed>
     */
    public function getDiscoveryDocument(): array
    {
        return $this->discoveryDocument;
    }

    /**
     * Fetch and parse an OIDC discovery document.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    private function fetchDiscoveryDocument(string $discoveryUrl, ClientInterface $httpClient): array
    {
        $response = $httpClient->request('GET', $discoveryUrl, [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $data = json_decode((string)$response->getBody(), true);

        if (!is_array($data) || empty($data['authorization_endpoint'])) {
            throw new \RuntimeException(
                'Invalid OIDC Discovery response from ' . $discoveryUrl,
                1744200001
            );
        }

        return $data;
    }

    /**
     * Normalize discovery URL:
     * - Bare domain → prepend https://
     * - Missing /.well-known/ path → append it
     */
    protected function normalizeDiscoveryUrl(string $url): string
    {
        if (!str_starts_with($url, 'http')) {
            $url = 'https://' . $url;
        }

        if (!str_contains($url, '.well-known')) {
            $url = rtrim($url, '/') . '/.well-known/openid-configuration';
        }

        return $url;
    }

    private function getLogger(): LoggerInterface
    {
        return GeneralUtility::makeInstance(LogManager::class)->getLogger(static::class);
    }
}
