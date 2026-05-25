<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateCheckInRequest;
use App\Entity\CheckIn;
use App\Repository\CheckInRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Application service for check-ins. Holds the "what happens when" logic so
 * controllers stay thin and the same operations are reusable from CLI or tests.
 */
final class CheckInService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CheckInRepository $checkIns,
    ) {
    }

    public function create(CreateCheckInRequest $request): CheckIn
    {
        $checkIn = new CheckIn(
            energyLevel: (int) $request->energyLevel,
            focusGoal: $request->focusGoal,
            distractionRisk: $request->distractionRisk,
            notes: $request->notes,
        );

        $this->entityManager->persist($checkIn);
        $this->entityManager->flush();

        return $checkIn;
    }

    /**
     * @return CheckIn[]
     */
    public function recent(int $limit = 20): array
    {
        return $this->checkIns->findRecent($limit);
    }

    public function today(): ?CheckIn
    {
        return $this->checkIns->findLatestForToday();
    }
}
