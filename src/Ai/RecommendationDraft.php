<?php

declare(strict_types=1);

namespace App\Ai;

/**
 * Immutable, transport-agnostic result of "analyse this check-in".
 *
 * It is what an AiRecommendationClientInterface returns. The service turns it
 * into a persisted Recommendation entity. Keeping this separate from the
 * entity means the AI layer never touches Doctrine.
 */
final class RecommendationDraft
{
    public function __construct(
        public readonly string $priority,
        public readonly string $riskLevel,
        public readonly string $nextAction,
        public readonly string $reasoning,
    ) {
    }
}
