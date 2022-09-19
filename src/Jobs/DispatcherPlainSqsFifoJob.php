<?php

declare(strict_types=1);

namespace Housfy\SqsFifoPlain\Jobs;

use Carbon\Carbon;
use Housfy\SqsFifoPlain\Bus\SqsFifoQueueable;
use Housfy\SqsFifoPlain\Support\PlainSqsFifoValueObject;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatcherPlainSqsFifoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SqsFifoQueueable;
    
    protected PlainSqsFifoValueObject $plainSqsFifoValueObject;

    public function __construct(PlainSqsFifoValueObject $plainSqsFifoValueObject)
    {
        $this->plainSqsFifoValueObject = $plainSqsFifoValueObject;
        $this->onConnection($plainSqsFifoValueObject->connection());
    }

    public function getPayload(): array
    {
        if (is_null($this->plainSqsFifoValueObject->occurredOn())) {
            $this->plainSqsFifoValueObject->setOccurredOn(Carbon::now('UTC')->format('Y-m-d H:i:s'));
        }

        $meta = array_merge(
            [
                "host" => getHostName(),
                "ip" => getHostByName(getHostName())
            ],
            $this->plainSqsFifoValueObject->meta()
        );

        $payload = [
            "data" => [
                "id" => $this->plainSqsFifoValueObject->id(),
                "type" => $this->plainSqsFifoValueObject->type(),
                "occurred_on" => $this->plainSqsFifoValueObject->occurredOn(),
                "attributes" => $this->plainSqsFifoValueObject->attributes(),
                "meta" => $meta,
            ],
            // Needed to have messages separated by groups
            "group" => $this->plainSqsFifoValueObject->group()
        ];

        return $payload;
    }
}
