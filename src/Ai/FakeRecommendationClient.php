<?php

declare(strict_types=1);

namespace App\Ai;

use App\Entity\CheckIn;

/**
 * Deterministic, offline implementation of the AI boundary.
 *
 * Two jobs:
 *  - let tests run with zero network and no API key;
 *  - act as the fallback when the real OpenAI call fails or is unavailable.
 *
 * It produces a sensible recommendation purely from the tool findings, so its
 * output is always valid and reproducible.
 */
final class FakeRecommendationClient implements AiRecommendationClientInterface
{
    public function generate(CheckIn $checkIn, array $toolFindings): RecommendationDraft
    {
        $risk = $toolFindings['score_focus_risk'] ?? [];
        $action = $toolFindings['suggest_next_action'] ?? [];

        $riskLevel = (string) ($risk['riskLevel'] ?? 'low');
        $priority = (string) ($action['priority'] ?? 'medium');
        $nextAction = (string) ($action['nextAction'] ?? sprintf('Make progress on "%s".', $checkIn->getFocusGoal()));

        /** @var list<string> $signals */
        $signals = $risk['signals'] ?? [];
        $reasoning = sprintf(
            'Local coach (no AI): energy %d/5 with stated distraction "%s" maps to %s focus risk%s. '
            . 'Suggested a %s-priority step toward "%s".',
            $checkIn->getEnergyLevel(),
            $checkIn->getDistractionRisk(),
            $riskLevel,
            $signals !== [] ? ' (signals: ' . implode(', ', $signals) . ')' : '',
            $priority,
            $checkIn->getFocusGoal(),
        );

        return new RecommendationDraft($priority, $riskLevel, $nextAction, $reasoning);
    }
}
