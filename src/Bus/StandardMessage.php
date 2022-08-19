<?php

declare(strict_types=1);

namespace Housfy\SqsFifoPlain\Bus;

use Carbon\Carbon;

class StandardMessage
{
    public function __construct(
        private string $id,
        private string $type,
        private array $attributes,
        private string $group = "default",
        private ?array $meta,
        private ?string $ocurred_on
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getOcurredOn(): string
    {
        return $this->ocurred_on;
    }

    public function setOcurredOn(string $ocurred_on): void
    {
        $this->ocurred_on = $ocurred_on;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function setMeta(array $meta): void
    {
        $this->meta = $meta;
    }

    public function getGroup()
    {
        return $this->group;
    }

    public function setGroup($group): void
    {
        $this->group = $group;
    }

    public function toArray(): array
    {
        if (is_null($this->ocurred_on)) {
            $this->ocurred_on = Carbon::now()->format('Y-m-d H:i:s');
        }

        $meta = array_merge(
            [
                "host" => getHostName(),
                "ip" => getHostByName(getHostName())
            ],
            $this->meta
        );

        $data = [
            "id" => $this->id,
            "type" => $this->type,
            "occurred_on" => $this->ocurred_on,
            "attributes" => $this->attributes,
            "meta" => $meta,
            "messageGroupId" => $this->group,
            "group" => $this->group
        ];

        return $data;
    }
}