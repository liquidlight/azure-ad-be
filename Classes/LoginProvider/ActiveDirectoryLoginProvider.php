<?php

declare(strict_types=1);

namespace DifferentTechnology\AzureAdBe\LoginProvider;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\LoginController;
use TYPO3\CMS\Backend\LoginProvider\LoginProviderInterface;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Fluid\View\FluidViewAdapter;
use TYPO3\CMS\Fluid\View\StandaloneView;

class ActiveDirectoryLoginProvider implements LoginProviderInterface
{
    public const TEMPLATE_ROOT_PATH = 'EXT:azure_ad_be/Resources/Private/Templates';

    /**
     * Used by TYPO3 v12, which does not call modifyView()
     *
     * @throws \UnexpectedValueException
     */
    public function render(StandaloneView $view, PageRenderer $pageRenderer, LoginController $loginController): void
    {
        $view->setTemplatePathAndFilename(GeneralUtility::getFileAbsFileName(self::TEMPLATE_ROOT_PATH . '/LoginForm.html'));
    }

    /**
     * Used by TYPO3 v13+
     *
     * @return string Template file to render
     */
    public function modifyView(ServerRequestInterface $request, ViewInterface $view): string
    {
        if ($view instanceof FluidViewAdapter) {
            $templatePaths = $view->getRenderingContext()->getTemplatePaths();
            $templatePaths->setTemplateRootPaths([
                ...$templatePaths->getTemplateRootPaths(),
                self::TEMPLATE_ROOT_PATH,
            ]);
        }
        return 'LoginForm';
    }
}
