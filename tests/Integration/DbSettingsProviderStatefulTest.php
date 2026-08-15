<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Tests\Integration;

use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Classify;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\PropertyTesting\StateMachine\CommandSequence;
use Rasuvaeff\PropertyTesting\StateMachine\StateMachine;
use Rasuvaeff\Yii3SettingsDb\Tests\Integration\Support\RemoveCommand;
use Rasuvaeff\Yii3SettingsDb\Tests\Integration\Support\SetCommand;
use Rasuvaeff\Yii3SettingsDb\Tests\Integration\Support\SettingsStoreHarness;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;

/**
 * Model-based test over a real in-memory SQLite database: under any interleaving
 * of set (upsert) and remove, {@see \Rasuvaeff\Yii3SettingsDb\DbSettingsProvider::get()}
 * reflects a simple model (the effective value of each key), where remove reverts
 * a key to its definition default rather than dropping it. Exercises
 * set-then-set, remove-then-set and remove-missing interactions the isolated
 * integration cases do not combine.
 */
#[Test]
#[CoversNothing]
final class DbSettingsProviderStatefulTest
{
    #[Property(runs: 100)]
    public function setAndRemoveTrackTheModel(CommandSequence $sequence): void
    {
        $harness = new SettingsStoreHarness(4);

        $kinds = [];

        foreach ($sequence->commands as $command) {
            $kinds[$command::class] = true;
        }

        // Floors rather than shares: a swarmed subset of two commands
        // makes each single-command run about a third of draws, and the
        // gates exist to catch one becoming unreachable.
        Classify::cover(isset($kinds[SetCommand::class]) && !isset($kinds[RemoveCommand::class]), 'writes only', 10.0);
        Classify::cover(isset($kinds[RemoveCommand::class]) && !isset($kinds[SetCommand::class]), 'removals only', 10.0);
        Classify::cover(\count($kinds) === 2, 'both commands interleaved', 10.0);

        StateMachine::check($sequence, static fn(): SettingsStoreHarness => $harness);

        // A key the sequence never touched still reads its definition default.
        Assert::same($harness->snapshot(4)[3], SettingsStoreHarness::DEFAULT_VALUE);
    }

    /** @return array<string, ArbitraryInterface> */
    public static function setAndRemoveTrackTheModelGenerators(): array
    {
        // Swarmed: a sequence may use only one of the two commands. Drawn
        // uniformly, a run that only ever writes — never exercising a removal
        // between two writes of the same key — is astronomically rare.
        return ['sequence' => Gen::swarm(Gen::commands([0, 0, 0], [
            Gen::map(
                Gen::tuple(Gen::intBetween(0, 2), Gen::intBetween(0, 9)),
                static fn(array $pair): SetCommand => new SetCommand($pair[0], $pair[1]),
            ),
            Gen::map(Gen::intBetween(0, 2), static fn(int $index): RemoveCommand => new RemoveCommand($index)),
        ]))];
    }
}
