<?php

namespace Goomento\SystemTraces\Api;

interface TraceCollectorInterface
{
    /**
     * @return array
     */
    public function pullData(): array;
}
