<?php

use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

if ((new Typo3Version())->getMajorVersion() < 13) {
    ExtensionManagementUtility::addTypoScriptSetup("@import 'EXT:fluid_styled_content/Configuration/TypoScript/setup.typoscript'");
    ExtensionManagementUtility::addTypoScriptSetup("@import 'EXT:fluid_styled_content/Configuration/TypoScript/Styling/setup.typoscript'");
    ExtensionManagementUtility::addTypoScriptSetup("@import 'EXT:oidc-sitepackage/Configuration/Sets/Oidc/setup.typoscript'");
    ExtensionManagementUtility::addTypoScriptConstants("@import 'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript'");
    ExtensionManagementUtility::addTypoScriptConstants("@import 'EXT:fluid_styled_content/Configuration/TypoScript/Styling/constants.typoscript'");
    ExtensionManagementUtility::addTypoScriptConstants("@import 'EXT:oidc-sitepackage/Configuration/Sets/Oidc/constants.typoscript'");
}
