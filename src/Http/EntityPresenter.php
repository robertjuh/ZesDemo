<?php

declare(strict_types=1);

namespace App\Http;

use App\Entity\CheckIn;
use App\Entity\Recommendation;

/**
 * Single place that turns entities into the JSON shapes the API exposes.
 *
 * Centralising this keeps controllers thin and the wire format consistent,
 * and avoids leaking Doctrine objects straight onto the response.
 */
final class EntityPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function checkIn(CheckIn $checkIn): array
    {
        return [
            'id' => $checkIn->getId(),
            'energyLevel' => $checkIn->getEnergyLevel(),
            'focusGoal' => $checkIn->getFocusGoal(),
            'distractionRisk' => $checkIn->getDistractionRisk(),
            'notes' => $checkIn->getNotes(),
            'createdAt' => $checkIn->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recommendation(Recommendation $recommendation): array
    {
        return [
            'id' => $recommendation->getId(),
            'checkInId' => $recommendation->getCheckIn()->getId(),
            'priority' => $recommendation->getPriority(),
            'riskLevel' => $recommendation->getRiskLevel(),
            'nextAction' => $recommendation->getNextAction(),
            'reasoning' => $recommendation->getReasoning(),
            'createdAt' => $recommendation->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
