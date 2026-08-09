<?php

declare(strict_types=1);

namespace Causal\Oidc\LoginProvider;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\LoginController;
use TYPO3\CMS\Backend\LoginProvider\LoginProviderInterface;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;

class OidcLoginProvider implements LoginProviderInterface
{
    /**
     * @var int
     */
    public const IDENTIFIER = 1742888452490;

    /**
     * @inheritDoc
     * Implementation for TYPO3 v13
     * @todo must be removed/hidden in v14 due to missing StandaloneView
     */
    public function render(StandaloneView $view, PageRenderer $pageRenderer, LoginController $loginController)
    {
        $view->setTemplatePathAndFilename(
            GeneralUtility::getFileAbsFileName('EXT:oidc/Resources/Private/Templates/Backend/LoginOidc.html')
        );

        $view->assign('enablePasswordReset', false);
    }

    /**
     * Implementation for TYPO3 v14+
     */
    public function modifyView(ServerRequestInterface $request, ViewInterface $view): string
    {
        $view->assign('enablePasswordReset', false);
        return 'Backend/LoginOidc';
    }
}
