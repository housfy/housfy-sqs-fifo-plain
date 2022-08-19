<?php

declare(strict_types=1);

namespace Housfy\SqsFifoPlain\Sqs;

use Housfy\SqsFifoPlain\Jobs\CustomSqsJob;
use Housfy\SqsFifoPlain\Jobs\DispatcherPlainSqsFifoJob;
use Illuminate\Queue\SqsQueue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Arr;
use Illuminate\Queue\Jobs\SqsJob;

/**
 * Class CustomSqsQueue
 * @package App\Services
 */
class Queue extends SqsQueue
{
    /**
     * Create a payload string from the given job and data.
     *
     * @param  string  $job
     * @param  mixed   $data
     * @param  string  $queue
     * @return string
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

        $queue = end(explode('/', $queue));

        if (array_key_exists($queue, Config::get('sqs-fifo-plain.handlers'))) {
            return Config::get('sqs-fifo-plain.handlers')[$queue];
        }

       return Config::get('sqs-fifo-plain.default-handler');
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param  string  $queue
     * @return \Illuminate\Contracts\Queue\Job|null
     */
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
     * @param string $payload
     * @param null $queue
     * @param array $options
     * @return mixed|null
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $message = [
            'QueueUrl' => $this->getQueue($queue), 'MessageBody' => $payload, 'MessageGroupId' => $this->getMeta($payload, 'group', 'default'),
        ];

        $response = $this->sqs->sendMessage($message);

        return $response->get('MessageId');
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