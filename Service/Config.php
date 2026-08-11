<?php

namespace Goomento\SystemTraces\Service;

use Magento\Framework\Component\ComponentRegistrar;

class Config
{
    private ComponentRegistrar $componentRegistrar;

    private ?array $config = null;

    public function __construct(ComponentRegistrar $componentRegistrar)
    {
        $this->componentRegistrar = $componentRegistrar;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (bool)($this->load()['enabled'] ?? true);
    }

    /**
     * @param string $path
     * @return bool
     */
    public function isUrlAllowed(string $path): bool
    {
        $pattern = (string)($this->load()['url_pattern'] ?? '*');

        return $pattern === '' || $pattern === '*' || fnmatch($pattern, $path);
    }

    /**
     * @return bool
     */
    public function isFolderMode(): bool
    {
        return (string)($this->load()['report_path_mode'] ?? 'folder') === 'folder';
    }

    /**
     * @return array
     */
    private function load(): array
    {
        if ($this->config === null) {
            $file = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, 'Goomento_SystemTraces')
                . '/env.php';
            $this->config = is_file($file) ? (array)include $file : [];
        }

        return $this->config;
    }
}
