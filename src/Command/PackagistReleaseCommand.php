<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Command;

use Janalis\Bumpversion\Http\ApiClient;
use Janalis\Bumpversion\Http\ClientFactory;
use Janalis\Bumpversion\Publishing\PackagistPublisher;
use Janalis\Bumpversion\Release\ReleaseContext;
use Janalis\Bumpversion\Version\SemanticVersion;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class PackagistReleaseCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('release:packagist')->setDescription('Refresh or wait for Packagist and verify the release commit.')
            ->addOption('configuration', 'c', InputOption::VALUE_REQUIRED, 'Project configuration', 'bumpversion.yaml')
            ->addOption('package', null, InputOption::VALUE_REQUIRED, 'Packagist vendor/package; defaults to publishing settings or composer.json')
            ->addOption('release-version', null, InputOption::VALUE_REQUIRED, 'Version to verify; defaults to configuration')
            ->addOption('commit', null, InputOption::VALUE_REQUIRED, 'Expected commit; defaults to HEAD')
            ->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'Maximum polling duration in seconds (0 to 3600)', '300')
            ->addOption('http-client', null, InputOption::VALUE_REQUIRED, 'auto, symfony, or guzzle')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate locally without API requests');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = new ReleaseContext($input->getOption('configuration'));
        $package = $context->package($input->getOption('package'));
        $version = null !== $input->getOption('release-version') ? SemanticVersion::parse($input->getOption('release-version')) : $context->config->version;
        $timeout = $input->getOption('timeout');
        if (!is_string($timeout) || !ctype_digit($timeout) || strlen($timeout) > 4) {
            throw new \InvalidArgumentException('Timeout must be a whole number of seconds from 0 to 3600.');
        }
        $client = ClientFactory::create($input->getOption('http-client') ?? $context->config->publishing['http_client'] ?? 'auto', $context->root);
        (new PackagistPublisher(new ApiClient($client)))->publish($package, $version, $context->commit($input->getOption('commit')), (int) $timeout, getenv('PACKAGIST_USERNAME') ?: null, getenv('PACKAGIST_TOKEN') ?: null, (bool) $input->getOption('dry-run'));
        $output->writeln(($input->getOption('dry-run') ? 'Would refresh or verify ' : 'Verified ').$package.' '.$version, OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}
