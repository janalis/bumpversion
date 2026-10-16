<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Command;

use Janalis\Bumpversion\Http\ApiClient;
use Janalis\Bumpversion\Http\ClientFactory;
use Janalis\Bumpversion\Publishing\GitHubPublisher;
use Janalis\Bumpversion\Release\ReleaseContext;
use Janalis\Bumpversion\Version\SemanticVersion;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class GitHubReleaseCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('release:github')->setDescription('Publish verified assets and curated notes to an existing GitHub tag.')
            ->addOption('configuration', 'c', InputOption::VALUE_REQUIRED, 'Project configuration', 'bumpversion.yaml')
            ->addOption('repository', null, InputOption::VALUE_REQUIRED, 'GitHub owner/repository')
            ->addOption('release-version', null, InputOption::VALUE_REQUIRED, 'Release version; defaults to configuration')
            ->addOption('tag-prefix', null, InputOption::VALUE_REQUIRED, 'Tag prefix override')
            ->addOption('commit', null, InputOption::VALUE_REQUIRED, 'Expected release commit; defaults to HEAD')
            ->addOption('notes-file', null, InputOption::VALUE_REQUIRED, 'Curated release notes file; defaults to changelog extraction')
            ->addOption('asset', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Asset path relative to configuration directory (repeatable)')
            ->addOption('http-client', null, InputOption::VALUE_REQUIRED, 'auto, symfony, or guzzle')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate locally without API requests');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = new ReleaseContext($input->getOption('configuration'));
        $version = null !== $input->getOption('release-version') ? SemanticVersion::parse($input->getOption('release-version')) : $context->config->version;
        if (0 !== $version->compare($context->config->version) || (string) $version !== (string) $context->config->version) {
            throw new \InvalidArgumentException('Publishing version must match the prepared configuration.');
        }
        $repository = $input->getOption('repository') ?? $context->config->publishing['github_repository'] ?? '';
        $prefix = $context->prefix($input->getOption('tag-prefix'));
        $notesFile = $input->getOption('notes-file');
        $notes = null !== $notesFile ? file_get_contents($this->file($context->root, $notesFile)) : $context->notes($version);
        if (false === $notes) {
            throw new \RuntimeException('Cannot read release notes.');
        }
        $files = array_map(fn (string $file) => $this->file($context->root, $file), $input->getOption('asset'));
        $client = ClientFactory::create($input->getOption('http-client') ?? $context->config->publishing['http_client'] ?? 'auto', $context->root);
        $token = getenv('GH_TOKEN') ?: getenv('GITHUB_TOKEN') ?: null;
        $url = (new GitHubPublisher(new ApiClient($client)))->publish($repository, $prefix.$version, $version, $context->commit($input->getOption('commit')), $notes, $files, $token, (bool) $input->getOption('dry-run'));
        $output->writeln(($input->getOption('dry-run') ? 'Would publish ' : 'Published ').$url, OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }

    private function file(string $root, string $file): string
    {
        $path = str_starts_with($file, DIRECTORY_SEPARATOR) ? $file : $root.DIRECTORY_SEPARATOR.$file;
        if (!is_file($path) || is_link($path) || !is_readable($path)) {
            throw new \InvalidArgumentException('Notes and assets must be readable regular files.');
        }

        return $path;
    }
}
