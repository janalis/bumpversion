<?php

return (new PhpCsFixer\Config())
    ->setCacheFile(__DIR__.'/.cache/php-cs-fixer')
    ->setRules(['@Symfony' => true])
    ->setFinder(PhpCsFixer\Finder::create()->in([__DIR__.'/src', __DIR__.'/tests', __DIR__.'/tools'])->append([__DIR__.'/bin/bumpversion', __DIR__.'/bin/bumpversion.php', __DIR__.'/create-phar.php']));
