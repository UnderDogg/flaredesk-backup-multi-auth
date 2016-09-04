<?php

namespace Modules\Documents\Providers;

use Caffeinated\Modules\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the module services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadTranslationsFrom(__DIR__.'/../Resources/Lang', 'documents');
        $this->loadViewsFrom(__DIR__.'/../Resources/Views', 'documents');
    }

    /**
     * Register the module services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
    //$this->registerBindings();
    }












  private function registerBindings()
  {
    $this->app->bind(
      'Modules\Relations\Repositories\RelationRepository',
      function () {
        $repository = new \Modules\Relations\Repositories\Eloquent\EloquentRelationRepository(new \Modules\Relations\Models\Relation());
        return $repository;

      }
    );
    // add bindings
  }


}