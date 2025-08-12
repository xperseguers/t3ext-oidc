<?php

namespace Causal\Oidc\LoginProvider;

use TYPO3\CMS\Backend\Authentication\PasswordReset;
use TYPO3\CMS\Backend\Controller\LoginController;
use TYPO3\CMS\Backend\LoginProvider\LoginProviderInterface;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class OidcLoginProvider implements LoginProviderInterface
{
    public const int IDENTIFIER = 1754326802;

    public function render(StandaloneView $view, PageRenderer $pageRenderer, LoginController $loginController): void
    {
        $view->setTemplatePathAndFilename('EXT:oidc/Resources/Private/Templates/Login/BackendLoginForm.html');
    }
}
