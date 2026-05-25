<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RecommendationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A structured recommendation derived from a CheckIn.
 *
 * Produced by the RecommendationService, which combines deterministic
 * "tools" with an AI client. A CheckIn may be analysed more than once, so the
 * relation is many recommendations to one check-in.
 */
#[ORM\Entity(repositoryClass: RecommendationRepository::class)]
class Recommendation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CheckIn::class)]
    #[ORM\JoinColumn(nullable: false)]
    private CheckIn $checkIn;

    #[ORM\Column(length: 50)]
    private string $priority;

    #[ORM\Column(length: 50)]
    private string $riskLevel;

    #[ORM\Column(length: 255)]
    private string $nextAction;

    #[ORM\Column(type: Types::TEXT)]
    private string $reasoning;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        CheckIn $checkIn,
        string $priority,
        string $riskLevel,
        string $nextAction,
        string $reasoning,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->checkIn = $checkIn;
        $this->priority = $priority;
        $this->riskLevel = $riskLevel;
        $this->nextAction = $nextAction;
        $this->reasoning = $reasoning;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCheckIn(): CheckIn
    {
        return $this->checkIn;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function getRiskLevel(): string
    {
        return $this->riskLevel;
    }

    public function getNextAction(): string
    {
        return $this->nextAction;
    }

    public function getReasoning(): string
    {
        return $this->reasoning;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
