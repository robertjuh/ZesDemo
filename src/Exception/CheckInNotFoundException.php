<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Raised when an operation references a check-in id that does not exist.
 * The controller maps this to a clean 404 JSON response.
 */
final class CheckInNotFoundException extends \RuntimeException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Check-in %d was not found.', $id));
    }
}
