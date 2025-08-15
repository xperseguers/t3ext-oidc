<?php

namespace Causal\Oidc\LoginProvider;

use TYPO3\CMS\Backend\Controller\LoginController;
use TYPO3\CMS\Backend\LoginProvider\LoginProviderInterface;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Fluid\View\StandaloneView;

class OidcLoginProvider implements LoginProviderInterface
{
    public const int IDENTIFIER = 1754326802;

    public function render(StandaloneView $view, PageRenderer $pageRenderer, LoginController $loginController): void
    {
        $view->setTemplatePathAndFilename('EXT:oidc/Resources/Private/Templates/Login/BackendLoginForm.html');

        if (session_id() !== '') { // If no session exists, start a new one
            session_start();
        }

        if (isset($_SESSION['oidc_user'])) {
            $view->assignMultiple(
                [
                    'hasOidcLoginError' => true,
                    'oidcUser' => $_SESSION['oidc_user']['realName'],
                ]
            );
            unset($_SESSION['oidc_user']);
        }
    }
}
