<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb;

use Rasuvaeff\Yii3Settings\SettingDefinition;
use Rasuvaeff\Yii3Settings\SettingType;
use Rasuvaeff\Yii3SettingsDb\Exception\InvalidSettingRowException;

/**
 * @internal
 */
final readonly class SettingRowMapper
{
    /**
     * @param array<array-key, mixed> $row
     */
    public function toValue(array $row, SettingDefinition $definition): mixed
    {
        if (!\array_key_exists('value', $row)) {
            throw new InvalidSettingRowException(
                message: 'Missing column "value" in setting row',
            );
        }

        return match ($definition->type) {
            SettingType::String => $this->parseString(key: $definition->key, value: $row['value']),
            SettingType::Int => $this->parseInt(key: $definition->key, value: $row['value']),
            SettingType::Float => $this->parseFloat(key: $definition->key, value: $row['value']),
            SettingType::Bool => $this->parseBool(value: $row['value']),
            SettingType::Array => $this->parseArray(key: $definition->key, value: $row['value']),
        };
    }

    public function toStorage(SettingDefinition $definition, mixed $value): string
    {
        return match ($definition->type) {
            SettingType::String => $this->stringStorage(value: $definition->cast($value)),
            SettingType::Int => $this->intStorage(value: $definition->cast($value)),
            SettingType::Float => $this->floatStorage(value: $definition->cast($value)),
            SettingType::Bool => $this->boolStorage(value: $definition->cast($value)),
            SettingType::Array => $this->arrayStorage(key: $definition->key, value: $definition->cast($value)),
        };
    }

    private function parseString(string $key, mixed $value): string
    {
        if (!\is_string($value)) {
            throw new InvalidSettingRowException(
                message: sprintf('Invalid stored string for setting "%s": got %s', $key, get_debug_type($value)),
            );
        }

        return $value;
    }

    private function parseInt(string $key, mixed $value): int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new InvalidSettingRowException(
            message: sprintf('Invalid stored int for setting "%s": got %s', $key, get_debug_type($value)),
        );
    }

    private function parseFloat(string $key, mixed $value): float
    {
        if (\is_float($value)) {
            return $value;
        }

        if (\is_int($value)) {
            return $value / 1;
        }

        if (\is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        throw new InvalidSettingRowException(
            message: sprintf('Invalid stored float for setting "%s": got %s', $key, get_debug_type($value)),
        );
    }

    private function parseBool(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_int($value)) {
            return $value !== 0;
        }

        if (\is_string($value)) {
            return $value !== '' && $value !== '0';
        }

        throw new InvalidSettingRowException(
            message: sprintf('Invalid stored bool: got %s', get_debug_type($value)),
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    private function parseArray(string $key, mixed $value): array
    {
        if (\is_array($value)) {
            return $value;
        }

        if (!\is_string($value)) {
            throw new InvalidSettingRowException(
                message: sprintf('Invalid stored array for setting "%s": got %s', $key, get_debug_type($value)),
            );
        }

        try {
            $decoded = json_decode(json: $value, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidSettingRowException(
                message: sprintf('Invalid stored JSON for setting "%s": %s', $key, $value),
            );
        }

        if (!\is_array($decoded)) {
            throw new InvalidSettingRowException(
                message: sprintf('Invalid stored array for setting "%s": expected array JSON', $key),
            );
        }

        return $decoded;
    }

    private function stringStorage(mixed $value): string
    {
        /** @var string $value */
        return $value;
    }

    private function intStorage(mixed $value): string
    {
        /** @var int $value */
        return (string) $value;
    }

    private function floatStorage(mixed $value): string
    {
        /** @var float $value */
        return (string) $value;
    }

    private function boolStorage(mixed $value): string
    {
        /** @var bool $value */
        return $value ? '1' : '0';
    }

    private function arrayStorage(string $key, mixed $value): string
    {
        /** @var array<array-key, mixed> $value */

        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidSettingRowException(
                message: sprintf('Failed to encode array for setting "%s": %s', $key, $e->getMessage()),
                previous: $e,
            );
        }
    }

}
