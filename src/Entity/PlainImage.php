<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PlainImageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlainImageRepository::class)]
class PlainImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 2000)]
    private ?string $link = null;

    #[ORM\Column(length: 2000, nullable: true)]
    private ?string $fileLocation = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(string $link): self
    {
        $this->link = $link;

        return $this;
    }

    public function getFileLocation(): ?string
    {
        return $this->fileLocation;
    }

    public function setFileLocation(?string $fileLocation): self
    {
        $this->fileLocation = $fileLocation;

        return $this;
    }
}
