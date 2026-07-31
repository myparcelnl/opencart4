<?php

declare(strict_types=1);

namespace Opencart\System\Engine;

class Registry
{
    /** @var array<string, object> */
    private array $data = [];

    public function set(string $key, object $value): void
    {
        $this->data[$key] = $value;
    }

    public function get(string $key): object
    {
        return $this->data[$key];
    }
}

class Model
{
    public function __construct(protected Registry $registry)
    {
    }

    public function __get(string $key): object
    {
        return $this->registry->get($key);
    }
}

class Controller extends Model
{
}
