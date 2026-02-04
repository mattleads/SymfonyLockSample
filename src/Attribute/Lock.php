<?php

namespace App\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Lock
{
    public function __construct(
        public string $resourceName,
        public int $ttl = 30,
        public bool $blocking = false
    ) {}
}
