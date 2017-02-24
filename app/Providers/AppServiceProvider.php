<?php

namespace App\Providers;

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
        /**
         * 视图composer共享后台菜单数据
         */
        view()->composer(
            'layouts.partials.sidebar', 'App\Http\ViewComposers\MenuComposer'
        );
        view()->composer(
            ['layouts.index','layouts.blog'], 'App\Http\ViewComposers\SettingsComposer'
        );
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
