<?php

declare(strict_types=1);

namespace SeviaFlow\Licensing;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use SeviaFlow\Licensing\Contracts\ActivationStore;
use SeviaFlow\Licensing\Middleware\RequireLicensedModule;

final class SeviaFlowLicensingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/seviaflow-licensing.php', 'seviaflow-licensing');
        $this->app->singleton(LicenseVerifier::class);
        $this->app->singleton(LicenseSigner::class);
        $this->app->singleton(LicenseGate::class);
        $this->app->singleton(ActivationStore::class, function (): ActivationStore {
            return new FileActivationStore($this->files());
        });
    }

    public function boot(): void
    {
        $router = $this->app->make('router');
        $router->aliasMiddleware('seviaflow.license', RequireLicensedModule::class);
    }

    private function files(): Filesystem
    {
        $disk = (string) config('filesystems.default', 'local');

        return Storage::disk($disk);
    }
}
