<?php

declare(strict_types=1);

namespace Janalis\Bumpversion\Http;

final class ApiException extends \RuntimeException
{
    public function __construct(public readonly int $status)
    {
        parent::__construct('Publishing API returned HTTP '.$status.'.');
    }
}
