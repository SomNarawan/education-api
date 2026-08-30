<?php

namespace App\Providers;

use App\Contracts\CurriculumApi;
use App\Services\CurriculumApiService;
use App\Services\MockCurriculumApiService;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurriculumApi::class, function () {
            return match (config('curriculum_api.driver')) {
                'mock' => new MockCurriculumApiService,
                'http' => new CurriculumApiService,
                default => throw new InvalidArgumentException('Unsupported curriculum API driver'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::listen(function ($query) {

            Log::info('SQL : '.$query->sql);

            Log::info('Bindings : ', $query->bindings);

            Log::info('Time : '.$query->time.' ms');
        });

        Event::listen(DiagnosingHealth::class, function (): void {
            DB::connection()->getPdo();
        });
    }
}
