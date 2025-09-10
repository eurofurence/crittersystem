<?php

declare(strict_types=1);

namespace Engelsystem\ServiceProvider;

use Engelsystem\Container\ServiceProvider;
use Engelsystem\Helpers\ShiftExportHelper;
use Engelsystem\Models\Location;
use Engelsystem\Models\AngelType;
use Engelsystem\Models\Shifts\Shift;
use Engelsystem\Models\Shifts\ShiftType;
use Engelsystem\Models\User\User;

class ShiftExportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ShiftExportHelper::class, function ($app) {
            return new ShiftExportHelper(
                $app->make(Location::class),
                $app->make(AngelType::class),
                $app->make(Shift::class),
                $app->make(ShiftType::class),
                $app->make(User::class)
            );
        });
    }
}
