<?php

declare(strict_types=1);

namespace Housfy\SqsFifoPlain;

use Housfy\SqsFifoPlain\Sqs\Connector;
use Illuminate\Support\ServiceProvider;
use Housfy\SqsFifoPlain\Queue\Deduplicators\Sqs;
use Housfy\SqsFifoPlain\Queue\Deduplicators\Unique;
use Housfy\SqsFifoPlain\Queue\Deduplicators\Content;
use Housfy\SqsFifoPlain\Queue\Connectors\SqsFifoConnector;

class LaravelServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/Config/sqs-fifo-plain.php' => config_path('sqs-fifo-plain.php')
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\WorkCommand::class,
            ]);
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $app = $this->app;

        $app->bind(Console\WorkCommand::class, function ($app) {
            return new Console\WorkCommand($app['queue.worker'], $app['cache.store']);
        });


        $this->registerDeduplicators();

        // Queue is a deferred provider. We don't want to force resolution to provide
        // a new driver. Therefore, if the queue has already been resolved, extend
        // it now. Otherwise, extend the queue after it has been resolved.
        if ($app->bound('queue')) {
            $this->extendManager($app['queue']);
        } else {
            // "afterResolving" not introduced until 5.0. Before 5.0 uses "resolving".
            if (method_exists($app, 'afterResolving')) {
                $app->afterResolving('queue', function ($manager) {
                    $this->extendManager($manager);
                });
            } else {
                $app->resolving('queue', function ($manager) {
                    $this->extendManager($manager);
                });
            }
        }
    }

    /**
     * Register everything for the given manager.
     *
     * @param  \Illuminate\Queue\QueueManager  $manager
     *
     * @return void
     */
    public function extendManager($manager)
    {
        $this->registerConnectors($manager);
    }

    /**
     * Register the connectors on the queue manager.
     *
     * @param  \Illuminate\Queue\QueueManager  $manager
     *
     * @return void
     */
    public function registerConnectors($manager)
    {
        $manager->extend('sqs-fifo-plain', function () {
            return new SqsFifoConnector();
        });
    }

    /**
     * Register the default deduplicator methods.
     *
     * @return void
     */
    public function registerDeduplicators()
    {
        foreach (['Unique', 'Content', 'Sqs'] as $deduplicator) {
            $this->{"register{$deduplicator}Deduplicator"}();
        }
    }

    /**
     * Register the unique deduplicator to treat all messages as unique.
     *
     * @return void
     */
    public function registerUniqueDeduplicator()
    {
        $this->app->bind('queue.sqs-fifo-plain.deduplicator.unique', Unique::class);
    }

    /**
     * Register the content deduplicator to treat messages with the same payload as duplicates.
     *
     * @return void
     */
    public function registerContentDeduplicator()
    {
        $this->app->bind('queue.sqs-fifo-plain.deduplicator.content', Content::class);
    }

    /**
     * Register the SQS deduplicator for queues with ContentBasedDeduplication enabled on SQS.
     *
     * @return void
     */
    public function registerSqsDeduplicator()
    {
        $this->app->bind('queue.sqs-fifo-plain.deduplicator.sqs', Sqs::class);
    }
}
