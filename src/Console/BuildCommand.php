<?php

declare(strict_types=1);

namespace Rezepte\Console;

use Psr\Log\LogLevel;
use Rezepte\Config\ConfigException;
use Rezepte\Config\SiteConfig;
use Rezepte\Generator\BuildReport;
use Rezepte\Recipe\RecipeLoadException;
use Rezepte\SiteFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Path;

#[AsCommand(name: BuildCommand::NAME, description: 'Generates the static recipe website.')]
final class BuildCommand extends Command
{
    public const string NAME = 'build';
    private const string DIST_CONFIG = 'config/site.dist.php';
    private const string LOCAL_CONFIG = 'config/site.php';

    public function __construct(private readonly string $projectDir)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate and render only, do not write any files')
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'Override the base URL from the configuration')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Local configuration (overrides ' . self::DIST_CONFIG . ')', self::LOCAL_CONFIG)
            ->setHelp(<<<'HELP'
                Scans the recipes directory, validates all recipes and generates
                the complete static website in the output directory.

                  <info>php build.php</info>                       Build the website
                  <info>php build.php --dry-run</info>             Validate only, write nothing
                  <info>php build.php -v</info>                    Verbose output
                  <info>php build.php --base-url=https://example.org</info>
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $io->title('Recipe site generator' . ($dryRun ? ' (dry run)' : ''));

        $logger = new CountingLogger(new ConsoleLogger($output, [
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO => OutputInterface::VERBOSITY_VERBOSE,
            LogLevel::DEBUG => OutputInterface::VERBOSITY_VERY_VERBOSE,
        ], [LogLevel::NOTICE => 'comment']));

        try {
            $configOption = self::stringOption($input, 'config') ?? self::LOCAL_CONFIG;
            $localConfig = Path::makeAbsolute($configOption, $this->projectDir);
            if (!is_file($localConfig)) {
                if ($configOption !== self::LOCAL_CONFIG) {
                    throw new ConfigException(sprintf('Configuration file "%s" not found.', $localConfig));
                }
                $logger->warning('No local {local} found – using only the defaults from {dist}.', [
                    'local' => self::LOCAL_CONFIG,
                    'dist' => self::DIST_CONFIG,
                ]);
                $io->text([
                    sprintf('Create %s with at least your own base URL, e.g.:', self::LOCAL_CONFIG),
                    '',
                    '  <info><?php</info>',
                    "  <info>return ['base_url' => 'https://your-domain.example'];</info>",
                    '',
                ]);
            }
            $config = SiteConfig::fromFiles(
                Path::makeAbsolute(self::DIST_CONFIG, $this->projectDir),
                $localConfig,
                $this->projectDir,
                self::stringOption($input, 'base-url'),
            );
            $logger->info('Base URL: {url}', ['url' => $config->baseUrl]);

            $report = (new SiteFactory($config, $logger))->createSiteGenerator($dryRun)->build();
        } catch (ConfigException $exception) {
            $io->error('Configuration error: ' . $exception->getMessage());

            return self::INVALID;
        } catch (RecipeLoadException $exception) {
            $this->printRecipeErrors($io, $exception);
            $io->error($exception->getMessage() . ' No files were changed.');

            return self::FAILURE;
        } catch (\Throwable $exception) {
            $io->error(sprintf('Build aborted: %s', $exception->getMessage()));
            if ($output->isVerbose()) {
                $io->text($exception->getTraceAsString());
            }

            return self::FAILURE;
        }

        $this->printSummary($io, $report, $logger->warnings(), $config->outputDir);

        return self::SUCCESS;
    }

    private static function stringOption(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function printRecipeErrors(SymfonyStyle $io, RecipeLoadException $exception): void
    {
        $io->section('Invalid recipes');
        foreach ($exception->errors as $error) {
            $io->writeln(sprintf('<error> %s </error>', $error->recipePath));
            $io->listing($error->problems);
        }
    }

    private function printSummary(SymfonyStyle $io, BuildReport $report, int $warnings, string $outputDir): void
    {
        $io->section('Summary');
        $io->definitionList(
            ['Recipes' => (string) $report->recipes],
            ['Categories' => (string) $report->categories],
            ['HTML pages' => (string) $report->pages],
            ['Images' => (string) $report->images],
            [($report->dryRun ? 'Files (simulated)' : 'Files written') => (string) $report->files],
            ['Warnings' => (string) $warnings],
            ['Duration' => sprintf('%.2f s', $report->seconds)],
        );

        $message = $report->dryRun
            ? 'Dry run successful – all recipes are valid.'
            : sprintf('Website generated in %s', $outputDir);
        $warnings > 0 ? $io->warning($message . sprintf(' (%d warning(s))', $warnings)) : $io->success($message);
    }
}
