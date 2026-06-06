<?php

namespace App\Services;

use Redis;

class IdempotencyBlocker
{
    public function __construct(
        private readonly Redis $redis
    ) {}

    public function tryLock(string $phone): bool
    {
        $lockKey = sprintf('lock:phone:%s', md5($phone));

        $result = $this->redis->set($lockKey, 'processing', ['NX', 'EX' => 10]);

        return (bool)$result;
    }
}
