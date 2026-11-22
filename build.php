<?php

declare(strict_types=1);

/*
 * Entry point of the recipe site generator.
 *
 *   php build.php [--dry-run] [--base-url=URL] [-v] [--help]
 */

use Rezepte\Console\BuildCommand;
use Symfony\Component\Console\Application;

if (PHP_SAPI !== 'cli') {
    exit("This script is meant to be run from the command line only.\n");
}

require __DIR__ . '/vendor/autoload.php';

$application = new Application('Recipe site generator', '1.0.0');
$command = new BuildCommand(__DIR__);
$application->addCommand($command);
$application->setDefaultCommand(BuildCommand::NAME, true);

exit($application->run());
