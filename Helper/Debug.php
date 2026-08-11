<?php

namespace Goomento\SystemTraces\Helper;

class Debug
{
    private static ?string $rootPath = null;

    /**
     * @param array $trace
     * @param int|null $skipLine
     * @return array
     */
    public static function trace(array $trace = [], ?int $skipLine = null): array
    {
        if (empty($trace)) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $skipLine = $skipLine ?? 1;
        }

        $result = [];
        foreach ($trace as $index => $frame) {
            if ($index < $skipLine) {
                continue;
            }

            $className = '[class]';
            if (isset($frame['class'], $frame['function'])) {
                $className = $frame['class'];

                if (isset($frame['object']) && get_class($frame['object']) !== $frame['class']) {
                    $className = get_class($frame['object']);
                }
            }
            if (preg_match('/Interceptor$/', $className)) {
                $className = '[interceptor]';
            }

            $result[] = [
                'file' => $frame['file'] ?? '[file]',
                'line' => $frame['line'] ?? '[line]',
                'class' => $className,
                'function' => $frame['function'] ?? '[function]',
            ];
        }

        return $result;
    }

    /**
     * @param string $file
     * @return string
     */
    public static function relativePath(string $file): string
    {
        $root = self::getRootPath() . DIRECTORY_SEPARATOR;

        return str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;
    }

    /**
     * @return string
     */
    private static function getRootPath(): string
    {
        if (self::$rootPath === null) {
            self::$rootPath = defined('BP') ? BP : dirname(__DIR__);
        }

        return self::$rootPath;
    }
}
