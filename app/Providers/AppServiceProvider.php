<?php

namespace App\Providers;

use App\Services\Protection\FakeBackend;
use App\Services\Protection\HttpWorkerBackend;
use App\Services\Protection\ProtectionBackend;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProtectionBackend::class, function () {
            $config = config('voiceshield');

            return match ($config['driver']) {
                'http' => new HttpWorkerBackend(
                    $config['worker']['url'],
                    $config['worker']['token'],
                    $config['worker']['timeout'],
                ),
                default => new FakeBackend($config['fake']['path'], $config['fake']['duration']),
            };
        });
    }

    public function boot(): void
    {
        //
    }
}
