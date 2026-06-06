# AGENTS.md — yii3-settings-db

Guidance for AI agents working on this package. Read before changing code.

## What this is

Database-backed writable settings provider for Yii3. Implements
`WritableSettingsProvider` from `rasuvaeff/yii3-settings`, stores runtime
setting overrides in a `settings` table, and leaves types/defaults in core
`SettingDefinition` config. Namespace: `Rasuvaeff\Yii3SettingsDb`.

Public API: `DbSettingsProvider`, `Exception\InvalidSettingRowException`.
`SettingRowMapper` is `@internal` and is the only place that knows DB row ↔ typed
setting conversion. The package ships a `yiisoft/db-migration` migration in
`migrations/` and Yii3 config-plugin wiring in `config/`.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **Unknown or invalid data must fail loudly.** Unknown key →
   `UnknownSettingException`; invalid stored DB row → `InvalidSettingRowException`.
   Never silently skip bad rows.
4. **Preserve the public contract.** Update README + tests with any API change.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image.

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer psalm
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```

Or with Make:

```bash
make build
make cs-fix
make psalm
make test
make test-coverage
make mutation
make release-check
```

`composer.lock` is gitignored (library).
`make test-coverage` and `make mutation` bootstrap `pcov` inside the
`composer:2` container because the base image has no coverage driver.

## Invariants & gotchas

- `has()` means "key is declared in definitions", not "row exists in DB".
- `get()` with no DB row delegates to the optional `fallback` provider; with no
  fallback it returns `SettingDefinition::default`.
- `set()` and `remove()` still validate the key against definitions.
- Explicit config `values` must stay working in Yii3 integration: `di.php` builds
  `DbSettingsProvider` with a `ConfigSettingsProvider` fallback (precedence: DB row
  > config value > default). Do **not** reintroduce `ChainSettingsProvider([Db, Config])` —
  the DB provider's `has()` is always true for defined keys, so the chain would
  shadow the config provider and silently drop `values`.
- Reserved SQL words: raw SQL must quote `"key"` and `"value"`.
- Row mapping is strict for stored ints/floats/arrays; malformed data throws
  `InvalidSettingRowException`.
- Cache is delegated to core `CachedSettingsProvider`; DB writes do not auto-clear cache.
- Code: `declare(strict_types=1)`, `final readonly class`, `#[\Override]`,
  explicit types.

- `examples/` is part of the public contract: keep scripts runnable and update
  `examples/README.md` when example usage changes.

## When you finish

- Update `README.md` (and `examples/` if usage changed); update `CHANGELOG.md`
  when releasing.
- Re-run `composer build`; if the change affects the public API or release
  process, also run `make release-check`. Paste the output.
