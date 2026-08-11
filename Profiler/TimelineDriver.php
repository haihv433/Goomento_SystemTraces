<?php

namespace Goomento\SystemTraces\Profiler;

use Magento\Framework\Profiler\DriverInterface;

/**
 * Records one flat, per-occurrence event per start/stop pair, keeping each occurrence's
 * absolute start time - unlike Magento\Framework\Profiler\Driver\Standard, which only
 * keeps cumulative time/count per timer name.
 */
class TimelineDriver implements DriverInterface
{
    private array $stack = [];

    private array $events = [];

    /**
     * @param string $timerId
     * @param array|null $tags
     * @return void
     */
    public function start($timerId, ?array $tags = null): void
    {
        $this->stack[] = [
            'name' => $timerId,
            'start' => microtime(true),
            'mem' => memory_get_usage(true),
        ];
    }

    /**
     * @param string $timerId
     * @return void
     */
    public function stop($timerId): void
    {
        $frame = array_pop($this->stack);
        if ($frame === null) {
            return;
        }

        $this->events[] = [
            'name' => $frame['name'],
            'start' => $frame['start'],
            'duration' => microtime(true) - $frame['start'],
            'depth' => count($this->stack),
            'memory' => memory_get_usage(true) - $frame['mem'],
        ];
    }

    /**
     * @param string|null $timerId
     * @return void
     */
    public function clear($timerId = null): void
    {
        $this->events = [];
        $this->stack = [];
    }

    /**
     * @return array
     */
    public function getEvents(): array
    {
        return $this->events;
    }
}
