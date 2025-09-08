<?php

namespace App\Support;

class ClientContext
{
    protected ?int $id = null;

    public function set(int $id): void
    {
        $this->id = $id;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function get(): ?int
    {
        return $this->id;
    }
}