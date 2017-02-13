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
    $referer = $_SERVER['HTTP_REFERER'];
    if (($pos = strpos($referer, 'typo3/index.php')) !== false) {
        $ajaxUrl = substr($referer, 0, $pos) . 'typo3/index.php?ajaxID=TxOidc::callback&state=' . $_GET['state'] . '&code=' . $_GET['code'];
        header('Location: ' . $ajaxUrl);
        exit();
    }
}

exit('Invalid state');
