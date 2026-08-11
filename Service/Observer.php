<?php

namespace Goomento\SystemTraces\Service;

use Goomento\SystemTraces\Api\TraceCollectorInterface;

class Observer implements TraceCollectorInterface
{
    private array $calls = [];

    /**
     * @param string $eventName
     * @param string $class
     * @param float $start
     * @param float $duration
     * @return void
     */
    public function addObserver(string $eventName, string $class, float $start, float $duration): void
    {
        $this->calls[] = [
            'event' => $eventName,
            'class' => $class,
            'start' => $start,
            'duration' => $duration,
        ];
    }

    /**
     * @return array
     */
    public function pullData(): array
    {
        return $this->calls;
    }
}
