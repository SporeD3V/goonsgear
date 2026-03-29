<?php

namespace App\Providers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAlertSubscription;
use App\Models\TagFollow;
use App\Observers\ProductObserver;
use App\Observers\ProductVariantObserver;
use App\Policies\StockAlertSubscriptionPolicy;
use App\Policies\TagFollowPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(TagFollow::class, TagFollowPolicy::class);
        Gate::policy(StockAlertSubscription::class, StockAlertSubscriptionPolicy::class);

        Product::observe(ProductObserver::class);
        ProductVariant::observe(ProductVariantObserver::class);
    }
}
