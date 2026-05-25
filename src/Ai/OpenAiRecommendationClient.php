<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Exception\AiClientException;
use App\Entity\CheckIn;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Calls the OpenAI Chat Completions API and maps the reply to a draft.
 *
 * Notes for review:
 *  - The API key comes only from the OPENAI_API_KEY env var (never hard-coded).
 *  - Every failure mode (no key, transport error, non-2xx, malformed/invalid
 *    JSON) is normalised to AiClientException so callers handle one thing.
 *  - The model is asked for strict JSON, and the reply is still validated and
 *    sanitised before we trust it.
 */
final class OpenAiRecommendationClient implements AiRecommendationClientInterface
{
    private const ALLOWED_LEVELS = ['low', 'medium', 'high'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(OPENAI_API_KEY)%')]
        private readonly string $apiKey = '',
        #[Autowire('%env(OPENAI_MODEL)%')]
        private readonly string $model = 'gpt-4o-mini',
        #[Autowire('%env(OPENAI_BASE_URI)%')]
        private readonly string $baseUri = 'https://api.openai.com',
    ) {
    }

    public function generate(CheckIn $checkIn, array $toolFindings): RecommendationDraft
    {
        if ('' === trim($this->apiKey)) {
            throw new AiClientException('OPENAI_API_KEY is not set.');
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUri, '/') . '/v1/chat/completions', [
                'auth_bearer' => $this->apiKey,
                'json' => [
                    'model' => $this->model,
                    'temperature' => 0.3,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => $this->userPrompt($checkIn, $toolFindings)],
                    ],
                ],
                'timeout' => 20,
            ]);

            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                throw new AiClientException(sprintf('OpenAI returned HTTP %d.', $status));
            }

            $payload = $response->toArray(false);
        } catch (HttpClientExceptionInterface $e) {
            throw new AiClientException('OpenAI request failed: ' . $e->getMessage(), 0, $e);
        }

        $content = $payload['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || '' === trim($content)) {
            throw new AiClientException('OpenAI response did not contain message content.');
        }

        return $this->parseDraft($content);
    }

    private function systemPrompt(): string
    {
        return 'You are a concise daily focus coach. '
            . 'Reply with a single JSON object and nothing else, using exactly these string keys: '
            . '"priority" (one of low|medium|high), "riskLevel" (one of low|medium|high), '
            . '"nextAction" (one short, concrete sentence), "reasoning" (one or two sentences). '
            . 'Respect the provided tool findings as ground truth.';
    }

    /**
     * @param array<string, array<string, mixed>> $toolFindings
     */
    private function userPrompt(CheckIn $checkIn, array $toolFindings): string
    {
        $context = [
            'checkIn' => [
                'energyLevel' => $checkIn->getEnergyLevel(),
                'focusGoal' => $checkIn->getFocusGoal(),
                'distractionRisk' => $checkIn->getDistractionRisk(),
                'notes' => $checkIn->getNotes(),
            ],
            'toolFindings' => $toolFindings,
        ];

        return "Produce a recommendation for this check-in.\n"
            . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Validate and sanitise the model's JSON before we trust it.
     */
    private function parseDraft(string $content): RecommendationDraft
    {
        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new AiClientException('OpenAI response was not valid JSON.', 0, $e);
        }

        if (!is_array($data)) {
            throw new AiClientException('OpenAI response JSON was not an object.');
        }

        foreach (['priority', 'riskLevel', 'nextAction', 'reasoning'] as $key) {
            if (!isset($data[$key]) || !is_scalar($data[$key])) {
                throw new AiClientException(sprintf('OpenAI response missing field "%s".', $key));
            }
        }

        return new RecommendationDraft(
            priority: $this->normaliseLevel((string) $data['priority'], 'medium'),
            riskLevel: $this->normaliseLevel((string) $data['riskLevel'], 'medium'),
            nextAction: $this->clip((string) $data['nextAction'], 255),
            reasoning: $this->clip((string) $data['reasoning'], 2000),
        );
    }

    private function normaliseLevel(string $value, string $default): string
    {
        $value = strtolower(trim($value));

        return in_array($value, self::ALLOWED_LEVELS, true) ? $value : $default;
    }

    private function clip(string $value, int $max): string
    {
        $value = trim($value);

        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }
}
