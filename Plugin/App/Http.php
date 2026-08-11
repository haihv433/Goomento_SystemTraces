<?php

namespace Goomento\SystemTraces\Plugin\App;

use Goomento\SystemTraces\Profiler\TimelineDriver;
use Goomento\SystemTraces\Service\Config;
use Magento\Framework\App\Http as HttpApp;
use Magento\Framework\Profiler;

class Http
{
    /**
     * The timer name Bootstrap::run() hardcodes around the whole application run - see
     * Magento\Framework\App\Bootstrap::run(). It calls Profiler::start('magento') before
     * ::launch() runs (i.e. before any driver exists, so that call is a no-op) and
     * Profiler::stop('magento') after ::launch() returns. If we're the ones flipping the
     * profiler from disabled to enabled in between those two calls, the later stop()
     * throws "Timer \"magento\" has not been started", since the earlier start() never
     * recorded anything. Re-issuing start() ourselves right after enabling closes that gap.
     */
    private const BOOTSTRAP_TIMER = 'magento';

    private TimelineDriver $driver;

    private Config $config;

    public function __construct(TimelineDriver $driver, Config $config)
    {
        $this->driver = $driver;
        $this->config = $config;
    }

    /**
     * @param HttpApp $subject
     * @return void
     */
    public function beforeLaunch(HttpApp $subject): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $wasEnabled = Profiler::isEnabled();
        Profiler::add($this->driver);

        if (!$wasEnabled) {
            Profiler::start(self::BOOTSTRAP_TIMER);
        }
    }
}
