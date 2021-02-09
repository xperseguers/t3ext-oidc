<?php
defined('TYPO3_MODE') || die();

(static function (string $_EXTKEY) {
    // Register hooks into \TYPO3\CMS\Core\DataHandling\DataHandler
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = \Causal\Oidc\Hooks\DataHandler::class;
})('oidc');
