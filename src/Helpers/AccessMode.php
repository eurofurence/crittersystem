<?php

declare(strict_types=1);

namespace Engelsystem\Helpers;

use Engelsystem\Models\EventConfig;

// To use this functionality, you can:
//
// 1. Change the access mode through the EventConfig model:
// ```php
// $config = EventConfig::firstOrNew(['name' => 'access_mode']);
// $config->value = 'staff'; // or 'admin' or 'public'
// $config->save();
// ```
//
// 2. Or use the helper class:
// ```php
// $accessMode = app(AccessMode::class);
// $accessMode->setMode(AccessMode::MODE_STAFF);
// ```
class AccessMode
{
    // Public Access
    public const MODE_PUBLIC = 'public';
    // Staff and Admins only
    public const MODE_STAFF = 'staff';
    // Admins only
    public const MODE_ADMIN = 'admin';

    public function __construct(
        protected EventConfig $eventConfig
    ) {
    }

    public function setMode(string $mode): void
    {
        if (!in_array($mode, [self::MODE_PUBLIC, self::MODE_STAFF, self::MODE_ADMIN])) {
            throw new \InvalidArgumentException('Invalid access mode');
        }

        $config = $this->eventConfig->firstOrNew(['name' => 'access_mode']);
        $config->value = $mode;
        $config->save();
    }

    public function isStaffOnly(): bool
    {
        return $this->getMode() === self::MODE_STAFF;
    }

    public function getMode(): string
    {
        $config = $this->eventConfig->where('name', 'access_mode')->first();
        return $config ? $config->value : self::MODE_PUBLIC;
    }

    public function isAdminOnly(): bool
    {
        return $this->getMode() === self::MODE_ADMIN;
    }
}
