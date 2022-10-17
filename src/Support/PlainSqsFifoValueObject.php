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

    /**
     * @param string $id
     * @param string $type
     * @param array $attributes
     * @param string $group
     * @param array $meta
     * @param string|null $occurred_on
     * @param string|null $connection
     */
    public function __construct(
        string $id,
        string $type,
        array $attributes,
        string $group = "default",
        ?string $connection = null,
        array $meta = [],
        ?string $occurred_on = null
    ) {
        $this->id = $id;
        $this->type = $type;
        $this->attributes = $attributes;
        $this->group = $group;
        $this->connection = $connection;
        $this->meta = $meta;
        $this->occurred_on = $occurred_on;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function occurredOn(): ?string
    {
        return $this->occurred_on;
    }

    public function attributes(): array
    {
        return $this->attributes;
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function group()
    {
        return $this->group;
    }

    public function connection()
    {
        return $this->connection;
    }
}
