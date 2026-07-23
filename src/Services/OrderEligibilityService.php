<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use function is_numeric;
use function strtotime;
use function time;

final class OrderEligibilityService
{
    public static function canPurchaseTimeProduct(User $user, object $content): bool
    {
        if (! isset($content->class) || ! is_numeric($content->class)) {
            return false;
        }

        $targetClass = (int) $content->class;
        $currentClass = (int) $user->class;
        $currentExpiry = strtotime((string) $user->class_expire);
        $hasActiveClass = $currentClass > 0
            && $currentExpiry !== false
            && $currentExpiry > time();

        return $targetClass >= 0
            && (! $hasActiveClass || $currentClass === $targetClass);
    }
}
