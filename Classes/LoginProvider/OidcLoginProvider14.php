<?php

declare(strict_types=1);

namespace Causal\Oidc\LoginProvider;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\LoginProvider\LoginProviderInterface;
use TYPO3\CMS\Core\View\ViewInterface;

class OidcLoginProvider14 implements LoginProviderInterface
{
    public const int IDENTIFIER = 1742888452490;

    /**
     * Implementation for TYPO3 v14+
     */
    public function modifyView(ServerRequestInterface $request, ViewInterface $view): string
    {
        $view->assign('enablePasswordReset', false);
        return 'Backend/LoginOidc';
    }
}
