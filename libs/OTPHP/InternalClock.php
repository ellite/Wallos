<?php

declare(strict_types=1);

namespace OTPHP;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * @internal
 */
final class InternalClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
