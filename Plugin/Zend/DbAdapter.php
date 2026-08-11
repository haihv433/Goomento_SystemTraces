<?php

namespace Goomento\SystemTraces\Plugin\Zend;

use Goomento\SystemTraces\Profiler\Db;
use Goomento\SystemTraces\Service\Config;
use Zend_Db_Adapter_Abstract;

class DbAdapter
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @param Zend_Db_Adapter_Abstract $subject
     * @param array|bool|Zend_Config|Zend_Db_Profiler $profiler
     * @return array
     */
    public function beforeSetProfiler(Zend_Db_Adapter_Abstract $subject, $profiler): array
    {
        if ($this->config->isEnabled()) {
            $profiler = [
                'enabled' => 1,
                'class' => Db::class,
            ];
        }

        return [$profiler];
    }
}
