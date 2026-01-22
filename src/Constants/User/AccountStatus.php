<?php

declare(strict_types=1);

namespace App\Constants\User;

enum AccountStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case BANNED = 'banned';

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
