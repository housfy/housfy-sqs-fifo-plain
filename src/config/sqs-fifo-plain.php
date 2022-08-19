<?php

/**
 * List of plain SQS queues and their corresponding handling classes
 * Please know that one queue can have only ONE Handler
 */
return [
    'handlers' => [
        'queueName' => App\Jobs\QueueNameHandlerJob::class,
    ],

    'default-handler' => App\Jobs\DefaultHandlerJob::class
];