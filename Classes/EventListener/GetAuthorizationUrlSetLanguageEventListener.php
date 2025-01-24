<?php

declare(strict_types=1);

namespace Causal\Oidc\EventListener;

use Causal\Oidc\Event\GetAuthorizationUrlEvent;
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
        if ($request) {
            /** @var SiteLanguage $siteLanguage */
            $siteLanguage = $request->getAttribute('language', $request->getAttribute('site')->getDefaultLanguage());
            // fallback for TYPO3 v11
            $language = is_object($siteLanguage->getLocale())
                ? $siteLanguage->getLocale()->getLanguageCode()
                : $siteLanguage->getTwoLetterIsoCode();
        }
        $event->options = array_replace($event->options, [
            $languageOption => $language,
        ]);
    }
}
