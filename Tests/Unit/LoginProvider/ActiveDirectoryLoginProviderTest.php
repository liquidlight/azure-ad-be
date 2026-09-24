<?php

declare(strict_types=1);

namespace DifferentTechnology\AzureAdBe\Tests\Unit\LoginProvider;

use DifferentTechnology\AzureAdBe\LoginProvider\ActiveDirectoryLoginProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Fluid\View\FluidViewAdapter;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\View\TemplatePaths;
use TYPO3Fluid\Fluid\View\TemplateView;

final class ActiveDirectoryLoginProviderTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // modifyView() is only called by TYPO3 v13+, which introduced the Fluid view adapter
        if (!class_exists(FluidViewAdapter::class)) {
            self::markTestSkipped('modifyView() is only used from TYPO3 v13');
        }
    }

    #[Test]
    public function modifyViewAddsExtensionTemplatePathAfterCorePaths(): void
    {
        // EXT: paths can't be resolved without a package manager, so keep paths as given
        $templatePaths = new class () extends TemplatePaths {
            protected function sanitizePath(mixed $path): string|array
            {
                return $path;
            }
        };
        $templatePaths->setTemplateRootPaths(['EXT:backend/Resources/Private/Templates']);
        $renderingContext = new RenderingContext();
        $renderingContext->setTemplatePaths($templatePaths);
        $view = new FluidViewAdapter(new TemplateView($renderingContext));

        $template = (new ActiveDirectoryLoginProvider())->modifyView(new ServerRequest(), $view);

        self::assertSame('LoginForm', $template);
        // Last path wins, so the extension's LoginForm.html is found before any core template
        self::assertSame(
            ['EXT:backend/Resources/Private/Templates', 'EXT:azure_ad_be/Resources/Private/Templates'],
            $templatePaths->getTemplateRootPaths()
        );
    }
}
