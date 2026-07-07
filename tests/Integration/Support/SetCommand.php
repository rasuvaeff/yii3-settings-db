<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Tests\Integration\Support;

use Rasuvaeff\PropertyTesting\StateMachine\Command;

/**
 * Model-based command: set (upsert) the setting at $index to $value, so get()
 * returns it. The model is `list<int>` of effective values.
 */
final readonly class SetCommand implements Command
{
    public function __construct(
        private int $index,
        private int $value,
    ) {}

    #[\Override]
    public function preCondition(mixed $model): bool
    {
        return true;
    }

    #[\Override]
    public function nextState(mixed $model): mixed
    {
        \assert(is_array($model));

        $model[$this->index] = $this->value;

        return $model;
    }

    #[\Override]
    public function run(mixed $model, mixed $system): mixed
    {
        \assert($system instanceof SettingsStoreHarness && is_array($model));

        $system->set($this->index, $this->value);

        return $system->snapshot(count($model));
    }

    #[\Override]
    public function postCondition(mixed $model, mixed $result): bool
    {
        return $result === $this->nextState($model);
    }

    #[\Override]
    public function __toString(): string
    {
        return 'Set(' . $this->index . ', ' . $this->value . ')';
    }
}
