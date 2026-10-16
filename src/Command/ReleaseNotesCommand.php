<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Command;

use Janalis\Bumpversion\Release\ReleaseContext;
use Janalis\Bumpversion\Version\SemanticVersion;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ReleaseNotesCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('release:notes')->setDescription('Extract curated notes for a dated release.')
            ->addArgument('version', InputArgument::OPTIONAL, 'Version to extract; defaults to the configured version')
            ->addOption('configuration', 'c', InputOption::VALUE_REQUIRED, 'Project configuration', 'bumpversion.yaml')
            ->addOption('tag-prefix', null, InputOption::VALUE_REQUIRED, 'Tag prefix override')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'markdown or json', 'markdown');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!in_array($input->getOption('format'), ['markdown', 'json'], true)) {
            throw new \InvalidArgumentException('Format must be markdown or json.');
        }
        $context = new ReleaseContext($input->getOption('configuration'));
        $version = null !== $input->getArgument('version') ? SemanticVersion::parse($input->getArgument('version')) : $context->config->version;
        $notes = $context->notes($version);
        $text = 'json' === $input->getOption('format') ? json_encode(['version' => (string) $version, 'tag' => $context->prefix($input->getOption('tag-prefix')).$version, 'prerelease' => null !== $version->preRelease, 'notes' => $notes], JSON_THROW_ON_ERROR) : $notes;
        $output->write($text.(str_ends_with($text, "\n") ? '' : "\n"), false, OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}
