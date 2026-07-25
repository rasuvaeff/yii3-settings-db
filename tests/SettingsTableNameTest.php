<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Tests;

use InvalidArgumentException;
use Rasuvaeff\Yii3SettingsDb\SettingsTableName;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(SettingsTableName::class)]
final class SettingsTableNameTest
{
    public function defaultsToTheDocumentedName(): void
    {
        Assert::same((new SettingsTableName())->value, 'settings');
        Assert::same((string) new SettingsTableName(), 'settings');
    }

    public function acceptsASchemaQualifiedName(): void
    {
        Assert::same((new SettingsTableName('public.settings'))->value, 'public.settings');
    }

    public function indexBaseFlattensTheSchemaSeparator(): void
    {
        // a dot cannot appear in an index name
        Assert::same((new SettingsTableName('public.settings'))->forIndexName(), 'public_settings');
        Assert::same((new SettingsTableName('settings'))->forIndexName(), 'settings');
    }

    #[DataProvider('invalidNamesProvider')]
    public function rejectsAnythingOutsideTheIdentifierWhitelist(string $name): void
    {
        Expect::exception(InvalidArgumentException::class);

        new SettingsTableName($name);
    }

    public static function invalidNamesProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'starts with digit' => ['1table'];
        yield 'space' => ['my table'];
        yield 'semicolon injection' => ['t; DROP TABLE users'];
        yield 'dash' => ['my-table'];
        yield 'two dots' => ['a.b.c'];
        // PCRE's $ also matches before a trailing newline — the pattern is
        // anchored with \z so this is rejected
        yield 'trailing newline' => ["settings\n"];
    }
}
