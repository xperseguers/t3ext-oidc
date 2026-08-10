<?php

declare(strict_types=1);

namespace Causal\Oidc\LoginProvider;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\LoginController;
use TYPO3\CMS\Backend\LoginProvider\LoginProviderInterface;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Fluid\View\FluidViewAdapter;

class OidcLoginProvider implements LoginProviderInterface
{
    public const int IDENTIFIER = 1742888452490;

    /**
     * @inheritDoc
     * @param \TYPO3\CMS\Fluid\View\StandaloneView $view
     */
    public function render($view, PageRenderer $pageRenderer, LoginController $loginController) {}

    public function modifyView(ServerRequestInterface $request, ViewInterface $view): string
    {
        $view->assign('enablePasswordReset', false);
        if ($view instanceof FluidViewAdapter) {
            $view->getRenderingContext()->getTemplatePaths()->setTemplateRootPaths(['EXT:oidc/Resources/Private/Templates/']);
        }
        return 'Backend/LoginOidc';
    }
}
