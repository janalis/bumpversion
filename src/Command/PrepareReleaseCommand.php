<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Command;

use Janalis\Bumpversion\Release\ReleaseContext;
use Janalis\Bumpversion\Release\ReleasePlanner;
use Janalis\Bumpversion\Release\VersionTransition;
use Janalis\Bumpversion\VersionControlSystem\GitVersionControlSystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class PrepareReleaseCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('release:prepare')->setDescription('Prepare configured release files without committing or publishing.')
            ->addArgument('version', InputArgument::REQUIRED, 'Exact SemVer version, major, minor, or patch')
            ->addOption('configuration', 'c', InputOption::VALUE_REQUIRED, 'Project configuration', 'bumpversion.yaml')
            ->addOption('tag-prefix', null, InputOption::VALUE_REQUIRED, 'Tag prefix override')
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'Changelog date in YYYY-MM-DD')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without writing files')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'text, json, or github', 'text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption('format');
        if (!in_array($format, ['text', 'json', 'github'], true)) {
            throw new \InvalidArgumentException('Format must be text, json, or github.');
        }
        $context = new ReleaseContext($input->getOption('configuration'));
        $requested = $input->getArgument('version');
        $part = in_array($requested, ['major', 'minor', 'patch'], true) ? $requested : null;
        $next = VersionTransition::next($context->config->version, $part, null, null === $part ? $requested : null);
        $prefix = $context->prefix($input->getOption('tag-prefix'));
        $tag = $prefix.$next;
        (new GitVersionControlSystem($context->root))->preflight($tag, true);
        $planner = new ReleasePlanner();
        $plan = $planner->plan($context->filename, $context->config, $next, $input->getOption('date'), $prefix);
        if (!$input->getOption('dry-run')) {
            $plan->apply();
        }
        $data = ['version' => (string) $next, 'tag' => $tag, 'prerelease' => null !== $next->preRelease];
        if ('github' === $format) {
            foreach ($data as $key => $value) {
                $output->writeln($key.'='.(is_bool($value) ? ($value ? 'true' : 'false') : $value), OutputInterface::OUTPUT_RAW);
            }
        } elseif ('json' === $format) {
            $output->writeln(json_encode($data + ['dry_run' => (bool) $input->getOption('dry-run'), 'files' => array_map(fn ($path) => substr($path, strlen($context->root) + 1), $plan->files())], JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);
        } else {
            $output->writeln(($input->getOption('dry-run') ? 'Would prepare ' : 'Prepared ').$tag, OutputInterface::OUTPUT_RAW);
            foreach ($plan->files() as $file) {
                $output->writeln('Update '.substr($file, strlen($context->root) + 1), OutputInterface::OUTPUT_RAW);
            }
        }

        return Command::SUCCESS;
    }
}
