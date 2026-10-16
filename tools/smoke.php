<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';

$root = dirname(__DIR__);
$temporary = sys_get_temp_dir().'/bumpversion-smoke-'.bin2hex(random_bytes(8));
mkdir($temporary, 0700);
$remove = static function (string $path) use (&$remove): void {
    foreach (new FilesystemIterator($path) as $file) {
        if ($file->isDir() && !$file->isLink()) {
            $remove($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    rmdir($path);
};
$run = static function (array $arguments, string $cwd) {
    return (new Process($arguments, $cwd))->mustRun()->getOutput();
};
try {
    $manifest = file($root.'/dist/manifest.txt', FILE_IGNORE_NEW_LINES);
    foreach ($manifest as $entry) {
        if (preg_match('~(?:^|/)(?:tests?|Tests?|docs?|Docs?|\.git|\.cache|\.env(?:\.[^/]*)?|auth\.json|\.DS_Store|phpunit|phpstan|friendsofphp)(?:/|$)~', $entry)) {
            throw new RuntimeException('Unexpected archive entry: '.$entry);
        }
    }
    $phar = new Phar($root.'/dist/bumpversion.phar');
    foreach (new RecursiveIteratorIterator($phar) as $file) {
        $contents = $file->getContent();
        foreach (array_filter([$root, getenv('HOME') ?: null]) as $privatePath) {
            if (str_contains($contents, $privatePath)) {
                throw new RuntimeException('Local path found in release archive.');
            }
        }
    }
    $expected = hash_file('sha256', $root.'/dist/bumpversion.phar').'  bumpversion.phar'."\n";
    if (file_get_contents($root.'/dist/SHA256SUMS') !== $expected) {
        throw new RuntimeException('PHAR checksum mismatch.');
    }
    $source = $temporary.'/source';
    mkdir($source);
    foreach ($manifest as $entry) {
        if (!(str_starts_with($entry, 'src/') || str_starts_with($entry, 'bin/') || in_array($entry, ['composer.json', 'LICENSE', 'VERSION'], true))) {
            continue;
        }
        $destination = $source.'/'.$entry;
        if (!is_dir(dirname($destination))) {
            mkdir(dirname($destination), 0755, true);
        }
        copy($root.'/'.$entry, $destination);
    }
    file_put_contents($temporary.'/composer.json', json_encode([
        'name' => 'example/release-smoke',
        'require' => ['janalis/bumpversion' => '@dev'],
        'repositories' => [['type' => 'path', 'url' => $source, 'options' => ['symlink' => false]]],
        'config' => ['bin-dir' => 'tools/bin', 'platform' => ['php' => PHP_VERSION]],
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    $run(['composer', 'install', '--no-interaction', '--no-plugins', '--no-scripts', '--no-progress'], $temporary);
    $consumer = $temporary.'/tools/bin/bumpversion';
    foreach ([$consumer, $root.'/dist/bumpversion.phar'] as $index => $binary) {
        $versionOutput = $run([PHP_BINARY, $binary, '--version'], $temporary);
        if (!str_contains($versionOutput, trim(file_get_contents($root.'/VERSION')))) {
            throw new RuntimeException('Installed binary reports the wrong release version.');
        }
        $workspace = $temporary.'/workspace-'.$index;
        mkdir($workspace);
        file_put_contents($workspace.'/VERSION', "1.2.3\n");
        $run([PHP_BINARY, $binary, 'init', '--no-interaction', '--current-version=1.2.3', '--path=VERSION'], $workspace);
        foreach (['release:prepare', 'release:notes', 'release:github', 'release:packagist'] as $command) {
            $run([PHP_BINARY, $binary, $command, '--help'], $workspace);
        }
        $run([PHP_BINARY, $binary, 'release:packagist', '--package=example/package', '--commit='.str_repeat('1', 40), '--dry-run'], $workspace);
        file_put_contents($workspace.'/notes.md', "# Synthetic release\n\n- A curated feature.\n");
        $run([PHP_BINARY, $binary, 'release:github', '--repository=example/package', '--notes-file=notes.md', '--asset=VERSION', '--commit='.str_repeat('1', 40), '--dry-run'], $workspace);
        $run([PHP_BINARY, $binary, 'bump', '--dry-run'], $workspace);
        if ("1.2.3\n" !== file_get_contents($workspace.'/VERSION')) {
            throw new RuntimeException('Dry run modified a file.');
        }
        $run([PHP_BINARY, $binary, 'bump', '--configuration='.$workspace.'/bumpversion.yaml'], $temporary);
        if ("1.2.4\n" !== file_get_contents($workspace.'/VERSION')) {
            throw new RuntimeException('Installed binary did not update the version.');
        }
    }
    echo "Composer consumer, custom bin directory, PHAR, manifest, privacy scan, and checksum: OK\n";
} finally {
    $remove($temporary);
}
