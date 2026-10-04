# Task Completion Checklist

## REQUIRED Before Marking ANY Task Complete

### 1. Code Quality Gates (MANDATORY)

#### PHPCS - Coding Standards
```bash
# Run auto-fix first
vendor/bin/phpcbf --standard=Drupal,DrupalPractice web/modules/custom/MODULE_NAME/

# Then verify
vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom/MODULE_NAME/
```
**Must pass with zero errors.**

#### PHPStan - Static Analysis (Level 5)
```bash
vendor/bin/phpstan analyze web/modules/custom/MODULE_NAME/
```
**Must pass with zero errors.**

### 2. Workflow
1. Write/modify code
2. Run PHPCBF (auto-fix)
3. Run PHPCS (verify)
4. Run PHPStan (verify)
5. If any check fails → fix issues → repeat
6. Only when ALL pass → mark task complete

### 3. Configuration Changes
If you modified configuration:
```bash
# Export to files
drush config:export -y

# Verify no unintended changes
drush config:export --diff
```

If you created install config (`config/install/*.yml`):
- Ensure matching schema in `config/schema/`
- Apply same config to active: `drush config:import --partial --source=path/to/config/install`

### 4. Cache Clearing
After any significant changes:
```bash
drush cache:rebuild
```

### 5. Testing (If Applicable)
```bash
# Run module tests
vendor/bin/phpunit web/modules/custom/MODULE_NAME/tests/
```

## Code Review Checklist

### PHP/Drupal
- [ ] Follows Drupal coding standards
- [ ] Uses PHP 8.3+ features appropriately
- [ ] Constructor property promotion for DI
- [ ] PHP 8 attributes for plugins (not annotations)
- [ ] Strict types declared where required (entities, interfaces, enums)
- [ ] All properties have type declarations
- [ ] PHPDoc on all methods and properties
- [ ] No `\Drupal::service()` static calls (use DI)
- [ ] Proper exception handling with `@throws`
- [ ] Input sanitization (`Xss::filter()`, `Html::escape()`)

### Architecture
- [ ] Services have interfaces
- [ ] Plugins follow attribute pattern
- [ ] Events used for extensibility points
- [ ] Result objects for operation outcomes
- [ ] Traits for shared behavior

### Security
- [ ] User input sanitized
- [ ] Access control implemented
- [ ] No SQL injection (use Entity Query/Database API)
- [ ] CSRF tokens for forms

### Files
- [ ] Files under 2000 lines (split if larger)
- [ ] Clear separation of concerns
- [ ] No orphaned/unused code

## Quick Reference: If Check Fails

| Check | Action |
|-------|--------|
| PHPCS fails | Run `phpcbf` first, then fix remaining manually |
| PHPStan fails | Fix type errors, add type hints, check nullable handling |
| Tests fail | Debug, fix code or update tests if behavior changed |
| Config mismatch | `drush config:export -y` after changes |

## DO NOT Mark Complete If:
- ❌ Any PHPCS errors remain
- ❌ Any PHPStan errors remain
- ❌ Tests are failing
- ❌ Config not exported after changes
- ❌ Unrelated files were modified unintentionally
