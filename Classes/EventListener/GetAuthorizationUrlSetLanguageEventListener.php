<?php

declare(strict_types=1);

namespace Causal\Oidc\EventListener;

use Causal\Oidc\Event\GetAuthorizationUrlEvent;
use Causal\Oidc\LoginProvider\OidcLoginProvider;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

class GetAuthorizationUrlSetLanguageEventListener
{
    public function __invoke(GetAuthorizationUrlEvent $event): void
    {
        $settings = $event->settings;
        $languageOption = $settings['oidcAuthorizeLanguageParameter'] ?? null;
        if (empty($languageOption)) {
            return;
        }

        $language = 'en';
        $request = $event->request;
        if ($request && !array_key_exists('loginProvider',$request->getQueryParams())) {
            /** @var SiteLanguage $siteLanguage */
            $siteLanguage = $request->getAttribute('language', $request->getAttribute('site')->getDefaultLanguage());
            $language = $siteLanguage->getLocale()->getLanguageCode();
        }
        $event->options[$languageOption] = $language;
    }
}
