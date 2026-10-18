<?php

namespace App\Providers;

use App\Contracts\SmsGateway;
use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsGateway::class, fn ($app) => $app->make(config('omit.sms_gateway')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (PermissionName::cases() as $permission) {
            Gate::define($permission->value, fn (User $user): bool => $user->hasPermission($permission));
        }

        // « composer run dev » lance aussi le planificateur : acquisitions, clôtures, rappels, sauvegardes.
        DevCommands::artisan('schedule:work', 'scheduler');

        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)->by(
            Str::transliterate(Str::lower($request->string('email'))).'|'.$request->ip()
        ));
    }
}
