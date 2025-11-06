<?php

use Causal\Oidc\Frontend\FrontendSimulationInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScriptFactory;
use TYPO3\CMS\Frontend\Page\PageInformationFactory;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (
    ContainerConfigurator $configurator,
    ContainerBuilder $builder,
) {
    // We retrieve the services class.
    $services = $configurator->services();

    // Ensure to make only the concrete frontend simulation for current used TYPO3 version
    // available in the dependency container. Additionally we create an alias for to the
    // version related implementation for `FrontendSimulationInterface` allowing to use
    // the interface to get the suiting implementation for current core usage injected or
    // retrieved using `GeneralUtility::makeInstance(FrontendSimulationInterface::class)`.
    $typo3MajorVersion = (new Typo3Version())->getMajorVersion();
    $frontendSimulationClassName = sprintf(
        'Causal\\Oidc\\Frontend\\FrontendSimulationV%s',
        $typo3MajorVersion,
    );
    $services->set($frontendSimulationClassName)->autoconfigure()->autowire()->public();
    $services->alias(FrontendSimulationInterface::class, service($frontendSimulationClassName))->public();

    // Make internal TYPO3 v13 services public so that they can be retrieved with `GeneralUtility::makeInstance()`,
    // which is required for `FrontendSimulationV13` implementation to pull them instead of getting them injected.
    if (class_exists(PageInformationFactory::class)) {
        $builder->findDefinition(PageInformationFactory::class)->setPublic(true);
    }
    if (class_exists(FrontendTypoScriptFactory::class)) {
        $builder->findDefinition(FrontendTypoScriptFactory::class)->setPublic(true);
    }
};
