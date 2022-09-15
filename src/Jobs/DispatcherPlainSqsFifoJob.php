<?php

declare(strict_types=1);

namespace Housfy\SqsFifoPlain\Jobs;

use Carbon\Carbon;
use Housfy\SqsFifoPlain\Bus\SqsFifoQueueable;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatcherPlainSqsFifoJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels, SqsFifoQueueable;

    private string $id;
    private string $type;
    private array $attributes;
    private string $group = "default";
    private array $meta = [];
    private ?string $ocurred_on = null;

    public function __construct()
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getOcurredOn(): string
    {
        return $this->ocurred_on;
    }

    public function setOcurredOn(string $ocurred_on): self
    {
        $this->ocurred_on = $ocurred_on;
        return $this;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function setMeta(array $meta): self
    {
        $this->meta = $meta;
        return $this;
    }

    public function getGroup()
    {
        return $this->group;
    }

    public function setGroup($group): self
    {
        $this->group = $group;
        return $this;
    }

    public function getPayload(): array
    {
        if (is_null($this->ocurred_on)) {
            $this->ocurred_on = Carbon::now('UTC')->format('Y-m-d H:i:s');
        }

        $meta = array_merge(
            [
                "host" => getHostName(),
                "ip" => getHostByName(getHostName())
            ],
            $this->meta
        );

        $payload = [
            "data" => [
                "id" => $this->id,
                "type" => $this->type,
                "occurred_on" => $this->ocurred_on,
                "attributes" => $this->attributes,
                "meta" => $meta,
            ],
            // Needed to have messages separated by groups
            "group" => $this->group
        ];

        return $payload;
    }
}