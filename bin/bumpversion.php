<?php

require __DIR__.'/../vendor/autoload.php';

use App\Command\BumpCommand;
use App\Command\InitCommand;
use Symfony\Component\Console\Application;

$application = new Application(basename(__FILE__, '.php'));

$application->add(new BumpCommand());
$application->add(new InitCommand());

$application->run();
