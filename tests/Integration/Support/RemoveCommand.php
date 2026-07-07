<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Tests\Integration\Support;

use Rasuvaeff\PropertyTesting\StateMachine\Command;

/**
 * Model-based command: remove the stored row for the setting at $index, so get()
 * reverts to the definition default (a no-op when no row is stored). The model is
 * `list<int>` of effective values.
 */
final readonly class RemoveCommand implements Command
{
    public function __construct(private int $index) {}

    #[\Override]
    public function preCondition(mixed $model): bool
    {
        return true;
    }

    #[\Override]
    public function nextState(mixed $model): mixed
    {
        \assert(is_array($model));

        $model[$this->index] = SettingsStoreHarness::DEFAULT_VALUE;

        return $model;
    }

    #[\Override]
    public function run(mixed $model, mixed $system): mixed
    {
        \assert($system instanceof SettingsStoreHarness && is_array($model));

        $system->remove($this->index);

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
        return 'Remove(' . $this->index . ')';
    }
}
