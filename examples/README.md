# Examples

| Script | Shows | Needs server? |
|---|---|---|
| `basic-usage.php` | Plain `DbSettingsProvider` + `Settings` facade with SQLite memory DB | No |
| `bootstrap.php` | Shared SQLite bootstrap helpers | No |
| `yii-config.php` | Yii3 config-plugin wiring (`params.php`, `di.php`, migration path) | No |

Run examples from the package root. No host PHP/Composer is assumed, so use Docker:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 php examples/basic-usage.php
docker run --rm -v "$PWD":/app -w /app composer:2 php examples/yii-config.php
```
