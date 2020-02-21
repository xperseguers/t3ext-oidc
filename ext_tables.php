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

defined('TYPO3_MODE') || die();

$boot = function ($_EXTKEY) {
    // Register TypoScript
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript', 'OpenID Connect');
    if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('felogin')) {
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript/felogin', 'OpenID Connect for felogin');
    }

    // Register hooks into \TYPO3\CMS\Core\DataHandling\DataHandler
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = \Causal\Oidc\Hooks\DataHandler::class;
};

$boot('oidc');
unset($boot);
