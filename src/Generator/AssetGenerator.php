<?php

declare(strict_types=1);

namespace Rezepte\Generator;

use Psr\Log\LoggerInterface;
use Rezepte\Recipe\RecipeCollection;
use Rezepte\Search\SearchIndexBuilder;
use Rezepte\Support\OutputDirectory;
use Rezepte\Support\VendorAssets;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;

/**
 * Provides all static files below public/assets/.
 */
final readonly class AssetGenerator
{
    public function __construct(
        private string $assetsDir,
        private OutputDirectory $output,
        private SearchIndexBuilder $searchIndex,
        private VendorAssets $vendorAssets,
        private LoggerInterface $logger,
    ) {
    }

    public function writeSearchIndex(RecipeCollection $recipes): void
    {
        $this->output->put('assets/data/recipes.json', $this->searchIndex->toJson($recipes));
    }

    /**
     * Copies the main images (unique names in assets/images/) and further recipe images.
     */
    public function copyRecipeImages(RecipeCollection $recipes): int
    {
        $count = 0;
        foreach ($recipes as $recipe) {
            $this->output->copy($recipe->image->sourcePath, ltrim($recipe->imageUrlPath(), '/'));
            $this->logger->info('Image copied: {file}', ['file' => $recipe->imageFileName()]);
            $count++;

            // Further images go next to the recipe page, so content.md can use relative links.
            foreach ($recipe->attachments as $fileName => $sourcePath) {
                $this->output->copy($sourcePath, $recipe->path() . '/' . $fileName);
                $count++;
            }
        }

        return $count;
    }

    public function compileStyles(): void
    {
        $source = $this->assetsDir . '/scss/style.scss';
        $compiler = new Compiler();
        $compiler->setOutputStyle(OutputStyle::COMPRESSED);
        $compiler->addImportPath($this->assetsDir . '/scss');

        $css = $compiler->compileFile($source)->getCss();
        $this->output->put('assets/css/style.css', $css . "\n");
    }

    public function copyScripts(): void
    {
        foreach (glob($this->assetsDir . '/js/*.js') ?: [] as $script) {
            $this->output->copy($script, 'assets/js/' . basename($script));
        }
    }

    /**
     * Static files such as the default OG image and the favicon (assets/static/).
     */
    public function copyStaticFiles(): void
    {
        foreach (glob($this->assetsDir . '/static/*') ?: [] as $file) {
            if (is_file($file)) {
                $this->output->copy($file, 'assets/static/' . basename($file));
            }
        }
    }

    public function copyVendorAssets(): void
    {
        foreach ($this->vendorAssets->resolveAll(!$this->output->isDryRun()) as $name => $path) {
            $this->output->copy($path, 'assets/vendor/' . $name);
        }
    }
}
