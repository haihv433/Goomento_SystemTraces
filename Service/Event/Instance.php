<?php

namespace Goomento\SystemTraces\Service\Event;

class Instance
{
    private ?string $classType;

    private array $sequence = [];

    /**
     * @param string|null $classType
     */
    public function __construct(?string $classType = null)
    {
        $this->classType = $classType;
    }

    /**
     * Records the dispatched event's class name, in order - Timeline zips this sequence
     * against matching EVENT: framework timers to label each row with the actual class.
     *
     * @param array $data
     * @return void
     */
    public function addClassToRegisterData(array $data): void
    {
        $class = !empty($data[$this->classType]) ? get_class($data[$this->classType]) : false;

        if ($class) {
            $this->sequence[] = $class;
        }
    }

    /**
     * @return string[]
     */
    public function getSequence(): array
    {
        return $this->sequence;
    }
}
