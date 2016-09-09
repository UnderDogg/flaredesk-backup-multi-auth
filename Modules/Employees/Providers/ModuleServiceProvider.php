<?php
namespace Modules\Employees\Providers;

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
    $this->loadTranslationsFrom(__DIR__ . '/../Resources/Lang', 'employees');
    $this->loadViewsFrom(__DIR__ . '/../Resources/Views', 'employees');
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
    $this->app->bind('Modules\Employees\Services\Staff\StaffServiceContract', 'Modules\Employees\Services\Mailbox\StaffService');
    // add bindings
  }



}
