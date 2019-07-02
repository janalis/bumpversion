<?php

declare(strict_types=1);

namespace App\Command;

use App\Configuration\YamlConfiguration;
use App\Version\SemanticVersion;
use App\Version\VersionInterface;
use App\VersionControlSystem\ChangeInterface;
use App\VersionControlSystem\GitVersionControlSystem;
use App\VersionControlSystem\VersionControlSystemRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class BumpCommand extends Command
{
    private const VERSIONS = [
        SemanticVersion::MAJOR,
        SemanticVersion::MINOR,
        SemanticVersion::PATCH,
    ];

    /**
     * {@inheritdoc}
     */
    protected static $defaultName = 'bump';

    /**
     * @var VersionControlSystemRepository
     */
    private $vcsRepository;

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        $this->vcsRepository = new VersionControlSystemRepository([
            new GitVersionControlSystem(),
        ]);
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Bumps version')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command allows you to bump a version...')

            ->addOption(
                'configuration',
                'c',
                InputOption::VALUE_REQUIRED,
                'read configuration from YAML file'
            )

            ->addOption(
                'pre-release',
                'p',
                InputOption::VALUE_REQUIRED,
                'the pre-release identifier (e.g "alpha", "beta", "rc")'
            )

            ->addOption(
                'type',
                't',
                InputOption::VALUE_REQUIRED,
                sprintf('the version type ("%s")', implode('", "', static::VERSIONS))
            )

            ->addOption(
                'version-control-system',
                'vcs',
                InputOption::VALUE_REQUIRED,
                sprintf('version control system ("%s")', implode('", "', $this->vcsRepository->getIdentifiers()))
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $preRelease = $input->getOption('pre-release');

        $type = $input->getOption('type');
        if ($type && !in_array($type, static::VERSIONS, true)) {
            throw new \RuntimeException(sprintf('Invalid type "%s" (type must be one of: "%s")', $type, implode('", "', static::VERSIONS)));
        }

        $vcs = null;
        if (($identifier = $input->getOption('version-control-system')) && !($vcs = $this->vcsRepository->find($identifier))) {
            throw new \RuntimeException(sprintf('Unknown vcs "%s" (vcs must be one of: "%s")', $identifier, implode('", "', $this->vcsRepository->getIdentifiers())));
        }
        if ($vcs && $vcs->isDirty()) {
            throw new \RuntimeException('Please commit/stash your changes before bumping version.');
        }

        $filename = $input->getOption('configuration') ?? 'bumpversion.yaml';
        if (!file_exists($filename)) {
            if (!file_exists("$filename.dist")) {
                if (!$input->isInteractive()) {
                    throw new \RuntimeException(sprintf('Configuration file "%s" not found in "%s"', $filename, getcwd()));
                }

                return $this->getApplication()->find('init')->execute($input, $output);
            }

            $filename = "$filename.dist";
        }
        $config = YamlConfiguration::parseFile($filename, SemanticVersion::class);

        $changelog = $config->getChangelog();
        $paths = $config->getPaths();
        $currentVersion = $config->getVersion();
        if (!$type && !$preRelease && (!$currentVersion || !$currentVersion->isPreRelease())) {
            $type = SemanticVersion::PATCH;
        }
        $nextVersion = $currentVersion ? $currentVersion->getNextVersion($type, $preRelease) : new SemanticVersion(1, 0, 0);

        if ($currentVersion) {
            $output->writeln('Bumping version...');
            $this->replaceInFiles($paths, $currentVersion, $nextVersion);
        }

        $config->setVersion($nextVersion);
        $config->save($filename);

        if ($vcs && $changelog) {
            $this->updateChangelog($changelog, $nextVersion, $vcs->getChanges($currentVersion));
        }

        if ($vcs) {
            $vcs->release((string) $currentVersion, (string) $nextVersion);
        }

        return 0;
    }

    /**
     * Updates current version to next version in files contained in paths.
     *
     * @param array            $paths          paths containing files to update
     * @param VersionInterface $currentVersion current version
     * @param VersionInterface $nextVersion    next version
     */
    private function replaceInFiles(array $paths, VersionInterface $currentVersion, VersionInterface $nextVersion): void
    {
        $finder = new Finder();
        $finder
            ->in(getcwd())
            ->path(array_map(static function (string $path): string {
                return sprintf('/^%s/', preg_quote($path, '/'));
            }, $paths))
            ->contains((string) $currentVersion)
        ;

        /** @var SplFileInfo $path */
        foreach ($finder->files() as $path) {
            file_put_contents($path->getPathname(), preg_replace(
                sprintf('/%s/', preg_quote((string) $currentVersion, '/')),
                (string) $nextVersion,
                file_get_contents($path->getPathname())
            ));
        }
    }

    /**
     * Updates changelog appending changes at version.
     *
     * @param string           $filename changelog filename
     * @param VersionInterface $version  version
     * @param array            $changes  changes
     */
    private function updateChangelog(string $filename, VersionInterface $version, array $changes): void
    {
        file_put_contents($filename, implode("\r\n", array_merge([
            '',
            sprintf('## %s', $version),
            '',
        ], array_map(static function (ChangeInterface $change): string {
            return sprintf('* [%s](%s) (%s)', $change->getSubject(), $change->getIdentifier(), $change->getAuthor());
        }, $changes), [
            '',
        ])), FILE_APPEND | LOCK_EX);
    }
}
