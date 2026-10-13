| Q                       | A        |
|-------------------------|----------|
| Bug fix ?               | yes/no   |
| New feature ?           | yes/no   |
| BC break ?              | yes/no   |
| Issues                  | Fix #... |

## Description

<!--
    Explain the feature/bugfix and why you chose this implementation.
-->

## Checklist

- [ ] Tests added or updated (`./vendor/bin/phpunit`)
- [ ] `./vendor/bin/php-cs-fixer fix --diff` and `./vendor/bin/phpstan analyse` pass
- [ ] No platform-specific code (Linux, macOS and Windows are tested in CI)
- [ ] Documentation updated (`docs/`, README parity table if relevant)
- [ ] Entry added under `Unreleased` in `CHANGELOG.md` (and `UPGRADE.md` for behaviour changes)
