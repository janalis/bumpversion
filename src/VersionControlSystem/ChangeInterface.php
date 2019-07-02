<?php

declare(strict_types=1);

namespace App\VersionControlSystem;

interface ChangeInterface
{
    public function __construct(string $identifier, string $subject, ?string $author = null, ?\DateTimeInterface $date = null);

    public function getAuthor(): ?string;

    public function getDate(): ?\DateTimeInterface;

    public function getIdentifier(): string;

    public function getSubject(): string;
}
