<?php

namespace Goomento\SystemTraces\Profiler;

use Goomento\SystemTraces\Helper\Debug;

class Db extends \Zend_Db_Profiler
{
    protected $queryBacktrace = [];

    /**
     * @param string $queryText
     * @param int|null $queryType
     * @return int|null
     */
    public function queryStart($queryText, $queryType = null)
    {
        $keyQuery = parent::queryStart($queryText, $queryType);
        if ($keyQuery) {
            $this->queryBacktrace[$keyQuery] = Debug::trace([], 5);
        }

        return $keyQuery;
    }

    /**
     * @param int $keyQuery
     * @return array
     */
    public function getQueryBt($keyQuery): array
    {
        return $this->queryBacktrace[$keyQuery] ?? [];
    }
}
