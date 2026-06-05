<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb;

use Rasuvaeff\Yii3Settings\ConfigSettingsProvider;
use Rasuvaeff\Yii3Settings\Exception\UnknownSettingException;
use Rasuvaeff\Yii3Settings\SettingDefinition;
use Rasuvaeff\Yii3Settings\SettingsProvider;
use Rasuvaeff\Yii3Settings\WritableSettingsProvider;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Query\Query;

/**
 * @api
 */
final readonly class DbSettingsProvider implements WritableSettingsProvider
{
    /**
     * @var array<string, SettingDefinition>
     */
    private array $definitions;

    private SettingRowMapper $rowMapper;

    /**
     * @param array<string, SettingDefinition|array{type: string, default?: mixed}> $definitions
     * @param non-empty-string $table
     * @param SettingsProvider|null $fallback source for keys without a stored DB row
     *                                         (e.g. config `values`); must recognize the same keys
     */
    public function __construct(
        private ConnectionInterface $db,
        array $definitions = [],
        private string $table = 'settings',
        private ?SettingsProvider $fallback = null,
    ) {
        $this->definitions = ConfigSettingsProvider::normalizeDefinitions($definitions);
        $this->rowMapper = new SettingRowMapper();
    }

    #[\Override]
    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    #[\Override]
    public function get(string $key): mixed
    {
        $definition = $this->definition($key);
        $row = (new Query($this->db))
            ->from($this->table)
            ->where(['key' => $key])
            ->one();

        if (!\is_array($row)) {
            if ($this->fallback === null) {
                return $definition->default;
            }

            return $this->fallback->get($key);
        }

        return $this->rowMapper->toValue(row: $row, definition: $definition);
    }

    #[\Override]
    public function set(string $key, mixed $value): void
    {
        $definition = $this->definition($key);

        $this->db->createCommand()
            ->upsert(
                $this->table,
                [
                    'key' => $key,
                    'value' => $this->rowMapper->toStorage(definition: $definition, value: $value),
                ],
            )
            ->execute();
    }

    #[\Override]
    public function remove(string $key): void
    {
        $this->definition($key);

        $this->db->createCommand()
            ->delete($this->table, ['key' => $key])
            ->execute();
    }

    private function definition(string $key): SettingDefinition
    {
        if (!isset($this->definitions[$key])) {
            throw new UnknownSettingException(
                message: sprintf('Unknown setting "%s"', $key),
            );
        }

        return $this->definitions[$key];
    }
}
