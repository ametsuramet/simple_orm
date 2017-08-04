<?php

namespace Amet\SimpleORM;

use Illuminate\Support\ServiceProvider;
use Amet\SimpleORM\Commands\GeneratorModelRoutesPublisherCommand;

class ModelBuilderServiceProvider extends ServiceProvider
{

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot() {
        
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('ametsuramet.simple_orm.model', function ($app) {
            return new GeneratorModelRoutesPublisherCommand();
        });

        $this->commands([
            'ametsuramet.simple_orm.model'
        ]);
    
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [

            'ametsuramet.simple_orm.model'
        ];
    }
}