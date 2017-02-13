<?php
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

namespace Causal\Oidc\Service;

/**
 * OpenID Connect authentication service.
 */
class AuthenticationService extends \TYPO3\CMS\Sv\AuthenticationService
{

    /**
     * true - this service was able to authenticate the user
     */
    const STATUS_AUTHENTICATION_SUCCESS_CONTINUE = true;

    /**
     * 200 - authenticated and no more checking needed
     */
    const STATUS_AUTHENTICATION_SUCCESS_BREAK = 200;

    /**
     * false - this service was the right one to authenticate the user but it failed
     */
    const STATUS_AUTHENTICATION_FAILURE_BREAK = false;

    /**
     * 100 - just go on. User is not authenticated but there's still no reason to stop
     */
    const STATUS_AUTHENTICATION_FAILURE_CONTINUE = 100;

    /**
     * AuthenticationService constructor.
     */
    public function __construct()
    {
        $config = $GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['oidc'];
        $this->config = $config ? unserialize($config) : [];
    }

    /**
     * Finds a user.
     *
     * @return int|bool|array
     */
    public function getUser()
    {
        return [];
    }

    /**
     * Authenticate a user
     *
     * @oaram array $user
     * @return int
     */
    public function authUser(array $user)
    {
        return static::STATUS_AUTHENTICATION_FAILURE_CONTINUE;
    }

}
