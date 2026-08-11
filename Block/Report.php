<?php

namespace Goomento\SystemTraces\Block;

use Goomento\SystemTraces\Helper\Debug;
use Goomento\SystemTraces\Service\Timeline;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Report extends Template
{
    private Timeline $timelineService;

    private ?array $timelineData = null;

    public function __construct(Context $context, Timeline $timelineService, array $data = [])
    {
        parent::__construct($context, $data);
        $this->timelineService = $timelineService;
    }

    /**
     * @return array
     */
    public function getEvents(): array
    {
        return $this->getTimelineData()['events'] ?? [];
    }

    /**
     * @return array
     */
    public function getSummary(): array
    {
        return $this->getTimelineData()['summary'] ?? [];
    }

    /**
     * @param float $seconds
     * @param int $decimals
     * @return string
     */
    public function formatMs(float $seconds, int $decimals = 2): string
    {
        return number_format(round(1000 * $seconds, $decimals), $decimals) . 'ms';
    }

    /**
     * @param float $bytes
     * @return string
     */
    public function formatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $power = $bytes ? (int)floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);
        $bytes /= (1 << (10 * $power));

        return round($bytes, 1) . ' ' . $units[$power];
    }

    /**
     * @param array $backtrace
     * @return string
     */
    public function formatBt(array $backtrace): string
    {
        if (empty($backtrace)) {
            return '';
        }

        $lines = [];
        foreach ($backtrace as $index => $frame) {
            $lines[] = sprintf(
                '#%d %s %s->%s()',
                $index,
                $this->escapeHtml(Debug::relativePath($frame['file']) . '(' . $frame['line'] . ')'),
                $this->escapeHtml($frame['class']),
                $this->escapeHtml($frame['function'])
            );
        }

        return '<details class="st-trace"><summary>' . count($lines) . ' frame(s)</summary>'
            . '<span class="st-trace-body">' . implode('<br/>', $lines) . '</span></details>';
    }

    /**
     * @return array
     */
    private function getTimelineData(): array
    {
        if ($this->timelineData === null) {
            $this->timelineData = $this->timelineService->pullData();
        }

        return $this->timelineData;
    }
}
