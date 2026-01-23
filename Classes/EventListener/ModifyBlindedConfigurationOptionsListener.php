<?php

declare(strict_types=1);

namespace Causal\Oidc\EventListener;

use TYPO3\CMS\Lowlevel\Event\ModifyBlindedConfigurationOptionsEvent;

final class ModifyBlindedConfigurationOptionsListener
{
    public function __invoke(ModifyBlindedConfigurationOptionsEvent $event): void
    {
        $options = $event->getBlindedConfigurationOptions();
        if ($event->getProviderIdentifier() === 'confVars') {
            $options['TYPO3_CONF_VARS']['EXTENSIONS']['oidc']['oidcClientKey']
                = substr($options['TYPO3_CONF_VARS']['EXTENSIONS']['oidc']['oidcClientKey'], 0, 3) . '******';
            $options['TYPO3_CONF_VARS']['EXTENSIONS']['oidc']['oidcClientSecret']
                = substr($options['TYPO3_CONF_VARS']['EXTENSIONS']['oidc']['oidcClientSecret'], 0, 3) . '******';
        }

        $event->setBlindedConfigurationOptions($options);
    }
}
