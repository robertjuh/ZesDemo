<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\CheckInNotFoundException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Renders any uncaught exception on an /api route as a consistent JSON error,
 * so clients never receive an HTML error page. Domain exceptions are mapped to
 * meaningful status codes; everything else degrades to a safe 500.
 */
final class ApiExceptionListener
{
    public function __construct(private readonly KernelInterface $kernel)
    {
    }

    #[AsEventListener]
    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $throwable = $event->getThrowable();
        [$status, $message] = $this->map($throwable);

        $payload = ['error' => $message];

        // Surface internals only in debug, and only for unexpected 5xx errors.
        if ($this->kernel->isDebug() && $status >= 500) {
            $payload['exception'] = $throwable::class;
            $payload['detail'] = $throwable->getMessage();
        }

        $event->setResponse(new JsonResponse($payload, $status));
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function map(\Throwable $throwable): array
    {
        return match (true) {
            $throwable instanceof CheckInNotFoundException => [Response::HTTP_NOT_FOUND, $throwable->getMessage()],
            $throwable instanceof HttpExceptionInterface => [
                $throwable->getStatusCode(),
                $throwable->getMessage() ?: (Response::$statusTexts[$throwable->getStatusCode()] ?? 'Error'),
            ],
            default => [Response::HTTP_INTERNAL_SERVER_ERROR, 'Internal server error.'],
        };
    }
}
