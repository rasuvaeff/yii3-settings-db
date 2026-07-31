# Changelog

## Unreleased

- Docs: the documented `setSourceNamespaces()` migration registration does not
  find the bundled migration and never has — `yiisoft/db-migration` matches the
  PSR-4 map by string prefix and resolves into the core package, so
  `./yii migrate:up` exits 0 having created nothing. Both READMEs now say so and
  give a working `Injector`-based recipe until the upstream fix ships.

## 2.0.0 — 2026-07-25

**Breaking.** See [UPGRADE.md](UPGRADE.md) — an installation that already
applied the migration must rewrite one row in the `migration` table.

- The bundled migration moved to `Rasuvaeff\Yii3SettingsDb\Migration\M260605120000CreateSettingsTable`
  (`src/Migration/`, PSR-4 autoloaded) from a global class in `migrations/`.
  Register it with `setSourceNamespaces()` instead of a `vendor/` path. Being
  autoloadable is what makes it safe to reference in DI at all: with the old
  global class, adding any container definition for it made
  `Yiisoft\Di\Container` fatal at build time in every request, because
  `new ReflectionClass()` ran before the migration runner had required the file.
- **The documented way to rename the table never worked.**
  `M...::class => ['__construct()' => ['table' => ...]]` is ignored:
  `yiisoft/db-migration` builds migrations through `Injector::make()`, which
  resolves arguments by name or type from the container and does not read
  definitions keyed by the migration's class — and a scalar `string $table` has
  no type to resolve. Users following the README silently got the default name.
- The table name is now a typed value object that `Injector` *can* resolve,
  built by `config/di.php` from params. One source of truth: the migration and
  `DbSettingsProvider` cannot disagree any more (in 1.x the runtime read params while the
  migration used its own default, so configuring params pointed the runtime at a
  table the migration had never created).
- New `table_prefix` param, prepended to `table` — a single place to keep
  package tables out of the way of an application's own.
- `DbSettingsProvider` validates the table name (through the same value object)
  — in 1.x it interpolated whatever string it was given straight into the query
  builder, with no identifier check at all.
- The row mapper's integer check is anchored with `\z` instead of `$`: PCRE's
  `$` also matches before a trailing newline.
- Bump `rasuvaeff/property-testing` to `^2.6`.


## 1.1.1 — 2026-06-30

- Add `/benchmarks` and `/Makefile` to `.gitattributes` export-ignore.

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.1.1 — 2026-06-27

- Migrate test suite from PHPUnit to Testo. Internal change, no public API impact.

## 1.1.0 — 2026-06-14

- Console command: `ReencryptSettingsCommand` (`settings:reencrypt`) invokes
  `DbSettingsProvider::reencryptSecrets()` from any Symfony Console /
  `yiisoft/yii-console` application. Requires `symfony/console ^7`.
- Added `getByPrefix()`: single `LIKE` query resolving all defined keys with a
  given prefix through the standard precedence (DB > fallback > default).
- Added `setMany()`: batch upsert in a transaction with upfront validation
  (unknown keys throw `UnknownSettingException`, readonly keys throw
  `ReadonlySettingException`); rolls back entirely on any failure.

## 1.0.0 — 2026-06-13

Initial release.

- `DbSettingsProvider`: DB-backed `WritableSettingsProvider` + `SettingsInspector`
  with a `SettingsProvider` fallback (config `values`). Implements `describe()`
  and `describeAll()`.
- At-rest encryption of secret settings via libsodium (XChaCha20-Poly1305),
  AAD-bound to the setting key: `Crypto\KeyRing`, `Crypto\SodiumCipher`,
  key rotation + `reencryptSecrets()`.
- `readonly` definitions: `set()`/`remove()` throw `ReadonlySettingException`;
  `describe()` reports `isWritable: false`.
- Yii3 config-plugin wiring (`config/di.php`, `config/params.php`): binds
  `WritableSettingsProvider`, `SettingsProvider` and `SettingsInspector` to the
  same instance; builds `KeyRing` + `SodiumCipher` from
  `rasuvaeff/yii3-settings-db.cipher` (`key_id`, `key`) — turnkey encryption.
- Shipped migration for the `settings` table; `SettingRowMapper` for typed
  row (de)serialization.
- Requires `rasuvaeff/yii3-settings` ^1.0.

