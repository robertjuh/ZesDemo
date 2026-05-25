<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CheckInRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A single daily check-in submitted by the user.
 *
 * The entity is intentionally "always valid": required fields are passed
 * through the constructor, so an instance cannot exist in a half-built state.
 * HTTP-level validation lives on the request DTO, not here.
 */
#[ORM\Entity(repositoryClass: CheckInRepository::class)]
class CheckIn
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $energyLevel;

    #[ORM\Column(length: 255)]
    private string $focusGoal;

    #[ORM\Column(length: 255)]
    private string $distractionRisk;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        int $energyLevel,
        string $focusGoal,
        string $distractionRisk,
        ?string $notes = null,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->energyLevel = $energyLevel;
        $this->focusGoal = $focusGoal;
        $this->distractionRisk = $distractionRisk;
        $this->notes = $notes;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEnergyLevel(): int
    {
        return $this->energyLevel;
    }

    public function getFocusGoal(): string
    {
        return $this->focusGoal;
    }

    public function getDistractionRisk(): string
    {
        return $this->distractionRisk;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
