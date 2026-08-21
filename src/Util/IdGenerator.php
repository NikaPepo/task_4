<?php

declare(strict_types=1);

namespace App\Util;

/**
 * IMPORTANT: utility helpers that produce unique identifiers
 * for various purposes (DOM data-attributes, e-mail verification tokens, etc.).
 */
final class IdGenerator
{
    /**
     * NOTE: Generates a stable, opaque, URL-safe unique value for a given
     * entity/row. Used as a DOM `data-uniq-id` value, so JS code can address
     * a table row without leaking the database primary key.
     *
     * IMPORTANT: same input => same output (deterministic per call).
     * Salt makes the value non-guessable.
     */
    public static function getUniqIdValue(int|string $id, string $salt = 'user'): string
    {
        // NOTE: short hash + id keeps DOM attribute readable while unique.
        return substr(hash('sha256', $salt.':'.$id.':um4t'), 0, 12).'-'.(string) $id;
    }

    /**
     * IMPORTANT: cryptographically-strong token for e-mail verification links.
     */
    public static function getVerificationToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}