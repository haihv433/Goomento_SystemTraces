<?php

namespace Goomento\SystemTraces\Plugin\Framework\App;

use Goomento\SystemTraces\Service\Config;
use Goomento\SystemTraces\Service\ReportGenerator;
use Magento\Framework\App\FrontControllerInterface;

class FrontController
{
    private ReportGenerator $reportGenerator;

    private Config $config;

    public function __construct(ReportGenerator $reportGenerator, Config $config)
    {
        $this->reportGenerator = $reportGenerator;
        $this->config = $config;
    }

    /**
     * @param FrontControllerInterface $subject
     * @return void
     */
    public function beforeDispatch(FrontControllerInterface $subject): void
    {
        $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        if (!$this->config->isEnabled() || !$this->config->isUrlAllowed($path)) {
            return;
        }

        register_shutdown_function([$this->reportGenerator, 'generate']);
    }
}
