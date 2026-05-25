<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Decodes a JSON request body into an array, turning malformed input into a
 * clean 400 instead of a PHP error. Keeps that boilerplate out of controllers.
 */
final class JsonBody
{
    /**
     * @return array<string, mixed>
     */
    public static function decode(Request $request): array
    {
        $content = $request->getContent();
        if ('' === trim($content)) {
            return [];
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new BadRequestHttpException('Request body is not valid JSON.', $e);
        }

        if (!is_array($data)) {
            throw new BadRequestHttpException('Request body must be a JSON object.');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }
}
