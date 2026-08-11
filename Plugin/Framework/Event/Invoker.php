<?php

namespace Goomento\SystemTraces\Plugin\Framework\Event;

use Goomento\SystemTraces\Service\Config;
use Goomento\SystemTraces\Service\Observer as ServiceObserver;
use Magento\Framework\Event\InvokerInterface;
use Magento\Framework\Event\Observer;

class Invoker
{
    private ServiceObserver $serviceObserver;

    private Config $config;

    public function __construct(ServiceObserver $serviceObserver, Config $config)
    {
        $this->serviceObserver = $serviceObserver;
        $this->config = $config;
    }

    /**
     * @param InvokerInterface $subject
     * @param callable $proceed
     * @param array $configuration
     * @param Observer $observer
     * @return void
     */
    public function aroundDispatch(InvokerInterface $subject, callable $proceed, array $configuration, Observer $observer): void
    {
        if (!$this->config->isEnabled() || !empty($configuration['disabled'])) {
            $proceed($configuration, $observer);
            return;
        }

        $start = microtime(true);
        $proceed($configuration, $observer);
        $this->serviceObserver->addObserver(
            $observer->getEvent()->getName(),
            (string)($configuration['instance'] ?? ''),
            $start,
            microtime(true) - $start
        );
    }
}
