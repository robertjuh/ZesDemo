<?php

declare(strict_types=1);

namespace App\Ai\Exception;

/**
 * Thrown when an AI client cannot produce a usable recommendation:
 * missing API key, transport error, or malformed/invalid model output.
 *
 * The RecommendationService treats this as a signal to fall back to the
 * deterministic local client rather than failing the request.
 */
final class AiClientException extends \RuntimeException
{
}
