<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Filesystem;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleServiceDrive;
use Masbug\Flysystem\GoogleDriveAdapter;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Storage::extend('google', function ($app, $config) {
            $client = new GoogleClient();
            $client->setClientId($config['clientId']);
            $client->setClientSecret($config['clientSecret']);
            $client->refreshToken($config['refreshToken']);

            $service = new GoogleServiceDrive($client);
            $options = [];
            if (!empty($config['folderId'])) {
                $options['sharedFolderId'] = $config['folderId'];
            }
            $adapter = new GoogleDriveAdapter($service, null, $options);

            $driver = new Filesystem($adapter);

            return new FilesystemAdapter($driver, $adapter, $config);
        });
    }
}