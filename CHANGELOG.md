# Changelog

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

