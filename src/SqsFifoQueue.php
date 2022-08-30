<?php

namespace Housfy\SqsFifoPlain;

use Housfy\SqsFifoPlain\Jobs\CustomSqsJob;
use Housfy\SqsFifoPlain\Jobs\DispatcherPlainSqsFifoJob;
use Illuminate\Queue\Jobs\SqsJob;
use Illuminate\Support\Facades\Config;
use LogicException;
use Aws\Sqs\SqsClient;
use ReflectionProperty;
use BadMethodCallException;
use InvalidArgumentException;
use Illuminate\Queue\SqsQueue;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Queue\CallQueuedHandler;
use Housfy\SqsFifoPlain\Support\Arr;
use Housfy\SqsFifoPlain\Support\Str;
use Illuminate\Notifications\SendQueuedNotifications;
use Housfy\SqsFifoPlain\Contracts\Queue\Deduplicator;

class SqsFifoQueue extends SqsQueue
{
    /**
     * The queue name suffix.
     *
     * @var string
     */
    protected $suffix;

    /**
     * The message group id of the fifo pipe in the queue.
     *
     * @var string
     */
    protected $group;

    /**
     * The driver to generate the deduplication id for the message.
     *
     * @var string
     */
    protected $deduplicator;

    /**
     * The flag to check if this queue is setup for delay.
     *
     * @var bool
     */
    protected $allowDelay;

    /**
     * Create a new Amazon SQS queue instance.
     *
     * @param  \Aws\Sqs\SqsClient  $sqs
     * @param  string  $default
     * @param  string  $prefix
     * @param  string  $suffix
     * @param  string  $group
     * @param  string  $deduplicator
     * @param  bool  $allowDelay
     *
     * @return void
     */
    public function __construct(SqsClient $sqs, $default, $prefix = '', $suffix = '', $group = '', $deduplicator = '', $allowDelay = false)
    {
        parent::__construct($sqs, $default, $prefix);

        $this->suffix = $suffix;
        $this->group = $group;
        $this->deduplicator = $deduplicator;
        $this->allowDelay = $allowDelay;
    }

    public function pop($queue = null)
    {
        $queue = $this->getQueue($queue);

        $response = $this->sqs->receiveMessage([
            'QueueUrl' => $queue,
            'AttributeNames' => ['ApproximateReceiveCount'],
        ]);

        if (isset($response['Messages']) && count($response['Messages']) > 0) {
            $queueId = explode('/', $queue);
            $queueId = array_pop($queueId);

            $class = (array_key_exists($queueId, $this->container['config']->get('sqs-fifo-plain.handlers')))
                ? $this->container['config']->get('sqs-fifo-plain.handlers')[$queueId]
                : $this->container['config']->get('sqs-fifo-plain.default-handler');

            $response = $this->modifyPayload($response['Messages'][0], $class);

            if (preg_match('/(5\.[4-8]\..*)|(6\.[0-9]*\..*)|(7\.[0-9]*\..*)|(8\.[0-9]*\..*)|(9\.[0-9]*\..*)/', $this->container->version())) {
                return new CustomSqsJob($this->container, $this->sqs, $response, $this->connectionName, $queue);
            }

            return new CustomSqsJob($this->container, $this->sqs, $queue, $response);
        }
    }

    /**
     * @param string|array $payload
     * @param string $class
     * @return array
     */
    private function modifyPayload($payload, $class)
    {
        if (! is_array($payload)) $payload = json_decode($payload, true);

        $body = json_decode($payload['Body'], true);

        $body = [
            'job' => $class . '@handle',
            'data' => $body,
            'uuid' => $payload['MessageId']
        ];

        $payload['Body'] = json_encode($body);

        return $payload;
    }


    /**
     * Set the underlying SQS instance.
     *
     * @param  \Aws\Sqs\SqsClient  $sqs
     *
     * @return \Housfy\SqsFifoPlain\SqsFifoQueue
     */
    public function setSqs(SqsClient $sqs)
    {
        $this->sqs = $sqs;

        return $this;
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param  string  $payload
     * @param  string|null  $queue
     * @param  array  $options
     *
     * @return mixed
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $message = [
            'QueueUrl' => $this->getQueue($queue), 'MessageBody' => $payload, 'MessageGroupId' => $this->getMeta($payload, 'group', $this->group),
        ];

        if (($deduplication = $this->getDeduplicationId($payload, $queue)) !== false) {
            $message['MessageDeduplicationId'] = $deduplication;
        }

        $response = $this->sqs->sendMessage($message);

        return $response->get('MessageId');
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * SQS FIFO queues do not allow per-message delays, but the queue itself
     * can be configured to delay the message. If this queue is setup for
     * delayed messages, push the job to the queue instead of throwing.
     *
     * @param  \DateTime|int  $delay
     * @param  string  $job
     * @param  mixed  $data
     * @param  string|null  $queue
     *
     * @return mixed
     *
     * @throws BadMethodCallException
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        if ($this->allowDelay) {
            return $this->push($job, $data, $queue);
        }

        throw new BadMethodCallException('FIFO queues do not support per-message delays.');
    }

    /**
     * Get the queue or return the default.
     *
     * Laravel 7.x added support for a suffix, mainly to support Laravel Vapor.
     * Since SQS FIFO queues must end in ".fifo", supporting a suffix config
     * on these queues must be customized to work with the existing suffix.
     *
     * Additionally, this will provide support for the suffix config for older
     * versions of Laravel, in case anyone wants to use it.
     *
     * @param  string|null  $queue
     *
     * @return string
     */
    public function getQueue($queue)
    {
        $queue = $queue ?: $this->default;

        // Prefix support was not added until Laravel 5.1. Don't support a
        // suffix on versions that don't even support a prefix.
        if (!property_exists($this, 'prefix')) {
            return $queue;
        }

        // Strip off the .fifo suffix to prepare for the config suffix.
        $queue = Str::beforeLast($queue, '.fifo');

        // Modify the queue name as needed and re-add the ".fifo" suffix.
        return (filter_var($queue, FILTER_VALIDATE_URL) === false
            ? rtrim($this->prefix, '/').'/'.Str::finish($queue, $this->suffix)
            : $queue).'.fifo';
    }

    /**
     * Get the deduplication id for the given driver.
     *
     * @param  string  $payload
     * @param  string  $queue
     *
     * @return string|bool
     *
     * @throws InvalidArgumentException
     */
    protected function getDeduplicationId($payload, $queue)
    {
        $driver = $this->getMeta($payload, 'deduplicator', $this->deduplicator);

        if (empty($driver)) {
            return false;
        }

        if ($this->container->bound($key = 'queue.sqs-fifo-plain.deduplicator.'.$driver)) {
            $deduplicator = $this->container->make($key);

            if ($deduplicator instanceof Deduplicator) {
                return $deduplicator->generate($payload, $queue);
            }

            throw new InvalidArgumentException(sprintf('Deduplication method [%s] must resolve to a %s implementation.', $driver, Deduplicator::class));
        }

        throw new InvalidArgumentException(sprintf('Unsupported deduplication method [%s].', $driver));
    }

    /**
     * Create a payload string from the given job and data.
     *
     * @param  mixed  $job
     * @param  mixed  $data
     * @param  string|null  $queue
     *
     * @return string
     *
     * @throws \LogicException
     * @throws \InvalidArgumentException
     * @throws \Illuminate\Queue\InvalidPayloadException
     */
    protected function createPayload($job, $data = '', $queue = null)
    {
        if (!$job instanceof DispatcherPlainSqsFifoJob) {
            return parent::createPayload($job, $data, $queue);
        }

        $handlerJob = $this->getClass($queue) . '@handle';

        return json_encode($job->getPayload());
    }

    /**
     * @param $queue
     * @return string
     */
    private function getClass($queue = null)
    {
        if (!$queue) return Config::get('sqs-fifo-plain.default-handler');

        $array = explode('/', $queue);
        $queue = end($array);

        if (array_key_exists($queue, Config::get('sqs-fifo-plain.handlers'))) {
            return Config::get('sqs-fifo-plain.handlers')[$queue];
        }

        return Config::get('sqs-fifo-plain.default-handler');
    }

    /**
     * Get additional meta from a payload string.
     *
     * @param  string  $payload
     * @param  string  $key
     * @param  mixed  $default
     *
     * @return mixed
     */
    protected function getMeta($payload, $key, $default = null)
    {
        $payload = json_decode($payload, true);

        return Arr::get($payload, $key, $default);
    }
}
