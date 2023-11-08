<?php
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
        // @TODO how to improve security? Maybe get rid of redirect in \Causal\Oidc\Controller\AuthenticationController::connectAction?
        if (!isset($_GET['tx_oidc'])) {
            return;
        }
        $event->setRequestToken(RequestToken::create('core/user-auth/' . strtolower($user->loginType)));
    }
}
