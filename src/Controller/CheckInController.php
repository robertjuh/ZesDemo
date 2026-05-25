<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateCheckInRequest;
use App\Http\EntityPresenter;
use App\Http\JsonBody;
use App\Service\CheckInService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Thin HTTP layer for check-ins: parse, validate, delegate, present.
 * All persistence and business rules live in CheckInService.
 */
#[Route('/api/checkins')]
final class CheckInController extends AbstractController
{
    public function __construct(
        private readonly CheckInService $checkIns,
        private readonly ValidatorInterface $validator,
        private readonly EntityPresenter $presenter,
    ) {
    }

    #[Route('', name: 'checkins_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $dto = CreateCheckInRequest::fromArray(JsonBody::decode($request));

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        $checkIn = $this->checkIns->create($dto);

        return $this->json($this->presenter->checkIn($checkIn), Response::HTTP_CREATED);
    }

    #[Route('', name: 'checkins_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $items = array_map(
            fn ($checkIn) => $this->presenter->checkIn($checkIn),
            $this->checkIns->recent(),
        );

        return $this->json($items);
    }

    #[Route('/today', name: 'checkins_today', methods: ['GET'])]
    public function today(): JsonResponse
    {
        $checkIn = $this->checkIns->today();
        if (null === $checkIn) {
            return $this->json(
                ['checkIn' => null, 'message' => 'No check-in recorded today yet.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        return $this->json($this->presenter->checkIn($checkIn));
    }

    private function validationError(ConstraintViolationListInterface $violations): JsonResponse
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[] = [
                'field' => $violation->getPropertyPath(),
                'message' => (string) $violation->getMessage(),
            ];
        }

        return $this->json(
            ['error' => 'Validation failed.', 'violations' => $errors],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}
