<?php

namespace Causal\Oidc\LoginProvider;

use TYPO3\CMS\Backend\Controller\LoginController;
use TYPO3\CMS\Backend\LoginProvider\LoginProviderInterface;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class OidcLoginProvider implements LoginProviderInterface
{
    /**
     * @var int
     */
    public const IDENTIFIER = 1742888452490;

    /**
     * @inheritDoc
     */
    public function render(StandaloneView $view, PageRenderer $pageRenderer, LoginController $loginController)
    {
        $view->setTemplatePathAndFilename(
            GeneralUtility::getFileAbsFileName('EXT:oidc/Resources/Private/Templates/Backend/LoginOidc.html')
        );

        $view->assign('enablePasswordReset', false);
    }
}
