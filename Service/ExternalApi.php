<?php

namespace Goomento\SystemTraces\Service;

use Goomento\SystemTraces\Api\TraceCollectorInterface;

class ExternalApi implements TraceCollectorInterface
{
    private array $calls = [];

    /**
     * @param string $method
     * @param string $url
     * @param int|null $status Null when the call raised an exception (no response received)
     * @param float $elapsedSecs
     * @param array $bt
     * @param string $source
     * @param float $startTime
     * @return void
     */
    public function addCall(
        string $method,
        string $url,
        ?int $status,
        float $elapsedSecs,
        array $bt,
        string $source,
        float $startTime
    ): void {
        $this->calls[] = [
            'method' => $method,
            'url' => $url,
            'status' => $status,
            'time' => $elapsedSecs,
            'start' => $startTime,
            'source' => $source,
            'bt' => $bt,
            'grade' => $this->grade($status, $elapsedSecs),
        ];
    }

    /**
     * @return array
     */
    public function pullData(): array
    {
        return $this->calls;
    }

    /**
     * @param int|null $status
     * @param float $elapsedSecs
     * @return string
     */
    private function grade(?int $status, float $elapsedSecs): string
    {
        if ($status === null || $status >= 400) {
            return 'bad';
        }
        if ($elapsedSecs < 1.0) {
            return 'good';
        }

        return 'medium';
    }
}
