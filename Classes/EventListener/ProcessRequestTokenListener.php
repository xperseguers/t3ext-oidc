<?php

declare(strict_types=1);

namespace Causal\Oidc\EventListener;

use TYPO3\CMS\Core\Authentication\Event\BeforeRequestTokenProcessedEvent;
use TYPO3\CMS\Core\Security\RequestToken;

final class ProcessRequestTokenListener
{
    public function __invoke(BeforeRequestTokenProcessedEvent $event): void
    {
        $user = $event->getUser();
        $requestToken = $event->getRequestToken();
        if ($requestToken instanceof RequestToken) {
            // fine, there is a valid request-token
            return;
        }
        if (!isset($event->getRequest()->getQueryParams()['tx_oidc'])) {
            return;
        }
        $event->setRequestToken(RequestToken::create('core/user-auth/' . strtolower($user->loginType)));
    }
}
