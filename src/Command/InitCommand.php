<?php

declare(strict_types=1);

namespace App\Command;

use App\Configuration\YamlConfiguration;
use App\Version\SemanticVersion;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class InitCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected static $defaultName = 'init';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Creates configuration file')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command allows you to create a bumpversion configuration file...')

            ->addOption(
                'configuration',
                'c',
                InputOption::VALUE_REQUIRED,
                'write configuration to YAML file'
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $filename = $input->getOption('configuration') ?? 'bumpversion.yaml';
        $helper = $this->getHelper('question');

        $question = new Question('What is the current version of your application? ', '1.0.0');
        $version = SemanticVersion::parse($helper->ask($input, $output, $question));

        $question = new Question('Which changelog file should be updated? (press return to add a path, leave empty to continue) ');
        $changelog = null;
        if ($path = $helper->ask($input, $output, $question)) {
            if (!file_exists($path)) {
                $output->writeln(sprintf('File "%s" does not exist', $path));
            }
            $changelog = $path;
        }

        $question = new Question('In which directory/file the version should be bumped? (press return to add a path, leave empty to continue) ');
        $paths = [];
        while ($path = $helper->ask($input, $output, $question)) {
            if (!file_exists($path)) {
                $output->writeln(sprintf('File "%s" does not exist', $path));
            }
            $paths[] = $path;
        }

        $config = new YamlConfiguration($paths, $version, $changelog);
        $config->save($filename);

        return 0;
    }
}
