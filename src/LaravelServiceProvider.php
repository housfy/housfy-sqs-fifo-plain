<?php

declare(strict_types=1);


namespace Housfy\SqsFifoPlain;

use Housfy\SqsFifoPlain\Sqs\Connector;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Queue;
use Illuminate\Queue\Events\JobProcessed;

/**
 * Class CustomQueueServiceProvider
 * @package App\Providers
 */
class LaravelServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/sqs-fifo-plain.php' => config_path('sqs-fifo-plain.php')
        ]);

        Queue::after(function (JobProcessed $event) {
            $event->job->delete();
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\WorkCommand::class,
            ]);
        }
    }

    /**
     * @return void
     */
    public function register()
    {
        $this->app->bind(Console\WorkCommand::class, function ($app) {
            return new Console\WorkCommand($app['queue.worker'], $app['cache.store']);
        });

        $this->app->booted(function () {
            $this->app['queue']->extend('sqs-fifo-plain', function () {
                return new Connector();
            });
        });
    }
}