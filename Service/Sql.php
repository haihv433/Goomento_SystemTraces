<?php

namespace Goomento\SystemTraces\Service;

use Goomento\SystemTraces\Api\TraceCollectorInterface;
use Magento\Framework\App\ResourceConnection;

class Sql implements TraceCollectorInterface
{
    private ResourceConnection $resource;

    private ?array $data = null;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    /**
     * @return array
     */
    public function pullData(): array
    {
        if ($this->data === null) {
            $this->data = $this->collect();
        }

        return $this->data;
    }

    /**
     * @return array
     */
    private function collect(): array
    {
        $profiler = $this->resource->getConnection('read')->getProfiler();

        if (!$profiler->getTotalNumQueries()) {
            return [];
        }

        $supportsBacktrace = method_exists($profiler, 'getQueryBt');
        $queries = [];

        foreach ($profiler->getQueryProfiles() as $key => $query) {
            $queries[] = [
                'sql' => $query->getQuery(),
                'params' => $query->getQueryParams(),
                'time' => $query->getElapsedSecs(),
                'start' => $query->getStartedMicrotime(),
                'bt' => $supportsBacktrace ? $profiler->getQueryBt($key) : [],
            ];
        }

        return [
            'all_queries' => $this->gradeQueries($queries),
            'total_elapsed_secs' => $profiler->getTotalElapsedSecs(),
        ];
    }

    /**
     * @param array $queries
     * @return array
     */
    private function gradeQueries(array $queries): array
    {
        $times = array_column($queries, 'time');
        $shortest = min($times);
        $longest = max($times);
        $average = array_sum($times) / count($times);

        $squareSum = 0.0;
        foreach ($times as $time) {
            $squareSum += ($time - $average) ** 2;
        }
        $standardDeviation = $squareSum > 0 ? sqrt($squareSum / count($times)) : 0.0;

        foreach ($queries as &$query) {
            if ($query['time'] < $shortest + 2 * $standardDeviation) {
                $query['grade'] = 'good';
            } elseif ($query['time'] > $longest - 2 * $standardDeviation) {
                $query['grade'] = 'bad';
            } else {
                $query['grade'] = 'medium';
            }
        }
        unset($query);

        return $queries;
    }
}
