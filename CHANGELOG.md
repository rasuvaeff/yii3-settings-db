# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

- Initial implementation: `DbSettingsProvider` (`WritableSettingsProvider`),
  `SettingRowMapper`, `Exception\InvalidSettingRowException`, the
  `M260605120000CreateSettingsTable` migration, and Yii3 config-plugin wiring.
- `DbSettingsProvider` accepts an optional `fallback` provider so config `values`
  resolve above definition defaults when no DB row exists.
