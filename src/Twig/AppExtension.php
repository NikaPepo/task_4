<?php

declare(strict_types=1);

namespace App\Twig;

use App\Util\IdGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * IMPORTANT: exposes the {@see IdGenerator::getUniqIdValue()} helper as a
 * Twig function so templates can render the uniq id without inlining logic.
 */
final class AppExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('getUniqIdValue', [IdGenerator::class, 'getUniqIdValue']),
        ];
    }
}