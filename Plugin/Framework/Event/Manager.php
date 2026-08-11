<?php

namespace Goomento\SystemTraces\Plugin\Framework\Event;

use Goomento\SystemTraces\Service\Config;
use Goomento\SystemTraces\Service\Event\Instance;
use Magento\Framework\Event\ManagerInterface;

class Manager
{
    private Instance $modelService;

    private Instance $collectionService;

    private Config $config;

    public function __construct(Instance $modelService, Instance $collectionService, Config $config)
    {
        $this->modelService = $modelService;
        $this->collectionService = $collectionService;
        $this->config = $config;
    }

    /**
     * @param ManagerInterface $subject
     * @param string $eventName
     * @param array $data
     * @return void
     */
    public function beforeDispatch(ManagerInterface $subject, $eventName, array $data = []): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        if ($eventName === 'model_load_before') {
            $this->modelService->addClassToRegisterData($data);
        } elseif ($eventName === 'core_collection_abstract_load_before') {
            $this->collectionService->addClassToRegisterData($data);
        }
    }
}
