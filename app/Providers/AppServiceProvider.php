<?php

namespace App\Providers;

use App\Contracts\CmisApi;
use App\Contracts\TeacherApi;
use App\Services\CmisService;
use App\Services\TeacherApiService;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CmisApi::class, CmisService::class);

        $this->app->scoped(TeacherApi::class, TeacherApiService::class);
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
