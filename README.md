# SQS FIFO PLAIN

This package send a plain payload to an SQS FIFO then processes it using an assigned Job via the config.

## How to use it?

### How do I dispatch an event?

In order to dispatch an event you must do the following

```php
use Housfy\SqsFifoPlain\Jobs\DispatcherPlainSqsFifoJob;
use Ramsey\Uuid\Uuid;

// ...

$payload = [
    'msg' => 'Hello World',
];

$type = 'customName.versionNumber.actionName'
$groupName = 'myGroup';
// The connectionName must be the same name that you used on the config/queue.php file
$connectionName= "sqs-fifo-plain";

$sqsValueObject = (new PlainSqsFifoValueObject(Uuid::uuid1(), $type, $payload, $groupName, $connectionName));

 DispatcherPlainSqsFifoJob::dispatch($sqsValueObject);
```

That will dispatch the following message to SQS FIFO queue:

```json
{
  "data": {
    "id": "2ee2bfd4-2555-11ed-9269-0242ac120008",
    "type": "customName.versionNumber.actionName",
    "occurred_on": "2022-08-26 15:38:52",
    "attributes": {
      "msg": "Hello world"
    },
    "meta": {
      "host": "80db1d09839a",
      "ip": "172.18.0.8"
    }
  },
  "group": "myGroup"
}
```

The message will be properly stored on fifo under the group that you specify!.

### How do I consume a message?

You will first need to configure a handler per queue, using the `config/sqs-fifo-plain.php` file

```php
<?php 
return [
    'handlers' => [
        'queueName.fifo' => App\Jobs\QueueNameHandlerJob::class,
    ],

    'default-handler' => App\Jobs\DefaultHandlerJob::class
];
```

Now the content of `QueueNameHandlerJob` should look something like this:

```php
class SqsFifoPlainHandlerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 5;

    protected $data;

    /**
     * @param SqsJob $job
     * @param array $data
     */
    public function handle(SqsJob $job, array $data)
    {
        $this->job = $job;
        
        var_dump($data['data']['attributes']['msg']);

        //If you want to mark the job as failed
        $this->job->markAsFailed();
        throw new \Exception("Prueba");
        
        //Delete from queue **ONLY** if the processing was right
        $this->job->delete();
    }
}
```

## Installation and configuration

### WAIT!

**BEFORE** you jump into integrating this package you must know these things:
- The messages **will not be deleted** from the queue if the processing fails.
- To mark job as fail you must explicit set `$this->job->markAsFailed();` on your Job handler and throw an exception.
- This packages assumes that you are using "Dead Letter Queues" (to which messages that fail 5 times will be moved to)
and as so you should run the following command 
to consume messages:
  - `php artisan sqsqueue:work [Connection Name] --tries=5 --backoff=32`

If all of that is ok with you the please continue with the setup, by following the instruction below.

Add the following lines to your composer.json file:

```
"repositories":[
    {
        "type": "vcs",
        "url": "git@github.com:housfy/housfy-sqs-fifo-plain.git"
    }
]
```

Then install the package with:

```bash
composer requie housfy/housfy-sqs-fifo-plain
```

Register the service provider

```php
// Add in your config/app.php

'providers' => [
    '...',
    Housfy\SqsFifoPlain\LaravelServiceProvider::class,
];
```

Make sure to publish the assets with:

```
php artisan vendor:publish
```

And select the `Housfy\SqsFifoPlain\LaravelServiceProvider`

Configure the `config/sqs-fif-plain.php` file according to your projects needs.

Configure a new queue connector on `config/queue.php`

```php
    'connections' => [
    //...
    'sqs-fifo-plain' => [
            'driver' => 'sqs-fifo-plain',
            'key'    => env('AWS_SQS_KEY', ''),
            'secret' => env('AWS_SQS_SECRET', ''),
            'prefix' => env('AWS_SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue'  => 'queue-name.fifo',
            'region' => 'eu-west-1',
            'group' => 'default',
            'deduplicator' => 'unique',
            'allow_delay' => env('AWS_SQS_ALLOW_DELAY'),
        ],
      ]
```

And set the following ENV parameters on your `.env` file

```
AWS_SQS_KEY=
AWS_SQS_SECRET=
AWS_SQS_PREFIX=https://sqs.eu-west-1.amazonaws.com/accountIdNumberGoesHere
```

Based on the works of:
- [https://github.com/dusterio/laravel-plain-sqs](https://github.com/dusterio/laravel-plain-sqs) (MIT License)
- [https://github.com/shiftonelabs/laravel-sqs-fifo-queue](https://github.com/shiftonelabs/laravel-sqs-fifo-queue) (MIT License)