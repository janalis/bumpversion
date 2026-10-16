<?php

declare(strict_types=1);

use Janalis\Bumpversion\Version\SemanticVersion;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';

$root = dirname(__DIR__);
$requested = $argv[1] ?? '';
if ('' === $requested) {
    throw new InvalidArgumentException('Usage: composer release:prepare -- X.Y.Z|major|minor|patch');
}
$current = SemanticVersion::parse(trim(file_get_contents($root.'/VERSION')));
$next = in_array($requested, ['major', 'minor', 'patch'], true) ? $current->next($requested) : SemanticVersion::parse($requested);
if ($next->compare($current) <= 0) {
    throw new InvalidArgumentException('The release must increase SemVer precedence.');
}
$tag = 'v'.$next;
$git = static fn (array $arguments): string => (new Process(['git', ...$arguments], $root))->mustRun()->getOutput();
if ('' !== trim($git(['status', '--porcelain', '--untracked-files=all']))) {
    throw new RuntimeException('Prepare releases from a clean working tree.');
}
if (in_array($tag, explode("\n", trim($git(['tag', '--list']))), true)) {
    throw new RuntimeException('Release tag already exists.');
}
(new Process([PHP_BINARY, $root.'/bin/bumpversion', 'bump', '--configuration='.$root.'/bumpversion.yaml.dist', '--new-version='.(string) $next, '--tag-prefix=v', '--no-interaction'], $root))->mustRun();
echo 'version='.$next."\n".'tag='.$tag."\n".'prerelease='.(null !== $next->preRelease ? 'true' : 'false')."\n";
