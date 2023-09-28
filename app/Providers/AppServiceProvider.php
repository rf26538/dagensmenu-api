<?php

namespace App\Providers;

use DB;
use Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('mailer', function ($app) {
            return $app->loadComponent('mail', 'Illuminate\Mail\MailServiceProvider', 'mailer');
        });
        
        $this->app->register(\Tymon\JWTAuth\Providers\LumenServiceProvider::class);
        $this->app->register('App\Providers\CustomHashServiceProvider');
        if ($this->app->environment() == 'local') {
	        $this->app->register(\Flipbox\LumenGenerator\LumenGeneratorServiceProvider::class);
	    }
    }

    //use to check the database calls, they will be registered in storage/logs/lumen-[date].log
    /*public function boot(){
        DB::listen(function($query) {
            Log::info(
                $query->sql,
                $query->bindings,
                $query->time
            );
        });
    }*/
}
