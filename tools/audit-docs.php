<?php

declare(strict_types=1);

/*
 * Runs npm audit for the documentation tooling and fails on every advisory
 * except those listed here. Only list an advisory that has no patched release,
 * and remove it as soon as the audit stops reporting it.
 */
const ACCEPTED_ADVISORIES = [
    'GHSA-fc5q-39xc-vjpf' => 'picomatch',
];

$fail = static function (string $message): never {
    fwrite(STDERR, $message."\n");
    exit(1);
};

$options = getopt('', ['report:']);
$report = isset($options['report']) && is_string($options['report'])
    ? @file_get_contents($options['report'])
    : shell_exec('npm audit --json --prefix '.escapeshellarg(dirname(__DIR__).'/docs'));
$audit = is_string($report) ? json_decode($report, true) : null;
if (!is_array($audit) || isset($audit['error']) || !is_array($audit['vulnerabilities'] ?? null)) {
    $fail('The documentation audit did not return a usable report.');
}

$accepted = [];
$rejected = [];
foreach ($audit['vulnerabilities'] as $package => $vulnerability) {
    $via = is_array($vulnerability) ? ($vulnerability['via'] ?? null) : null;
    if (!is_array($via) || [] === $via) {
        $rejected[] = $package.': no advisory details';
        continue;
    }
    foreach ($via as $source) {
        if (is_string($source) && isset($audit['vulnerabilities'][$source])) {
            continue;
        }
        $advisory = is_array($source) && is_string($source['url'] ?? null) ? basename($source['url']) : null;
        if (null !== $advisory && (ACCEPTED_ADVISORIES[$advisory] ?? null) === ($source['name'] ?? null)) {
            $accepted[$advisory] = true;
            continue;
        }
        $rejected[] = $package.': '.($advisory ?? 'unknown advisory');
    }
}

if ([] !== $rejected) {
    $fail("Unaccepted documentation advisories:\n- ".implode("\n- ", array_unique($rejected)));
}
$stale = array_diff_key(ACCEPTED_ADVISORIES, $accepted);
if ([] !== $stale) {
    $fail('Remove accepted advisories that are no longer reported: '.implode(', ', array_keys($stale)));
}

echo 'Documentation audit: only accepted advisories reported ('.implode(', ', array_keys($accepted)).").\n";
