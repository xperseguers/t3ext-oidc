<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Causal\Oidc\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use TYPO3\CMS\Core\Security\SecretIdentifier;

/**
 * Trait providing support for JWT using symmetric hash signing.
 *
 * The benefit of using a trait in this particular case is, that defaults in `self::class`
 * (used as a pepper during the singing process) are specific for that a particular implementation.
 *
 * @internal
 *
 * IMPORTANT: This is a _partial_ copy of \TYPO3\CMS\Core\Security\JwtTrait in TYPO3 v14
 */
trait JwtTrait
{
    private static function getDefaultSigningAlgorithm(): string
    {
        return 'HS256';
    }

    private static function deriveKey(
        #[\SensitiveParameter]
        string $baseKey,
        string $context,
    ): Key {
        $jwtAlgo = self::getDefaultSigningAlgorithm();
        [$hashAlgo, $length] = match ($jwtAlgo) {
            'HS256' => ['sha256', 32],
            'HS384' => ['sha384', 48],
            'HS512' => ['sha512', 64],
            default => throw new \InvalidArgumentException('Unsupported JWT algorithm: ' . $jwtAlgo, 1774954888),
        };
        return new Key(
            hash_hkdf(
                algo: $hashAlgo,
                key: $baseKey,
                length: $length,
                info: $context,
            ),
            $jwtAlgo
        );
    }

    private static function createSigningKeyFromEncryptionKey(string $context = self::class): Key
    {
        return self::deriveKey(
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? '',
            $context === '' ? self::class : $context
        );
    }

    private static function encodeHashSignedJwt(array $payload, Key $key, ?SecretIdentifier $identifier = null): string
    {
        $keyId = $identifier !== null ? json_encode($identifier) : null;
        return JWT::encode($payload, $key->getKeyMaterial(), $key->getAlgorithm(), $keyId);
    }

    /**
     * @param string $jwt
     * @param Key $key
     * @param bool $associative
     * @phpstan-return ($associative is true ? array : \stdClass)
     */
    private static function decodeJwt(string $jwt, Key $key, bool $associative = false): \stdClass|array
    {
        $payload = JWT::decode($jwt, $key);
        $encodedPayload = json_encode($payload);
        if ($encodedPayload === false) {
            throw new \InvalidArgumentException('Invalid JWT string', 1788878063);
        }
        return $associative ? json_decode($encodedPayload, true) : $payload;
    }
}
