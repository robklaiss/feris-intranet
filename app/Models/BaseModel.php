<?php

declare(strict_types=1);

namespace App\Models;

class BaseModel
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(public array $attributes = [])
    {
    }
}

