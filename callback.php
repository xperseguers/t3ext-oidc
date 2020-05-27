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

// see https://github.com/thephpleague/oauth2-client
if (!(empty($_GET['state']) || empty($_GET['code']))) {
    $schema = (@$_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
    $currentUrl = $schema . $_SERVER['SERVER_NAME'];
    if ($_SERVER['SERVER_PORT'] !== '80' && $_SERVER['SERVER_PORT'] !== '443') {
        $currentUrl .= ':' . $_SERVER['SERVER_PORT'];
    }
    $currentUrl .= $_SERVER['REQUEST_URI'];

    if (($pos = strpos($currentUrl, 'typo3conf/ext/oidc/callback.php')) !== false) {
        $connectUrl = substr($currentUrl, 0, $pos) . 'de/?type=1489657462&state=' . $_GET['state'] . '&code=' . $_GET['code'];
        header('Location: ' . $connectUrl);
        exit();
    }
}

exit('Invalid state');
