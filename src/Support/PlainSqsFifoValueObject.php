<?php

declare(strict_types=1);

namespace Housfy\SqsFifoPlain\Support;


class PlainSqsFifoValueObject
{
    private string $id;
    private string $type;
    private array $attributes;
    private string $group = "default";
    private array $meta = [];
    private ?string $occurred_on = null;
    private ?string $connection = null;
    
    public function id(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function occurredOn(): ?string
    {
        return $this->occurred_on;
    }

    public function setOccurredOn(string $occurred_on): self
    {
        $this->ocurred_on = $occurred_on;
        return $this;
    }

    public function attributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array $attributes): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function setMeta(array $meta): self
    {
        $this->meta = $meta;
        return $this;
    }

    public function group()
    {
        return $this->group;
    }

    public function setGroup(string $group): self
    {
        $this->group = $group;
        return $this;
    }

    public function connection()
    {
        return $this->connection;
    }

    public function setConnection(string $connection)
    {
        $this->connection = $connection;
        return $this;
    }
}
