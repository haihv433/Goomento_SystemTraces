<?php

namespace Goomento\SystemTraces\Service;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\State;
use Magento\Framework\Filesystem;
use Magento\Framework\View\LayoutFactory;
use Psr\Log\LoggerInterface;

class ReportGenerator
{
    private const LAYOUT_HANDLE = 'system_traces';

    private const BLOCK_NAME = 'st.report';

    private const REPORT_DIR = 'system_traces';

    private LayoutFactory $layoutFactory;

    private Filesystem $filesystem;

    private State $appState;

    private LoggerInterface $logger;

    private Timeline $timelineService;

    private Config $config;

    public function __construct(
        LayoutFactory $layoutFactory,
        Filesystem $filesystem,
        State $appState,
        LoggerInterface $logger,
        Timeline $timelineService,
        Config $config
    ) {
        $this->layoutFactory = $layoutFactory;
        $this->filesystem = $filesystem;
        $this->appState = $appState;
        $this->logger = $logger;
        $this->timelineService = $timelineService;
        $this->config = $config;
    }

    /**
     * @return void
     */
    public function generate(): void
    {
        try {
            $this->timelineService->pullData();

            $layout = $this->layoutFactory->create(['area' => $this->appState->getAreaCode()]);
            $layout->getUpdate()->addHandle(self::LAYOUT_HANDLE);
            $layout->getUpdate()->load();
            $layout->generateXml();
            $layout->generateElements();

            $block = $layout->getBlock(self::BLOCK_NAME);
            $body = $block ? $block->toHtml() : '';

            $relativePath = self::REPORT_DIR . '/' . $this->buildRelativePath();
            $dir = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
            $dir->create(dirname($relativePath));
            $dir->writeFile($relativePath, $this->buildDocument($body));
        } catch (\Throwable $exception) {
            $this->logger->warning('System Traces report generation failed: ' . $exception->getMessage());
        }
    }

    /**
     * @param string $body
     * @return string
     */
    private function buildDocument(string $body): string
    {
        $title = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/', ENT_QUOTES);

        return '<!doctype html><html><head><meta charset="utf-8"><title>System Traces - ' . $title . '</title></head>'
            . '<body><div id="st-report">' . $body . '</div></body></html>';
    }

    /**
     * @return string
     */
    private function buildRelativePath(): string
    {
        $requestPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', trim($requestPath, '/')), static fn ($segment) => $segment !== ''));
        $segments = array_map([$this, 'slugify'], $segments) ?: ['index'];

        $querySlug = $this->slugify((string)($_SERVER['QUERY_STRING'] ?? ''));
        $timestamp = $this->buildTimestamp();

        if ($this->config->isFolderMode()) {
            $filename = $querySlug !== '' ? $timestamp . '__' . $querySlug : $timestamp;

            return implode('/', $segments) . '/' . $filename . '.html';
        }

        $filename = implode('_', $segments);
        if ($querySlug !== '') {
            $filename .= '__' . $querySlug;
        }
        if (strlen($filename) > 150) {
            $filename = substr($filename, 0, 150) . '_' . substr(sha1($filename), 0, 8);
        }

        return $filename . '_' . $timestamp . '.html';
    }

    /**
     * @return string
     */
    private function buildTimestamp(): string
    {
        $now = microtime(true);

        return date('Ymd-His', (int)$now) . sprintf('%03d', ($now - floor($now)) * 1000);
    }

    /**
     * @param string $value
     * @return string
     */
    private function slugify(string $value): string
    {
        return trim((string)preg_replace('/[^A-Za-z0-9_\-]+/', '_', $value), '_');
    }
}
