<?php

declare(strict_types=1);

namespace App\Service;

use App\Agent\ToolRegistry;
use App\Ai\AiRecommendationClientInterface;
use App\Ai\Exception\AiClientException;
use App\Ai\FakeRecommendationClient;
use App\Entity\CheckIn;
use App\Entity\Recommendation;
use App\Exception\CheckInNotFoundException;
use App\Repository\CheckInRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates recommendation generation.
 *
 * Flow: load the check-in -> run deterministic tools to gather signals ->
 * ask the AI client for a structured draft -> persist it. If the AI client
 * fails for any reason, we fall back to the deterministic FakeRecommendationClient
 * so the endpoint always returns a usable result.
 *
 * The service depends on the AiRecommendationClientInterface, not on OpenAI.
 */
final class RecommendationService
{
    public function __construct(
        private readonly CheckInRepository $checkIns,
        private readonly EntityManagerInterface $entityManager,
        private readonly ToolRegistry $tools,
        private readonly AiRecommendationClientInterface $aiClient,
        private readonly FakeRecommendationClient $fallbackClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function generateForCheckIn(int $checkInId): Recommendation
    {
        $checkIn = $this->checkIns->find($checkInId);
        if (null === $checkIn) {
            throw CheckInNotFoundException::withId($checkInId);
        }

        $findings = $this->gatherToolFindings($checkIn);

        try {
            $draft = $this->aiClient->generate($checkIn, $findings);
        } catch (AiClientException $e) {
            // Expected when no API key is configured or the call fails: degrade gracefully.
            $this->logger->warning('AI recommendation failed; using deterministic fallback.', [
                'checkInId' => $checkInId,
                'error' => $e->getMessage(),
            ]);
            $draft = $this->fallbackClient->generate($checkIn, $findings);
        }

        $recommendation = new Recommendation(
            checkIn: $checkIn,
            priority: $draft->priority,
            riskLevel: $draft->riskLevel,
            nextAction: $draft->nextAction,
            reasoning: $draft->reasoning,
        );

        $this->entityManager->persist($recommendation);
        $this->entityManager->flush();

        return $recommendation;
    }

    /**
     * Run the agent tools and return their findings keyed by tool name.
     *
     * @return array<string, array<string, mixed>>
     */
    private function gatherToolFindings(CheckIn $checkIn): array
    {
        $risk = $this->tools->get('score_focus_risk')->execute([
            'energyLevel' => $checkIn->getEnergyLevel(),
            'distractionRisk' => $checkIn->getDistractionRisk(),
        ]);

        $action = $this->tools->get('suggest_next_action')->execute([
            'focusGoal' => $checkIn->getFocusGoal(),
            'riskLevel' => $risk['riskLevel'] ?? 'low',
        ]);

        return [
            'score_focus_risk' => $risk,
            'suggest_next_action' => $action,
        ];
    }
}
