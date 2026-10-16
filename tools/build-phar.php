<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';

if (false === Phar::canWrite()) {
    throw new RuntimeException('Run with php -d phar.readonly=0 tools/build-phar.php');
}
$root = dirname(__DIR__);
$stage = sys_get_temp_dir().'/bumpversion-build-'.bin2hex(random_bytes(8));
mkdir($stage, 0700);
$remove = static function (string $directory) use (&$remove): void {
    foreach (new FilesystemIterator($directory) as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            $remove($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($directory);
};
try {
    foreach (['composer.json', 'composer.lock', 'LICENSE'] as $file) {
        copy($root.'/'.$file, $stage.'/'.$file);
    }
    foreach (['src', 'bin'] as $directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), strlen($root) + 1);
            if ($file->isLink() || !$file->isFile() || !('src' === $directory && 'php' === $file->getExtension() || in_array($relative, ['bin/bumpversion', 'bin/bumpversion.php'], true))) {
                continue;
            }
            $destination = $stage.'/'.$relative;
            if (!is_dir(dirname($destination))) {
                mkdir(dirname($destination), 0755, true);
            }
            copy($file->getPathname(), $destination);
        }
    }
    $composer = getenv('BUMPVERSION_COMPOSER') ?: 'composer';
    (new Process([$composer, 'install', '--working-dir='.$stage, '--no-dev', '--no-plugins', '--no-scripts', '--prefer-dist', '--classmap-authoritative', '--no-interaction', '--no-progress']))->mustRun();
    $files = ['composer.json' => $stage.'/composer.json', 'composer.lock' => $stage.'/composer.lock', 'LICENSE' => $stage.'/LICENSE'];
    foreach (['src', 'bin'] as $directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage.'/'.$directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), strlen($stage) + 1);
            if (!$file->isLink() && $file->isFile() && ('src' === $directory && 'php' === $file->getExtension() || in_array($relative, ['bin/bumpversion', 'bin/bumpversion.php'], true))) {
                $files[$relative] = $file->getPathname();
            }
        }
    }
    // Only Composer-generated metadata and files belonging to locked production packages.
    $installed = json_decode(file_get_contents($stage.'/vendor/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);
    $directories = ['composer'];
    foreach ($installed['packages'] as $package) {
        $directories[] = $package['name'];
    }
    $files['vendor/autoload.php'] = $stage.'/vendor/autoload.php';
    foreach ($directories as $directory) {
        $base = $stage.'/vendor/'.$directory;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(), strlen($stage) + 1);
            if ($file->isLink() || !$file->isFile() || preg_match('~(?:^|/)(?:\.git|\.env(?:\.[^/]*)?|auth\.json|\.DS_Store|tests?|Tests?|docs?|Docs?)(?:/|$)~', $relative)) {
                continue;
            }
            $files[$relative] = $file->getPathname();
        }
    }
    ksort($files);
    if (!is_dir($root.'/dist')) {
        mkdir($root.'/dist', 0755);
    }
    $output = $root.'/dist/bumpversion.phar';
    $temporary = $stage.'/bumpversion.phar';
    $phar = new Phar($temporary);
    $phar->startBuffering();
    foreach ($files as $name => $path) {
        $phar->addFile($path, $name);
    }
    $phar->setStub("#!/usr/bin/env php\n<?php Phar::mapPhar('bumpversion.phar'); require 'phar://bumpversion.phar/bin/bumpversion.php'; __HALT_COMPILER();");
    $phar->setSignatureAlgorithm(Phar::SHA256);
    $phar->stopBuffering();
    copy($temporary, $output);
    chmod($output, 0755);
    file_put_contents($root.'/dist/SHA256SUMS', hash_file('sha256', $output)."  bumpversion.phar\n");
    file_put_contents($root.'/dist/manifest.txt', implode("\n", array_keys($files))."\n");
    echo "Built dist/bumpversion.phar, SHA256SUMS, and manifest.txt\n";
} finally {
    $remove($stage);
}
