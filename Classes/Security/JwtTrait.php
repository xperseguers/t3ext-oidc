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

/**
 * Trait providing support for JWT using symmetric hash signing.
 *
 * The benefit of using a trait in this particular case is, that defaults in `self::class`
 * (used as a pepper during the singing process) are specific for that a particular implementation.
 *
 * @internal
 *
 * IMPORTANT: This is a _partial_ copy of \TYPO3\CMS\Core\Security\JwtTrait in TYPO3 v12
 */
trait JwtTrait
{
    private static function getDefaultSigningAlgorithm(): string
    {
        return 'HS256';
    }

    private static function createSigningKeyFromEncryptionKey(string $pepper = self::class): Key
    {
        if ($pepper === '') {
            $pepper = self::class;
        }
        $encryptionKey = $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? '';
        $keyMaterial = hash('sha256', $encryptionKey) . '/' . $pepper;
        return new Key($keyMaterial, self::getDefaultSigningAlgorithm());
    }

    private static function encodeHashSignedJwt(array $payload, Key $key): string
    {
        return JWT::encode($payload, $key->getKeyMaterial(), self::getDefaultSigningAlgorithm());
    }

    /**
     * @param string $jwt
     * @param Key $key
     * @param bool $associative
     * @return \stdClass|array
     */
    private static function decodeJwt(string $jwt, Key $key, bool $associative = false)
    {
        $payload = JWT::decode($jwt, $key);
        return $associative ? json_decode(json_encode($payload), true) : $payload;
    }
}
