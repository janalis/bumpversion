<?php

declare(strict_types=1);

// remove development dependencies
shell_exec('composer install --no-dev --optimize-autoloader');

// the php.ini setting phar.readonly must be set to 0ff
$pharFile = 'bumpversion.phar';

// clean up
if (file_exists($pharFile)) {
    unlink($pharFile);
}

// create phar
$phar = new Phar($pharFile);

// start buffering. Mandatory to modify stub to add shebang
$phar->startBuffering();

// create the default stub from main.php entrypoint
$defaultStub = $phar::createDefaultStub('/bin/bumpversion.php');

// creating our library using whole directory
$gitignore = array_filter(array_map(static function (string $line): ?string {
    return trim(preg_replace('/(#.*)$/', '', $line));
}, file('.gitignore')));
$gitignore[] = basename(__FILE__);
$gitignore[] = '.idea';
$gitignore[] = 'tests';
$gitignore[] = 'phpunit.xml.dist';
$gitignore[] = '!composer.json';

$phar->buildFromDirectory('.', sprintf(
    '/^\.\/((?!%s)|%s).*$/',
    implode('|', array_map(static function (string $file): string {
        return str_replace('\\*', '.*', preg_quote($file, '/'));
    }, array_filter($gitignore, static function (string $file): bool {
        return 0 !== strpos($file, '!');
    }))),
    implode('|', array_map(static function (string $file): string {
        return str_replace('\\*', '.*', preg_quote(substr($file, 1), '/'));
    }, array_filter($gitignore, static function (string $file): bool {
        return 0 === strpos($file, '!');
    })))
));

// customize the stub to add the shebang
$stub = "#!/usr/bin/php\n$defaultStub";

// add the stub
$phar->setStub($stub);

// stop buffering
$phar->stopBuffering();

// plus - compressing it into gzip
$phar->compressFiles(Phar::GZ);

// make it executable
shell_exec("chmod +x $pharFile");

echo "$pharFile successfully created" . PHP_EOL;
