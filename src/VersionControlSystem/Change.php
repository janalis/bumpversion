<?php

declare(strict_types=1);

namespace App\VersionControlSystem;

class Change implements ChangeInterface
{
    /**
     * @var string
     */
    private $author;

    /**
     * @var \DateTimeInterface
     */
    private $date;

    /**
     * @var string
     */
    private $identifier;

    /**
     * @var string
     */
    private $subject;

    public function __construct(string $identifier, string $subject, ?string $author = null, ?\DateTimeInterface $date = null)
    {
        $this->author = $author;
        $this->date = $date;
        $this->identifier = $identifier;
        $this->subject = $subject;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }
}
