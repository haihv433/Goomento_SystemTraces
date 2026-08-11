<?php

namespace Goomento\SystemTraces\Service;

use Goomento\SystemTraces\Profiler\TimelineDriver;
use Goomento\SystemTraces\Service\Event\Instance;
use Magento\Framework\Profiler;

class Timeline
{
    private Sql $sqlService;

    private ExternalApi $apiService;

    private Observer $observerService;

    private TimelineDriver $driver;

    private Instance $modelService;

    private Instance $collectionService;

    private ?array $data = null;

    public function __construct(
        Sql $sqlService,
        ExternalApi $apiService,
        Observer $observerService,
        TimelineDriver $driver,
        Instance $modelService,
        Instance $collectionService
    ) {
        $this->sqlService = $sqlService;
        $this->apiService = $apiService;
        $this->observerService = $observerService;
        $this->driver = $driver;
        $this->modelService = $modelService;
        $this->collectionService = $collectionService;
    }

    /**
     * Merges SQL queries, external API calls, observer invocations, and framework phase
     * timers into a single chronologically-sorted event list.
     *
     * @return array
     */
    public function pullData(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $requestStart = (float)($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
        $now = microtime(true);

        $frameworkTimers = $this->classifyTimers($this->driver->getEvents());
        $rawTimers = array_merge($frameworkTimers, $this->classifyObservers($frameworkTimers));

        $events = [];

        $sqlData = $this->sqlService->pullData();
        foreach ($sqlData['all_queries'] ?? [] as $query) {
            if (empty($query['start'])) {
                continue;
            }
            [$depth, $parentLabel] = $this->findParent($rawTimers, (float)$query['start']);
            $events[] = [
                'type' => 'sql',
                'label' => $query['sql'],
                'start' => (float)$query['start'],
                'duration' => (float)$query['time'],
                'grade' => $query['grade'],
                'depth' => $depth,
                'context' => $parentLabel !== null ? 'Inside: ' . $parentLabel : null,
                'expandable_label' => $this->expandableLabel($query['sql']),
                'marks' => [['start' => (float)$query['start'], 'duration' => (float)$query['time']]],
                'bt' => $query['bt'],
            ];
        }

        foreach ($this->apiService->pullData() as $call) {
            if (empty($call['start'])) {
                continue;
            }
            [$depth, $parentLabel] = $this->findParent($rawTimers, (float)$call['start']);
            $label = $call['method'] . ' ' . $call['url'];
            $events[] = [
                'type' => 'api',
                'label' => $label,
                'start' => (float)$call['start'],
                'duration' => (float)$call['time'],
                'grade' => $call['grade'],
                'depth' => $depth,
                'context' => $parentLabel !== null ? 'Inside: ' . $parentLabel : null,
                'expandable_label' => $this->expandableLabel($label),
                'marks' => [['start' => (float)$call['start'], 'duration' => (float)$call['time']]],
                'bt' => $call['bt'],
            ];
        }

        foreach ($this->groupTimerEvents($rawTimers) as $timer) {
            $context = $timer['path'] !== $timer['leaf']
                ? implode(" \xE2\x80\xBA ", explode(Profiler::NESTING_SEPARATOR, $timer['path']))
                : null;

            $events[] = [
                'type' => $timer['type'],
                'label' => $timer['leaf'],
                'start' => (float)$timer['start'],
                'duration' => (float)$timer['duration'],
                'grade' => $this->gradeDuration((float)$timer['duration']),
                'depth' => $timer['depth'],
                'memory' => $timer['memory'],
                'count' => $timer['count'],
                'context' => $context,
                'expandable_label' => $this->expandableLabel($timer['leaf']),
                'marks' => $timer['occurrences'],
                'bt' => [],
            ];
        }

        usort($events, static fn ($a, $b) => $a['start'] <=> $b['start']);

        $totalDuration = max($now - $requestStart, 0.000001);
        foreach ($events as &$event) {
            foreach ($event['marks'] as &$mark) {
                $offsetPct = ($mark['start'] - $requestStart) / $totalDuration * 100;
                $widthPct = $mark['duration'] / $totalDuration * 100;
                $mark['offset_pct'] = min(100, max(0, $offsetPct));
                $mark['width_pct'] = max(0.2, min(100 - $mark['offset_pct'], $widthPct));
            }
            unset($mark);
        }
        unset($event);

        $apiCalls = $this->apiService->pullData();

        return $this->data = [
            'events' => $events,
            'summary' => [
                'total_duration' => $totalDuration,
                'peak_memory' => memory_get_peak_usage(true),
                'sql_count' => count($sqlData['all_queries'] ?? []),
                'sql_time' => (float)($sqlData['total_elapsed_secs'] ?? 0),
                'api_count' => count($apiCalls),
                'api_time' => array_sum(array_column($apiCalls, 'time')),
                'observer_count' => count($this->observerService->pullData()),
                'timer_count' => count($frameworkTimers),
            ],
        ];
    }

    /**
     * Reduces each framework timer name to its leaf segment, classifies it by type for
     * coloring, and swaps the generic model/collection load event name for the actual
     * class name using the dispatch-order sequence recorded by Service\Event\Instance.
     *
     * @param array $timers
     * @return array
     */
    private function classifyTimers(array $timers): array
    {
        $modelSequence = $this->modelService->getSequence();
        $collectionSequence = $this->collectionService->getSequence();
        $modelIndex = 0;
        $collectionIndex = 0;

        $classified = [];
        foreach ($timers as $timer) {
            $path = $this->stripRoot($timer['name']);
            $leaf = $this->leafName($path);
            $type = $this->classifyLeaf($leaf);

            if ($leaf === 'EVENT:model_load_before' && isset($modelSequence[$modelIndex])) {
                $leaf = 'Model: ' . $modelSequence[$modelIndex];
                $modelIndex++;
            } elseif ($leaf === 'EVENT:core_collection_abstract_load_before'
                && isset($collectionSequence[$collectionIndex])
            ) {
                $leaf = 'Collection: ' . $collectionSequence[$collectionIndex];
                $collectionIndex++;
            }

            $classified[] = $timer + [
                'leaf' => $leaf,
                'type' => $type,
                'path' => $path,
            ];
        }

        return $classified;
    }

    /**
     * Reshapes timed observer invocations into the same shape classifyTimers() produces,
     * positioned under their enclosing framework timer via findParent().
     *
     * @param array $frameworkTimers
     * @return array
     */
    private function classifyObservers(array $frameworkTimers): array
    {
        $classified = [];
        foreach ($this->observerService->pullData() as $call) {
            [$depth, $parentLabel] = $this->findParent($frameworkTimers, (float)$call['start']);
            $leaf = 'Observer: ' . $call['class'] . ' (' . $call['event'] . ')';

            $classified[] = [
                'leaf' => $leaf,
                'type' => 'observer',
                'path' => $parentLabel !== null ? $parentLabel . Profiler::NESTING_SEPARATOR . $leaf : $leaf,
                'start' => (float)$call['start'],
                'duration' => (float)$call['duration'],
                'depth' => $depth,
                'memory' => 0,
            ];
        }

        return $classified;
    }

    /**
     * @param string $path
     * @return string
     */
    private function leafName(string $path): string
    {
        $pos = strrpos($path, Profiler::NESTING_SEPARATOR);

        return $pos === false ? $path : substr($path, $pos + strlen(Profiler::NESTING_SEPARATOR));
    }

    /**
     * @param string $label
     * @return string
     */
    private function stripRoot(string $label): string
    {
        return str_replace(BP . DIRECTORY_SEPARATOR, '', $label);
    }

    /**
     * @param string $leaf
     * @return string
     */
    private function classifyLeaf(string $leaf): string
    {
        return match (true) {
            str_starts_with($leaf, 'CONTROLLER_ACTION:') => 'controller',
            $leaf === 'EVENT:model_load_before' || $leaf === 'EVENT:model_load_after' => 'model',
            $leaf === 'EVENT:core_collection_abstract_load_before'
                || $leaf === 'EVENT:core_collection_abstract_load_after' => 'collection',
            str_starts_with($leaf, 'EVENT:') => 'event',
            str_contains($leaf, 'generate_blocks') || str_contains($leaf, 'generateBlock') => 'layout',
            default => 'timer',
        };
    }

    /**
     * Finds the innermost framework timer whose interval contains the given start time,
     * so SQL/API/observer rows can be nested under the timer that triggered them.
     *
     * @param array $rawTimers
     * @param float $eventStart
     * @return array{0: int, 1: ?string}
     */
    private function findParent(array $rawTimers, float $eventStart): array
    {
        $bestDepth = -1;
        $bestLabel = null;
        foreach ($rawTimers as $timer) {
            $timerEnd = $timer['start'] + $timer['duration'];
            if ($timer['start'] <= $eventStart && $eventStart <= $timerEnd && $timer['depth'] > $bestDepth) {
                $bestDepth = $timer['depth'];
                $bestLabel = $timer['leaf'];
            }
        }

        return [$bestDepth + 1, $bestLabel];
    }

    /**
     * @param float $seconds
     * @return string
     */
    private function gradeDuration(float $seconds): string
    {
        if ($seconds >= 0.05) {
            return 'bad';
        }
        if ($seconds >= 0.01) {
            return 'medium';
        }

        return 'timer';
    }

    /**
     * Collapses repeated occurrences of the same leaf label into a single row (summed
     * duration, a count, and every individual occurrence kept under 'occurrences' so the
     * row can still be rendered as separate ticks).
     *
     * @param array $timers
     * @return array
     */
    private function groupTimerEvents(array $timers): array
    {
        $grouped = [];
        foreach ($timers as $timer) {
            $key = $timer['leaf'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'leaf' => $timer['leaf'],
                    'type' => $timer['type'],
                    'path' => $timer['path'],
                    'start' => $timer['start'],
                    'duration' => 0.0,
                    'depth' => $timer['depth'],
                    'memory' => 0,
                    'count' => 0,
                    'occurrences' => [],
                ];
            }
            $grouped[$key]['count']++;
            $grouped[$key]['duration'] += $timer['duration'];
            $grouped[$key]['memory'] += $timer['memory'];
            $grouped[$key]['start'] = min($grouped[$key]['start'], $timer['start']);
            $grouped[$key]['depth'] = min($grouped[$key]['depth'], $timer['depth']);
            $grouped[$key]['occurrences'][] = ['start' => $timer['start'], 'duration' => $timer['duration']];
        }

        return array_values($grouped);
    }

    /**
     * @param string $label
     * @return string|null
     */
    private function expandableLabel(string $label): ?string
    {
        return mb_strlen($label) > 60 ? $label : null;
    }
}
