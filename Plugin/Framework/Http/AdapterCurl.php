<?php

namespace Goomento\SystemTraces\Plugin\Framework\Http;

use Goomento\SystemTraces\Helper\Debug;
use Goomento\SystemTraces\Service\Config;
use Goomento\SystemTraces\Service\ExternalApi;
use Magento\Framework\HTTP\Adapter\Curl;

/**
 * Curl::write() only sets curl options - the request itself (curl_exec) happens in the
 * separate read() call, so method/url captured in write() are carried over to read(),
 * where timing and status become measurable.
 */
class AdapterCurl
{
    private ExternalApi $service;

    private Config $config;

    private array $pending = [];

    public function __construct(ExternalApi $service, Config $config)
    {
        $this->service = $service;
        $this->config = $config;
    }

    /**
     * @param Curl $subject
     * @param callable $proceed
     * @param string $method
     * @param string $url
     * @param string $http_ver
     * @param array $headers
     * @param string $body
     * @return mixed
     */
    public function aroundWrite(
        Curl $subject,
        callable $proceed,
        $method,
        $url,
        $http_ver = '1.1',
        $headers = [],
        $body = ''
    ) {
        if (!$this->config->isEnabled()) {
            return $proceed($method, $url, $http_ver, $headers, $body);
        }

        $this->pending[spl_object_id($subject)] = [
            'method' => $method,
            'url' => $url,
            'bt' => Debug::trace([], 3),
            'start' => microtime(true),
        ];

        return $proceed($method, $url, $http_ver, $headers, $body);
    }

    /**
     * @param Curl $subject
     * @param callable $proceed
     * @return mixed
     */
    public function aroundRead(Curl $subject, callable $proceed)
    {
        $key = spl_object_id($subject);
        if (!isset($this->pending[$key])) {
            return $proceed();
        }

        $call = $this->pending[$key];
        unset($this->pending[$key]);

        try {
            $result = $proceed();
            $status = $subject->getInfo(CURLINFO_HTTP_CODE);
            $this->service->addCall(
                $call['method'],
                $call['url'],
                $status ? (int)$status : null,
                microtime(true) - $call['start'],
                $call['bt'],
                Curl::class,
                $call['start']
            );

            return $result;
        } catch (\Throwable $exception) {
            $this->service->addCall(
                $call['method'],
                $call['url'],
                null,
                microtime(true) - $call['start'],
                $call['bt'],
                Curl::class,
                $call['start']
            );
            throw $exception;
        }
    }
}
