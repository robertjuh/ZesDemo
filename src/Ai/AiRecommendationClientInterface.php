<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Exception\AiClientException;
use App\Entity\CheckIn;

/**
 * Boundary between the application and whatever produces a recommendation.
 *
 * Controllers and services depend on this interface, never on OpenAI. That is
 * what makes the system testable (FakeRecommendationClient) and swappable
 * (a future MCP / tool-calling client would just be another implementation).
 */
interface AiRecommendationClientInterface
{
    /**
     * @param array<string, array<string, mixed>> $toolFindings
     *        Deterministic signals gathered by the agent tools, keyed by tool name.
     *
     * @throws AiClientException when a recommendation cannot be produced
     */
    public function generate(CheckIn $checkIn, array $toolFindings): RecommendationDraft;
}
