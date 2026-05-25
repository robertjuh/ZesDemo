<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\EntityPresenter;
use App\Http\JsonBody;
use App\Service\RecommendationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Thin HTTP layer for recommendations. A missing check-in surfaces as a 404
 * via the domain exception; AI failures are absorbed by the service's fallback.
 */
#[Route('/api/recommendations')]
final class RecommendationController extends AbstractController
{
    public function __construct(
        private readonly RecommendationService $recommendations,
        private readonly EntityPresenter $presenter,
    ) {
    }

    #[Route('/generate', name: 'recommendations_generate', methods: ['POST'])]
    public function generate(Request $request): JsonResponse
    {
        $data = JsonBody::decode($request);

        $checkInId = $data['checkInId'] ?? null;
        if (!is_numeric($checkInId)) {
            throw new BadRequestHttpException('checkInId is required and must be an integer.');
        }

        $recommendation = $this->recommendations->generateForCheckIn((int) $checkInId);

        return $this->json($this->presenter->recommendation($recommendation), Response::HTTP_CREATED);
    }
}
