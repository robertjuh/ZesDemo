<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Agent\AgentToolInterface;

/**
 * Turns a check-in into a coarse focus-risk score.
 *
 * Pure, deterministic, dependency-free: easy to unit test and a good example
 * of the kind of "tool" an AI agent would call to ground its reasoning in
 * concrete signals rather than vibes.
 */
final class ScoreFocusRiskTool implements AgentToolInterface
{
    /** Distraction keywords that each add a risk point. */
    private const RISK_KEYWORDS = [
        'overengineering', 'overthink', 'social', 'phone', 'notification',
        'tired', 'distract', 'procrastinat', 'too much', 'youtube', 'scroll',
    ];

    public function name(): string
    {
        return 'score_focus_risk';
    }

    /**
     * @param array{energyLevel?: int, distractionRisk?: string} $input
     *
     * @return array{riskLevel: string, score: int, signals: list<string>}
     */
    public function execute(array $input): array
    {
        $energy = (int) ($input['energyLevel'] ?? 3);
        $distraction = strtolower((string) ($input['distractionRisk'] ?? ''));

        $score = 0;
        $signals = [];

        if ($energy <= 2) {
            $score += 2;
            $signals[] = 'low_energy';
        } elseif ($energy === 3) {
            $score += 1;
            $signals[] = 'moderate_energy';
        }

        foreach (self::RISK_KEYWORDS as $keyword) {
            if ($distraction !== '' && str_contains($distraction, $keyword)) {
                ++$score;
                $signals[] = 'distraction:' . $keyword;
            }
        }

        return [
            'riskLevel' => $this->levelFor($score),
            'score' => $score,
            'signals' => $signals,
        ];
    }

    private function levelFor(int $score): string
    {
        return match (true) {
            $score >= 4 => 'high',
            $score >= 2 => 'medium',
            default => 'low',
        };
    }
}
