<?php

namespace SoulDoit\DataTableTwo;

use Illuminate\Support\ServiceProvider;

class DataTableServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (isNotLumen()) {
            $this->publishes([
                __DIR__.'/../config/sd-datatable-two-ssp.php' => config_path('sd-datatable-two-ssp.php'),
            ], 'config');
        }
    }

    public function register()
    {
        if (isNotLumen()) {
            $this->mergeConfigFrom(
                __DIR__.'/../config/sd-datatable-two-ssp.php',
                'sd-datatable-two-ssp'
            );
        }
    }

    protected function registerModelBindings()
    {
    }

    protected function registerBladeExtensions()
    {
    }

    protected function registerMacroHelpers()
    {
    }

    /**
     * Returns existing migration file if found, else uses the current timestamp.
     *
     * @param Filesystem $filesystem
     * @return string
     */
    protected function getMigrationFileName(Filesystem $filesystem): string
    {
    }
}
