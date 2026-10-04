# Project Overview

## Purpose
Drupal 11 development environment with Claude Code AI assistant integration.

## Runtime Context
**IMPORTANT**: Claude Code and Serena MCP run **inside the DDEV container**.
- All commands execute directly without `ddev` prefix
- Example: use `drush cr` not `ddev drush cr`
- Working directory: `/var/www/html`
- Web root: `/var/www/html/web`

## Serena Integration
Serena MCP is configured for **PHP + TypeScript** language support.

### Preferred Workflow
**Always use Serena's semantic tools before manual file editing:**

1. **Discovery** - Use `find_symbol` to locate classes, methods, functions
2. **Reading** - Use `get_symbols_overview` for file structure, `find_symbol` with `include_body=true` for implementation details
3. **Editing** - Use `insert_after_symbol`, `insert_before_symbol`, `replace_symbol_body` for precise modifications
4. **References** - Use `find_referencing_symbols` to understand dependencies before refactoring

### Benefits
- Token-efficient: Read only what's needed, not entire files
- Precise edits: Modify specific symbols without risk of line number drift
- Context-aware: Understand symbol relationships and dependencies

### When to Use Manual Editing
- Non-code files (YAML, JSON, markdown)
- Files without parseable symbols
- Small targeted line-based edits within a symbol body

## Tech Stack
| Component | Version | Notes |
|-----------|---------|-------|
| Drupal | 11.x | Core CMS platform |
| PHP | 8.3+ | Required, use modern features |
| Database | MariaDB 10.11 | Via DDEV |
| Web Server | nginx-fpm | Via DDEV |
| Package Manager | Composer 2 | With Corepack enabled |
| CLI | Drush 13 | Site management |
| Local Dev | DDEV | Containerized environment |

## Directory Structure
```
/var/www/html/
├── web/                      # Drupal webroot
│   ├── core/                 # Drupal core (DO NOT MODIFY)
│   ├── modules/
│   │   ├── contrib/          # Contributed modules (DO NOT MODIFY)
│   │   └── custom/           # Custom modules (DEVELOPMENT TARGET)
│   ├── themes/
│   │   ├── contrib/          # Contributed themes
│   │   └── custom/           # Custom themes
│   └── sites/
├── config/sync/              # Configuration sync directory
├── vendor/                   # Composer dependencies (DO NOT MODIFY)
├── .ddev/                    # DDEV configuration
├── .serena/                  # Serena MCP configuration
├── composer.json
├── phpstan.neon              # PHPStan config
└── CLAUDE.md                 # Development guidelines
```

## Environment Access
- **URL**: https://[project-name].ddev.site
- **SSH**: `ddev ssh` (from host)
- **Database**: MariaDB via `ddev mysql` (from host)
