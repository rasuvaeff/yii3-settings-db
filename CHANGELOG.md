# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

- Config-plugin DI now also binds `SettingsInspector` to the same
  `DbSettingsProvider` instance.
- Turnkey encryption: the DI builds `KeyRing` + `SodiumCipher` from
  `rasuvaeff/yii3-settings-db.cipher` (`key_id`, `key`) — no manual crypto wiring
  in the host app. `key` defaults to `null` (encryption disabled).
- `DbSettingsProvider` implements the new `SettingsInspector::describeAll()`.
- `readonly` definitions: `set()`/`remove()` throw `ReadonlySettingException`
  and `describe()` reports `isWritable: false`.
- Requires `rasuvaeff/yii3-settings` with `describeAll()` / definition metadata.

## 1.1.1 — 2026-06-10

- Require `rasuvaeff/yii3-settings` ^2.0.1 (the release with the PSR-16-compliant,
  dot-separated `CachedSettingsProvider` cache key).
- Tests use `yiisoft/test-support` doubles (`MemorySimpleCache`) instead of
  hand-rolled cache fakes.

## 1.1.0 — 2026-06-09

- Require `rasuvaeff/yii3-settings` ^2.0 and drop the `Settings` binding from
  `config/di.php` — the core now binds the `Settings` facade. This package binds
  only `WritableSettingsProvider` and `SettingsProvider`, so installing it next to
  the core no longer triggers the `Duplicate key "...\SettingsProvider"` /
  `Duplicate key "...\Settings"` config errors. Runtime behaviour (DB row > config
  `value` > definition default) is unchanged.

## 1.0.0 — 2026-06-07

- Initial implementation: `DbSettingsProvider` (`WritableSettingsProvider` +
  `SettingsInspector`), `SettingRowMapper`, `Exception\InvalidSettingRowException`,
  the `M260605120000CreateSettingsTable` migration, and Yii3 config-plugin wiring.
- `DbSettingsProvider` accepts an optional `fallback` provider so config `values`
  resolve above definition defaults when no DB row exists.
- At-rest encryption for `secret` settings via `Crypto\SodiumCipher` +
  `Crypto\KeyRing` (XChaCha20-Poly1305), including `reencryptSecrets()` for key
  rotation.
- Requires `rasuvaeff/yii3-settings` ^1.1, `yiisoft/db` ^2.0 and
  `yiisoft/db-migration` ^2.0. Consumers must provide a PSR-16 cache
  implementation (a transitive requirement of yiisoft/db 2.0).
