<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request model for "create a check-in".
 *
 * Validation lives here, not on the entity, so HTTP input rules stay separate
 * from persistence. Built from the decoded JSON body in the controller and
 * checked with the Symfony Validator before anything touches the database.
 */
final class CreateCheckInRequest
{
    #[Assert\NotNull(message: 'energyLevel is required.')]
    #[Assert\Range(notInRangeMessage: 'energyLevel must be between {{ min }} and {{ max }}.', min: 1, max: 5)]
    public ?int $energyLevel = null;

    #[Assert\NotBlank(message: 'focusGoal is required.')]
    #[Assert\Length(max: 255)]
    public string $focusGoal = '';

    #[Assert\NotBlank(message: 'distractionRisk is required.')]
    #[Assert\Length(max: 255)]
    public string $distractionRisk = '';

    #[Assert\Length(max: 2000)]
    public ?string $notes = null;

    /**
     * Build from a decoded JSON body with light, type-safe coercion.
     * Anything malformed becomes a validation error rather than a type error.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->energyLevel = self::asNullableInt($data['energyLevel'] ?? null);
        $request->focusGoal = self::asString($data['focusGoal'] ?? null);
        $request->distractionRisk = self::asString($data['distractionRisk'] ?? null);
        $request->notes = isset($data['notes']) ? self::asString($data['notes']) : null;

        return $request;
    }

    private static function asString(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function asNullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
