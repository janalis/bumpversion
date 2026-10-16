<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Command;

use Janalis\Bumpversion\Configuration\YamlConfiguration;
use Janalis\Bumpversion\Release\ReleasePlanner;
use Janalis\Bumpversion\Version\SemanticVersion;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

final class InitCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('init')->setDescription('Create a YAML configuration without overwriting an existing file.')
            ->addOption('configuration', 'c', InputOption::VALUE_REQUIRED, 'Configuration destination', 'bumpversion.yaml')
            ->addOption('current-version', null, InputOption::VALUE_REQUIRED, 'Current semantic version')
            ->addOption('path', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'File or directory relative to the configuration (repeatable)')
            ->addOption('changelog', null, InputOption::VALUE_REQUIRED, 'Existing changelog file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filename = $input->getOption('configuration');
        if (str_starts_with($filename, '~/') && false !== getenv('HOME')) {
            $filename = getenv('HOME').substr($filename, 1);
        }
        if (file_exists($filename) || is_link($filename)) {
            throw new \RuntimeException('Configuration already exists.');
        }
        $root = realpath(dirname($filename));
        if (false === $root) {
            throw new \RuntimeException('Configuration directory must exist.');
        }
        $version = $input->getOption('current-version');
        $paths = $input->getOption('path');
        $changelog = $input->getOption('changelog');
        if (!$input->isInteractive() && (null === $version || [] === $paths)) {
            throw new \InvalidArgumentException('Noninteractive init requires --current-version and at least one --path.');
        }
        $helper = new QuestionHelper();
        if (null === $version) {
            $question = new Question('Current version [1.0.0]: ', '1.0.0');
            $question->setValidator(static fn ($value) => (string) SemanticVersion::parse($value));
            $version = $helper->ask($input, $output, $question);
        }
        if ($input->isInteractive() && null === $changelog) {
            $changelog = $helper->ask($input, $output, new Question('Changelog file (leave empty to skip): '));
        }
        if ([] === $paths) {
            while (($path = $helper->ask($input, $output, new Question('File/directory to update (leave empty to finish): '))) !== null && '' !== $path) {
                ReleasePlanner::path($root, $path);
                $paths[] = $path;
            }
        }
        if ([] === $paths) {
            throw new \InvalidArgumentException('At least one path is required.');
        }
        foreach ($paths as $path) {
            ReleasePlanner::path($root, $path);
        }
        if (null !== $changelog && !is_file(ReleasePlanner::path($root, $changelog))) {
            throw new \InvalidArgumentException('Changelog must be an existing regular file.');
        }
        $config = new YamlConfiguration($paths, SemanticVersion::parse($version), $changelog);
        $handle = fopen($filename, 'x');
        if (false === $handle) {
            throw new \RuntimeException('Cannot create configuration.');
        }
        try {
            $contents = $config->contents();
            if (fwrite($handle, $contents) !== strlen($contents)) {
                throw new \RuntimeException('Cannot write configuration.');
            }
        } catch (\Throwable $exception) {
            fclose($handle);
            unlink($filename);
            throw $exception;
        }
        fclose($handle);
        $output->writeln('Configuration created.');

        return Command::SUCCESS;
    }
}
