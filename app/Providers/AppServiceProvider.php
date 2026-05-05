<?php

namespace App\Providers;

use App\Models\AccountEntry;
use App\Models\Delivery;
use App\Observers\AccountEntryObserver;
use App\Observers\DeliveryObserver;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
        AccountEntry::observe(AccountEntryObserver::class);
        Delivery::observe(DeliveryObserver::class);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
