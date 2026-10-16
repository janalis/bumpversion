<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Command;

use Janalis\Bumpversion\Changelog\KeepAChangelog;
use Janalis\Bumpversion\Configuration\YamlConfiguration;
use Janalis\Bumpversion\Release\ReleasePlanner;
use Janalis\Bumpversion\Version\SemanticVersion;
use Janalis\Bumpversion\VersionControlSystem\GitVersionControlSystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class BumpCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('bump')->setDescription('Update configured version strings and optionally prepare a Git release.')
            ->addOption('configuration', 'c', InputOption::VALUE_REQUIRED, 'YAML configuration file', 'bumpversion.yaml')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'major, minor, or patch')
            ->addOption('pre-release', 'p', InputOption::VALUE_REQUIRED, 'Named prerelease identifier, e.g. alpha')
            ->addOption('new-version', null, InputOption::VALUE_REQUIRED, 'Explicit target semantic version')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without writing files, commits, or tags')
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'Changelog release date (YYYY-MM-DD; defaults to today in UTC)')
            ->addOption('tag-prefix', null, InputOption::VALUE_REQUIRED, 'Git tag prefix (inferred from the changelog comparison link, otherwise empty)')
            ->addOption('commit', null, InputOption::VALUE_NONE, 'Commit only the files updated by this command')
            ->addOption('tag', null, InputOption::VALUE_NONE, 'Tag HEAD (after committing when --commit is also supplied)')
            ->addOption('version-control-system', 'vcs', InputOption::VALUE_REQUIRED, 'Legacy alias: git enables both --commit and --tag');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $target = $input->getOption('new-version');
        if (null !== $target && (null !== $input->getOption('type') || null !== $input->getOption('pre-release'))) {
            throw new \InvalidArgumentException('--new-version cannot be combined with --type or --pre-release.');
        }
        $vcs = $input->getOption('version-control-system');
        if (null !== $vcs && 'git' !== $vcs) {
            throw new \InvalidArgumentException('Only git is supported as a version control system.');
        }
        $filename = YamlConfiguration::resolve($input->getOption('configuration'));
        $config = YamlConfiguration::load($filename);
        $current = (string) $config->version;
        $next = null !== $target ? SemanticVersion::parse($target) : $config->version->next($input->getOption('type'), $input->getOption('pre-release'));
        if ($next->compare($config->version) <= 0) {
            throw new \InvalidArgumentException('Target version must have higher SemVer precedence than the current version.');
        }
        $prefix = $input->getOption('tag-prefix');
        if (null === $prefix && null !== $config->changelog) {
            $contents = file_get_contents(ReleasePlanner::path(dirname($filename), $config->changelog));
            if (false === $contents) {
                throw new \RuntimeException('Cannot read changelog.');
            }
            $prefix = KeepAChangelog::tagPrefix($contents, $current);
        }
        $prefix ??= '';
        $tagName = $prefix.$next;
        if (null !== $input->getOption('date') && null === $config->changelog) {
            throw new \InvalidArgumentException('--date requires a configured changelog.');
        }
        $commit = $input->getOption('commit') || 'git' === $vcs;
        $tag = $input->getOption('tag') || 'git' === $vcs;
        $git = $commit || $tag ? new GitVersionControlSystem(dirname($filename)) : null;
        $git?->preflight($tagName, $tag, $commit);
        $planner = new ReleasePlanner();
        $plan = $planner->plan($filename, $config, $next, $input->getOption('date'), $prefix);
        if ($commit) {
            $git?->validateFiles($plan->files());
        }
        $output->writeln("$current → $next", OutputInterface::OUTPUT_RAW);
        foreach ($planner->reports as $report) {
            $output->writeln($report, OutputInterface::OUTPUT_RAW);
        }
        foreach ($plan->files() as $file) {
            $output->writeln('Update '.substr($file, strlen(dirname($filename)) + 1), OutputInterface::OUTPUT_RAW);
        }
        if ($commit) {
            $output->writeln('Create release commit.');
        }
        if ($tag) {
            $output->writeln('Tag '.($commit ? 'release commit' : 'existing HEAD (updated files remain uncommitted)').' as '.$tagName, OutputInterface::OUTPUT_RAW);
        }
        if ($input->getOption('dry-run')) {
            $output->writeln('Dry run: nothing changed.');

            return Command::SUCCESS;
        }
        $plan->apply();
        $git?->release($plan->files(), $current, (string) $next, $commit, $tag, $tagName);

        return Command::SUCCESS;
    }
}
