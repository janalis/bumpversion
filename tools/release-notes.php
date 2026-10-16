<?php

declare(strict_types=1);

use Janalis\Bumpversion\Version\SemanticVersion;
use Symfony\Component\Yaml\Yaml;

require __DIR__.'/../vendor/autoload.php';

$root = dirname(__DIR__);
$tag = $argv[1] ?? '';
if (!str_starts_with($tag, 'v')) {
    throw new InvalidArgumentException('Release tags must start with v.');
}
$version = SemanticVersion::parse(substr($tag, 1));
$config = Yaml::parseFile($root.'/bumpversion.yaml.dist');
if (trim(file_get_contents($root.'/VERSION')) !== (string) $version || $config['bumpversion']['version'] !== (string) $version) {
    throw new RuntimeException('Tag, VERSION, and release configuration must agree.');
}
$contents = str_replace("\r\n", "\n", file_get_contents($root.'/CHANGELOG.md'));
$pattern = '/^## \['.preg_quote((string) $version, '/').'\] - (\d{4}-\d{2}-\d{2})\n(.*?)(?=^## |^\[[^\]]+\]:|\z)/ms';
if (!preg_match($pattern, $contents, $match)) {
    throw new RuntimeException('No dated changelog section exists for this release.');
}
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $match[1]);
if (false === $date || $date->format('Y-m-d') !== $match[1]) {
    throw new RuntimeException('The release date is invalid.');
}
$body = trim($match[2]);
if ('' === $body) {
    throw new RuntimeException('The release must have curated changelog entries.');
}
echo '# '.$version.' - '.$match[1]."\n\n".$body."\n";
// Preserve reference-style links used by curated entries.
if (preg_match_all('/^\[[^\]]+\]:[^\n]+$/m', $contents, $references)) {
    echo "\n".implode("\n", $references[0])."\n";
}
