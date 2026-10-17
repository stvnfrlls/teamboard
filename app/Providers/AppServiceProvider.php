<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('components.layouts.app', function (ViewInstance $view): void {
            $user = auth('web')->user();

            $view->with(
                'unreadNotificationCount',
                $user?->unreadNotifications()->count() ?? 0
            );
        });
    }
}
