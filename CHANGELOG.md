# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
