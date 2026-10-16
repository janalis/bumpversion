<?php

declare(strict_types=1);

use Janalis\Bumpversion\Command\BumpCommand;
use Janalis\Bumpversion\Command\InitCommand;
use Symfony\Component\Console\Application;

require $GLOBALS['_composer_autoload_path'] ?? __DIR__.'/../vendor/autoload.php';

$application = new Application('bumpversion', trim(file_get_contents(__DIR__.'/../VERSION')));
$application->addCommand(new BumpCommand());
$application->addCommand(new InitCommand());
exit($application->run());
