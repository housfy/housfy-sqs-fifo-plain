# SQS FIFO PLAIN

This package send a plain payload to an SQS FIFO then processes it using an assigned Job via the config.

## How to use it?

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
    'Housfy\SqsFifoPlain\LaravelServiceProvider::class',
];
```

Make sure to publish the assets with:

```
php artisan vendor:publish
```

Configure the `config/sqs-fif-plain.php` file according to your projects needs.

Configure a new queue connector on `config/queue.php`

```php
    'connections' => [
    //...
    'sqs-fifo' => [
            'driver' => 'sqs-fifo',
            'key'    => env('AWS_SQS_KEY', ''),
            'secret' => env('AWS_SQS_SECRET', ''),
            'prefix' => env('AWS_SQS_PREFIX'),
            'suffix' => env('SQS_SUFFIX'),
            'queue'  => 'myqueue.fifo',
            'region' => 'eu-west-1',
            'group' => 'default',
            'deduplicator' => 'unique',
            'allow_delay' => env('SQS_ALLOW_DELAY'),
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