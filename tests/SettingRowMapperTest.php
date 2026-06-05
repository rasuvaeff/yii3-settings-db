<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3Settings\SettingDefinition;
use Rasuvaeff\Yii3Settings\SettingType;
use Rasuvaeff\Yii3SettingsDb\Exception\InvalidSettingRowException;
use Rasuvaeff\Yii3SettingsDb\SettingRowMapper;

#[CoversClass(SettingRowMapper::class)]
final class SettingRowMapperTest extends TestCase
{
    private SettingRowMapper $mapper;

    #[\Override]
    protected function setUp(): void
    {
        $this->mapper = new SettingRowMapper();
    }

    #[Test]
    public function readsStringValue(): void
    {
        $definition = new SettingDefinition(key: 'mail.from', type: SettingType::String);

        $this->assertSame('admin@example.com', $this->mapper->toValue(['value' => 'admin@example.com'], $definition));
    }

    #[Test]
    public function readsIntValue(): void
    {
        $definition = new SettingDefinition(key: 'orders.max_items', type: SettingType::Int);

        $this->assertSame(250, $this->mapper->toValue(['value' => '250'], $definition));
    }

    #[Test]
    public function readsFloatValueFromString(): void
    {
        $definition = new SettingDefinition(key: 'vat.rate', type: SettingType::Float);

        $this->assertSame(20.5, $this->mapper->toValue(['value' => '20.5'], $definition));
    }

    #[Test]
    public function readsFloatValueFromInt(): void
    {
        $definition = new SettingDefinition(key: 'vat.rate', type: SettingType::Float);

        $this->assertSame(20.0, $this->mapper->toValue(['value' => 20], $definition));
    }

    /**
     * @return iterable<string, array{0: bool|int|string, 1: bool}>
     */
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
    #[Test]
    public function readsBoolValue(bool|int|string $raw, bool $expected): void
    {
        $definition = new SettingDefinition(key: 'mail.enabled', type: SettingType::Bool);

        $this->assertSame($expected, $this->mapper->toValue(['value' => $raw], $definition));
    }

    #[Test]
    public function readsArrayValueFromJson(): void
    {
        $definition = new SettingDefinition(key: 'app.features', type: SettingType::Array);

        $this->assertSame(['a' => 1, 'b' => [true]], $this->mapper->toValue(['value' => '{"a":1,"b":[true]}'], $definition));
    }

    #[Test]
    public function readsArrayValueFromNativeArray(): void
    {
        $definition = new SettingDefinition(key: 'app.features', type: SettingType::Array);

        $this->assertSame(['a' => 1, 'b' => [true]], $this->mapper->toValue(['value' => ['a' => 1, 'b' => [true]]], $definition));
    }

    #[Test]
    public function serializesStringValue(): void
    {
        $definition = new SettingDefinition(key: 'mail.from', type: SettingType::String);

        $this->assertSame('admin@example.com', $this->mapper->toStorage(definition: $definition, value: 'admin@example.com'));
    }

    #[Test]
    public function serializesIntValue(): void
    {
        $definition = new SettingDefinition(key: 'orders.max_items', type: SettingType::Int);

        $this->assertSame('250', $this->mapper->toStorage(definition: $definition, value: 250));
    }

    #[Test]
    public function serializesFloatValue(): void
    {
        $definition = new SettingDefinition(key: 'vat.rate', type: SettingType::Float);

        $this->assertSame('20.5', $this->mapper->toStorage(definition: $definition, value: 20.5));
    }

    #[Test]
    public function serializesBoolValue(): void
    {
        $definition = new SettingDefinition(key: 'mail.enabled', type: SettingType::Bool);

        $this->assertSame('1', $this->mapper->toStorage(definition: $definition, value: true));
        $this->assertSame('0', $this->mapper->toStorage(definition: $definition, value: false));
    }

    #[Test]
    public function serializesArrayValue(): void
    {
        $definition = new SettingDefinition(key: 'app.features', type: SettingType::Array);

        $this->assertSame('{"a":1,"b":[true]}', $this->mapper->toStorage(definition: $definition, value: ['a' => 1, 'b' => [true]]));
    }

    #[Test]
    public function throwsWhenStringStorageContainsNonStringValue(): void
    {
        $definition = new SettingDefinition(key: 'mail.from', type: SettingType::String);

        $this->expectException(InvalidSettingRowException::class);
        $this->expectExceptionMessage('Invalid stored string for setting "mail.from": got int');

        $this->mapper->toValue(['value' => 123], $definition);
    }

    #[Test]
    public function throwsWhenBoolStorageContainsUnsupportedType(): void
    {
        $definition = new SettingDefinition(key: 'mail.enabled', type: SettingType::Bool);

        $this->expectException(InvalidSettingRowException::class);
        $this->expectExceptionMessage('Invalid stored bool: got stdClass');

        $this->mapper->toValue(['value' => new \stdClass()], $definition);
    }

    #[Test]
    public function throwsWhenArrayJsonDecodesToNonArray(): void
    {
        $definition = new SettingDefinition(key: 'app.features', type: SettingType::Array);

        $this->expectException(InvalidSettingRowException::class);
        $this->expectExceptionMessage('Invalid stored array for setting "app.features": expected array JSON');

        $this->mapper->toValue(['value' => '5'], $definition);
    }

    #[Test]
    public function throwsWhenArrayEncodingFails(): void
    {
        $definition = new SettingDefinition(key: 'app.features', type: SettingType::Array);

        $this->expectException(InvalidSettingRowException::class);
        $this->expectExceptionMessage('Failed to encode array for setting "app.features"');

        $this->mapper->toStorage(definition: $definition, value: ["bad\xB1" => 'value']);
    }

    /**
     * @return iterable<string, array{0: SettingDefinition, 1: array<string, mixed>, 2: string}>
     */
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
    #[Test]
    public function throwsOnInvalidRow(SettingDefinition $definition, array $row, string $message): void
    {
        $this->expectException(InvalidSettingRowException::class);
        $this->expectExceptionMessage($message);

        $this->mapper->toValue(row: $row, definition: $definition);
    }
}
