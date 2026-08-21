<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * NOTA BENE: User lifecycle states.
 *
 * The {@see self::$cases()} order also controls the implicit precedence
 * used in business logic (e.g. "blocked always wins over unverified").
 */
enum UserStatus: string
{
    /** IMPORTANT: Newly registered user, has not confirmed the e-mail yet. */
    case Unverified = 'unverified';

    /** IMPORTANT: E-mail confirmed; user can log in and use the application. */
    case Active = 'active';

    /** IMPORTANT: User is blocked by an admin; cannot log in. */
    case Blocked = 'blocked';

    /**
     * IMPORTANT: Human-readable label, used in the table UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Unverified',
            self::Active => 'Active',
            self::Blocked => 'Blocked',
        };
    }

    /**
     * IMPORTANT: Bootstrap badge color class for the status pill in the table.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Unverified => 'bg-warning text-dark',
            self::Active => 'bg-success',
            self::Blocked => 'bg-danger',
        };
    }
}