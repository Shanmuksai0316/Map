<?php

namespace App\Rules;

use App\Models\Hostel;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a student's gender matches the hostel's gender_mode.
 *
 * - Hostel gender_mode "boys"  → student gender must be "male"
 * - Hostel gender_mode "girls" → student gender must be "female"
 * - Hostel gender_mode "co-ed" → any gender allowed
 *
 * Usage: new GenderHostelMatch($hostelId, $studentGender)
 */
class GenderHostelMatch implements ValidationRule
{
    public function __construct(
        protected int|string|null $hostelId,
        protected ?string $studentGender = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // If no hostel provided, skip (other rules should catch required fields)
        if (! $this->hostelId) {
            return;
        }

        $hostel = Hostel::withoutGlobalScopes()->find($this->hostelId);
        if (! $hostel) {
            return; // Hostel not found — other validation should catch this
        }

        $gender = $this->studentGender ?? $value;

        if (! $gender) {
            $fail('Student gender is required for hostel allocation.');
            return;
        }

        $genderMode = $hostel->gender_mode;

        if ($genderMode === 'co-ed') {
            return; // Any gender allowed
        }

        $allowed = match ($genderMode) {
            'boys' => 'male',
            'girls' => 'female',
            default => null,
        };

        if ($allowed && strtolower($gender) !== $allowed) {
            $hostelType = ucfirst($genderMode);
            $fail("Cannot assign {$gender} student to {$hostelType} hostel ({$hostel->name}). Gender mismatch.");
        }
    }

    /**
     * Static helper: validate gender-hostel compatibility and throw on mismatch.
     */
    public static function check(int|string $hostelId, string $gender): bool
    {
        $hostel = Hostel::withoutGlobalScopes()->find($hostelId);
        if (! $hostel) {
            return true; // Can't validate without hostel
        }

        if ($hostel->gender_mode === 'co-ed') {
            return true;
        }

        $allowed = match ($hostel->gender_mode) {
            'boys' => 'male',
            'girls' => 'female',
            default => null,
        };

        return ! $allowed || strtolower($gender) === $allowed;
    }
}
