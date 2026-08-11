<?php

namespace Goomento\SystemTraces\Plugin\Framework\Http;

use Goomento\SystemTraces\Helper\Debug;
use Goomento\SystemTraces\Service\Config;
use Goomento\SystemTraces\Service\ExternalApi;
use Magento\Framework\HTTP\ClientInterface;

class Client
{
    private ExternalApi $service;

    private Config $config;

    public function __construct(ExternalApi $service, Config $config)
    {
        $this->service = $service;
        $this->config = $config;
    }

    /**
     * @param ClientInterface $subject
     * @param callable $proceed
     * @param string $uri
     * @return mixed
     */
    public function aroundGet(ClientInterface $subject, callable $proceed, $uri)
    {
        if (!$this->config->isEnabled()) {
            return $proceed($uri);
        }

        return $this->trace($subject, $proceed, 'GET', $uri, [$uri]);
    }

    /**
     * @param ClientInterface $subject
     * @param callable $proceed
     * @param string $uri
     * @param array $params
     * @return mixed
     */
    public function aroundPost(ClientInterface $subject, callable $proceed, $uri, $params = [])
    {
        if (!$this->config->isEnabled()) {
            return $proceed($uri, $params);
        }

        return $this->trace($subject, $proceed, 'POST', $uri, [$uri, $params]);
    }

    /**
     * @param ClientInterface $subject
     * @param callable $proceed
     * @param string $method
     * @param string $uri
     * @param array $args
     * @return mixed
     */
    private function trace(ClientInterface $subject, callable $proceed, string $method, string $uri, array $args)
    {
        $bt = Debug::trace([], 4);
        $start = microtime(true);
        $source = preg_replace('/\\\\Interceptor$/', '', get_class($subject));

        try {
            $result = $proceed(...$args);
            $this->service->addCall($method, $uri, (int)$subject->getStatus(), microtime(true) - $start, $bt, $source, $start);

            return $result;
        } catch (\Throwable $exception) {
            $this->service->addCall($method, $uri, null, microtime(true) - $start, $bt, $source, $start);
            throw $exception;
        }
    }
}
