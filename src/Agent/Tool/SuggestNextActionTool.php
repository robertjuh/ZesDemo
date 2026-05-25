<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Agent\AgentToolInterface;

/**
 * Maps a focus goal + risk level to a concrete next action and priority.
 *
 * Deterministic counterpart to the AI: it always yields a sane suggestion,
 * which is exactly what makes it usable as the offline fallback.
 */
final class SuggestNextActionTool implements AgentToolInterface
{
    public function name(): string
    {
        return 'suggest_next_action';
    }

    /**
     * @param array{focusGoal?: string, riskLevel?: string} $input
     *
     * @return array{nextAction: string, priority: string}
     */
    public function execute(array $input): array
    {
        $goal = trim((string) ($input['focusGoal'] ?? '')) ?: 'your main goal';
        $riskLevel = (string) ($input['riskLevel'] ?? 'low');

        return match ($riskLevel) {
            'high' => [
                'priority' => 'high',
                'nextAction' => sprintf(
                    'Timebox 25 focused minutes on "%s" and remove your single biggest distraction first.',
                    $goal,
                ),
            ],
            'medium' => [
                'priority' => 'medium',
                'nextAction' => sprintf('Spend one distraction-free block on "%s" before anything else.', $goal),
            ],
            default => [
                'priority' => 'low',
                'nextAction' => sprintf('Make steady progress on "%s" at your normal pace.', $goal),
            ],
        };
    }
}
