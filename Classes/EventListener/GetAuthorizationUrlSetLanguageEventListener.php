<?php

declare(strict_types=1);

namespace Causal\Oidc\EventListener;

use Causal\Oidc\Event\GetAuthorizationUrlEvent;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

class GetAuthorizationUrlSetLanguageEventListener
{
    public function __invoke(GetAuthorizationUrlEvent $event): void
    {
        $languageOption = $event->settings->authorizeLanguageParameter;
        if (!$languageOption) {
            return;
        }

        $language = 'en';
        $request = $event->request;
        if ($request && ApplicationType::fromRequest($request)->isFrontend()) {
            /** @var SiteLanguage $siteLanguage */
            $siteLanguage = $request->getAttribute('language', $request->getAttribute('site')?->getDefaultLanguage());
            $language = $siteLanguage?->getLocale()->getLanguageCode() ?? $language;
        }
        $event->options[$languageOption] = $language;
    }
}
