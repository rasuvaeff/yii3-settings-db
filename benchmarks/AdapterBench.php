<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Benchmarks;

use Rasuvaeff\Yii3Settings\SettingDefinition;
use Rasuvaeff\Yii3Settings\SettingType;
use Rasuvaeff\Yii3SettingsDb\SettingRowMapper;
use Testo\Bench;

final class AdapterBench
{
    #[Bench(
        callables: [
            'array-type' => [self::class, 'mapArrayType'],
        ],
        calls: 1_000,
        iterations: 10,
    )]
    public static function mapScalarType(): mixed
    {
        $mapper = new SettingRowMapper();
        $definition = new SettingDefinition(key: 'app.timeout', type: SettingType::Int, default: 30);

        return $mapper->toValue(row: ['value' => '120'], definition: $definition);
    }

    public static function mapArrayType(): mixed
    {
        $mapper = new SettingRowMapper();
        $definition = new SettingDefinition(key: 'app.allowed-ips', type: SettingType::Array, default: []);

        return $mapper->toValue(
            row: ['value' => '["10.0.0.1","10.0.0.2","192.168.1.0/24","172.16.0.0/12"]'],
            definition: $definition,
        );
    }
}
