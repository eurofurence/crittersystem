<?php

declare(strict_types=1);

namespace Engelsystem\Helpers;

use Engelsystem\Container\ServiceProvider;
use Engelsystem\Services\UserSearchService;
use Psr\Log\LoggerInterface;

class UserSearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserSearchService::class, function ($app) {
            return new UserSearchService(
                $app->get(LoggerInterface::class)
            );
        });
    }
}
