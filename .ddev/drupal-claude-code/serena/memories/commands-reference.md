# Commands Reference

## Runtime Context
**IMPORTANT**: All commands run **inside the DDEV container**.
- Execute commands directly without `ddev` prefix
- Example: `drush cr` (not `ddev drush cr`)
- Example: `composer install` (not `ddev composer install`)

## Code Quality (REQUIRED before task completion)

### Linting (PHPCS)
```bash
# Auto-fix first
vendor/bin/phpcbf --standard=Drupal,DrupalPractice web/modules/custom/MODULE_NAME/

# Then check
vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom/MODULE_NAME/
```

### Static Analysis (PHPStan Level 5)
```bash
# Uses project phpstan.neon config
vendor/bin/phpstan analyze web/modules/custom/MODULE_NAME/
```

### Testing
```bash
# PHPUnit (all tests for module)
vendor/bin/phpunit web/modules/custom/MODULE_NAME/tests/

# Specific test file
vendor/bin/phpunit web/modules/custom/MODULE_NAME/tests/src/Unit/MyTest.php
```

## Module Management

```bash
# List modules
drush pm:list [--filter=FILTER]
drush pm:list --status=enabled

# Install module
composer require drupal/[module_name]  # Download
drush en [module_name]                  # Enable

# Uninstall module
drush pm:uninstall [module_name]

# Clear cache (run after changes)
drush cache:rebuild
# or shorthand
drush cr
```

## Configuration Management

```bash
# Export active config to files
drush config:export -y

# Import config files to active
drush config:import -y

# Partial import (e.g., reset module install config)
drush config:import --partial --source=[path-to-module]/config/install

# View config diff before import
drush config:export --diff

# View specific config
drush config:get [config.name]

# Set config value
drush config:set [config.name] [key] [value]

# Fresh install from config
drush site:install --existing-config

# Get config sync directory path
drush status --field=config-sync
```

## Entity/Field Operations

```bash
# View fields on entity bundle
drush field:info [entity_type] [bundle]

# Example: view fields on article node
drush field:info node article
```

## Debugging & Logs

```bash
# View recent watchdog logs
drush watchdog:show --count=20

# Clear all logs
drush watchdog:delete all

# Run cron
drush cron

# Site status
drush status
```

## DDEV Environment (Host Commands)

These commands are run **from the host machine**, not inside the container.
Since Claude/Serena run inside DDEV, you typically won't need these.

```bash
# Start/stop (from host)
ddev start
ddev stop

# SSH into container (from host)
ddev ssh

# Database operations (from host)
ddev mysql
ddev import-db --file=dump.sql.gz
ddev export-db --file=dump.sql.gz

# Xdebug toggle (from host)
ddev xdebug
ddev xdebug off
```

## Composer

```bash
# Install dependencies
composer install

# Require new package
composer require drupal/[package]
composer require --dev [package]  # Dev dependency

# Update packages
composer update

# Clear composer cache
composer clear-cache
```

## Git (Project Workflow)

```bash
# Standard git operations
git status
git add .
git commit -m "Message"
git push

# Feature branches
git checkout -b feature/my-feature
git checkout main
git merge feature/my-feature
```
