<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Tests;

use Rasuvaeff\Yii3Settings\SettingDefinition;
use Rasuvaeff\Yii3Settings\SettingType;
use Rasuvaeff\Yii3SettingsDb\Exception\InvalidSettingRowException;
use Rasuvaeff\Yii3SettingsDb\SettingRowMapper;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(SettingRowMapper::class)]
final class SettingRowMapperTest
{
    private SettingRowMapper $mapper;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->mapper = new SettingRowMapper();
    }

    public function readsStringValue(): void
    {
        $definition = new SettingDefinition(key: 'mail.from', type: SettingType::String);

        Assert::same($this->mapper->toValue(['value' => 'admin@example.com'], $definition), 'admin@example.com');
    }

    public function readsIntValue(): void
    {
        $definition = new SettingDefinition(key: 'orders.max_items', type: SettingType::Int);

        Assert::same($this->mapper->toValue(['value' => '250'], $definition), 250);
    }

    public function readsFloatValueFromString(): void
    {
        $definition = new SettingDefinition(key: 'vat.rate', type: SettingType::Float);

        Assert::same($this->mapper->toValue(['value' => '20.5'], $definition), 20.5);
    }

    public function readsFloatValueFromInt(): void
    {
        $definition = new SettingDefinition(key: 'vat.rate', type: SettingType::Float);

        Assert::same($this->mapper->toValue(['value' => 20], $definition), 20.0);
    }

    public static function boolProvider(): iterable
    {
        yield 'bool true' => [true, true];
        yield 'bool false' => [false, false];
        yield 'int 1' => [1, true];
        yield 'int 0' => [0, false];
        yield 'string 1' => ['1', true];
        yield 'string 0' => ['0', false];
        yield 'string empty' => ['', false];
        yield 'string yes' => ['yes', true];
    }

    #[DataProvider('boolProvider')]
    public function readsBoolValue(bool|int|string $raw, bool $expected): void
    {
        $definition = new SettingDefinition(key: 'mail.enabled', type: SettingType::Bool);

        Assert::same($this->mapper->toValue(['value' => $raw], $definition), $expected);
    }

    public function readsArrayValueFromJson(): void
    {
        $definition = new SettingDefinition(key: 'app.features', type: SettingType::Array);

        Assert::same($this->mapper->toValue(['value' => '{"a":1,"b":[true]}'], $definition), ['a' => 1, 'b' => [true]]);
    }

    public function readsArrayValueFromNativeArray(): void
    {
        $definition = new SettingDefinition(key: 'app.features', type: SettingType::Array);

        Assert::same($this->mapper->toValue(['value' => ['a' => 1, 'b' => [true]]], $definition), ['a' => 1, 'b' => [true]]);
    }

    public function serializesStringValue(): void
    {
        $definition = new SettingDefinition(key: 'mail.from', type: SettingType::String);

        Assert::same($this->mapper->toStorage(definition: $definition, value: 'admin@example.com'), 'admin@example.com');
    }

    public function serializesIntValue(): void
    {
        $definition = new SettingDefinition(key: 'orders.max_items', type: SettingType::Int);

        Assert::same($this->mapper->toStorage(definition: $definition, value: 250), '250');
    }

    public function serializesFloatValue(): void
    {
        $definition = new SettingDefinition(key: 'vat.rate', type: SettingType::Float);

        Assert::same($this->mapper->toStorage(definition: $definition, value: 20.5), '20.5');
    }

    public function serializesBoolValue(): void
    {
        $definition = new SettingDefinition(key: 'mail.enabled', type: SettingType::Bool);

        Assert::same($this->mapper->toStorage(definition: $definition, value: true), '1');
        Assert::same($this->mapper->toStorage(definition: $definition, value: false), '0');
    }

    public function serializesArrayValue(): void
    {
        $definition = new SettingDefinition(key: 'app.features', type: SettingType::Array);

        Assert::same($this->mapper->toStorage(definition: $definition, value: ['a' => 1, 'b' => [true]]), '{"a":1,"b":[true]}');
    }

    public function throwsWhenStringStorageContainsNonStringValue(): void
    {
        $definition = new SettingDefinition(key: 'mail.from', type: SettingType::String);

        try {
            $this->mapper->toValue(['value' => 123], $definition);
            Assert::fail('Expected InvalidSettingRowException');
        } catch (InvalidSettingRowException $e) {
            Assert::string($e->getMessage())->contains('Invalid stored string for setting "mail.from": got int');
        }
    }

    public function throwsWhenBoolStorageContainsUnsupportedType(): void
    {
        $definition = new SettingDefinition(key: 'mail.enabled', type: SettingType::Bool);

        try {
            $this->mapper->toValue(['value' => new \stdClass()], $definition);
            Assert::fail('Expected InvalidSettingRowException');
        } catch (InvalidSettingRowException $e) {
            Assert::string($e->getMessage())->contains('Invalid stored bool: got stdClass');
        }
    }

    public function throwsWhenArrayJsonDecodesToNonArray(): void
    {
        $definition = new SettingDefinition(key: 'app.features', type: SettingType::Array);

        try {
            $this->mapper->toValue(['value' => '5'], $definition);
            Assert::fail('Expected InvalidSettingRowException');
        } catch (InvalidSettingRowException $e) {
            Assert::string($e->getMessage())->contains('Invalid stored array for setting "app.features": expected array JSON');
        }
    }

    public function throwsWhenArrayEncodingFails(): void
    {
        $definition = new SettingDefinition(key: 'app.features', type: SettingType::Array);

        try {
            $this->mapper->toStorage(definition: $definition, value: ["bad\xB1" => 'value']);
            Assert::fail('Expected InvalidSettingRowException');
        } catch (InvalidSettingRowException $e) {
            Assert::string($e->getMessage())->contains('Failed to encode array for setting "app.features"');
        }
    }

    public static function invalidRowProvider(): iterable
    {
        yield 'missing value' => [
            new SettingDefinition(key: 'mail.from', type: SettingType::String),
            [],
            'Missing column "value" in setting row',
        ];
        yield 'invalid int string alpha' => [
            new SettingDefinition(key: 'orders.max_items', type: SettingType::Int),
            ['value' => 'abc'],
            'Invalid stored int for setting "orders.max_items": got string',
        ];
        yield 'invalid int string leading garbage' => [
            new SettingDefinition(key: 'orders.max_items', type: SettingType::Int),
            ['value' => 'a12'],
            'Invalid stored int for setting "orders.max_items": got string',
        ];
        yield 'invalid int string trailing garbage' => [
            new SettingDefinition(key: 'orders.max_items', type: SettingType::Int),
            ['value' => '12a'],
            'Invalid stored int for setting "orders.max_items": got string',
        ];
        yield 'invalid int null' => [
            new SettingDefinition(key: 'orders.max_items', type: SettingType::Int),
            ['value' => null],
            'Invalid stored int for setting "orders.max_items": got null',
        ];
        yield 'invalid float' => [
            new SettingDefinition(key: 'vat.rate', type: SettingType::Float),
            ['value' => 'abc'],
            'Invalid stored float for setting "vat.rate": got string',
        ];
        yield 'invalid float object' => [
            new SettingDefinition(key: 'vat.rate', type: SettingType::Float),
            ['value' => new \stdClass()],
            'Invalid stored float for setting "vat.rate": got stdClass',
        ];
        yield 'invalid int bool true' => [
            new SettingDefinition(key: 'orders.max_items', type: SettingType::Int),
            ['value' => true],
            'Invalid stored int for setting "orders.max_items": got bool',
        ];
        yield 'invalid int float' => [
            new SettingDefinition(key: 'orders.max_items', type: SettingType::Int),
            ['value' => 1.5],
            'Invalid stored int for setting "orders.max_items": got float',
        ];
        yield 'invalid array json' => [
            new SettingDefinition(key: 'app.features', type: SettingType::Array),
            ['value' => 'not-json'],
            'Invalid stored JSON for setting "app.features": not-json',
        ];
        yield 'invalid array type' => [
            new SettingDefinition(key: 'app.features', type: SettingType::Array),
            ['value' => 5],
            'Invalid stored array for setting "app.features": got int',
        ];
    }

    #[DataProvider('invalidRowProvider')]
    public function throwsOnInvalidRow(SettingDefinition $definition, array $row, string $message): void
    {
        try {
            $this->mapper->toValue(row: $row, definition: $definition);
            Assert::fail('Expected InvalidSettingRowException');
        } catch (InvalidSettingRowException $e) {
            Assert::string($e->getMessage())->contains($message);
        }
    }
}
