<?php

declare(strict_types=1);

use Janalis\Bumpversion\Version\SemanticVersion;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';

$version = (string) SemanticVersion::parse(ltrim($argv[1] ?? '', 'v'));
$expected = $argv[2] ?? '';
if (!preg_match('/^[a-f0-9]{40}$/D', $expected)) {
    throw new InvalidArgumentException('Expected a full release commit SHA.');
}
$deadline = microtime(true) + 300;
do {
    $process = new Process(['curl', '--fail', '--silent', '--show-error', '--max-time', '20', 'https://repo.packagist.org/p2/janalis/bumpversion.json']);
    $process->run();
    if ($process->isSuccessful()) {
        $data = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        $previous = [];
        foreach ($data['packages']['janalis/bumpversion'] ?? [] as $entry) {
            // Packagist's Composer 2 format inherits fields from the preceding entry.
            foreach ($entry as $key => $value) {
                if ('__unset' === $value) {
                    unset($previous[$key]);
                } else {
                    $previous[$key] = $value;
                }
            }
            if (ltrim($previous['version'] ?? '', 'v') === $version) {
                if (($previous['source']['reference'] ?? null) !== $expected) {
                    throw new RuntimeException('Packagist version points at an unexpected commit.');
                }
                echo 'Packagist indexed '.$version.' at '.$expected."\n";
                exit(0);
            }
        }
    }
    fwrite(STDERR, 'Waiting for Packagist to index '.$version."...\n");
    sleep(10);
} while (microtime(true) < $deadline);
throw new RuntimeException('Packagist has not indexed the release. Check the GitHub webhook and rerun this job.');
