# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Session Start
If the Serena MCP tool `mcp__serena__read_memory` is available, read the memories `project-overview` and
`task-completion-checklist` at the start of each session. If it is not available, skip this step.

## Project Overview
This is a Drupal 11 project. Drupal is an open source content management platform.

## Development Environment
- **Platform**: DDEV containerized development environment
- Run every command with the `ddev` prefix (e.g., `ddev drush cr`) from the repository root
- Repository root inside the container: `/var/www/html`
- Composer root: `/var/www/html/drupal`
- Web root: `/var/www/html/drupal/web`
- Configuration sync directory: `/var/www/html/drupal/config/sync`

### Environment Details
| Component | Version |
|-----------|---------|
| PHP | 8.4 |
| Database | MariaDB 11.8 |
| Web Server | nginx-fpm |
| Composer | 2 |
| Drush | 13 |

## Before Marking Any Task as Complete

### Code Quality Gates (MANDATORY)

#### PHPCS - Coding Standards
```bash
# Run from the Composer root (drupal/); ddev exec does this by default.
ddev exec vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom/MODULE_NAME/

# Auto-fix
ddev exec vendor/bin/phpcbf --standard=Drupal,DrupalPractice web/modules/custom/MODULE_NAME/
```
**Must pass with zero errors.**

#### PHPStan - Static Analysis (Level 5)
```bash
ddev exec vendor/bin/phpstan analyze web/modules/custom/MODULE_NAME/
```
**Must pass with zero errors.**

### Workflow
1. Write/modify code
2. Run PHPCBF (auto-fix)
3. Run PHPCS (verify)
4. Run PHPStan (verify)
5. If any check fails → fix issues → repeat
6. Only when ALL pass → mark task complete

## Configuration Management
- **Export configuration**: `ddev drush config:export -y`
- **Import configuration**: `ddev drush config:import -y`
- **Import partial configuration**: `ddev drush config:import --partial --source=[path-to-module/config/install]`
- **Verify configuration**: `ddev drush config:export --diff`
- **View config details**: `ddev drush config:get [config.name]`
- **Change config value**: `ddev drush config:set [config.name] [key] [value]`
- **Install from config**: `ddev drush site:install --existing-config`
- **Get config sync directory**: `ddev drush status --field=config-sync`

## Development Commands
- **List available modules**: `ddev drush pm:list [--filter=FILTER]`
- **List enabled modules**: `ddev drush pm:list --status=enabled [--filter=FILTER]`
- **Download a Drupal module**: `ddev composer require drupal/[module_name]`
- **Install a Drupal module**: `ddev drush en [module_name]`
- **Clear cache**: `ddev drush cache:rebuild`
- **Inspect logs**: `ddev drush watchdog:show --count=20`
- **Delete logs**: `ddev drush watchdog:delete all`
- **Run cron**: `ddev drush cron`
- **Show status**: `ddev drush status`

## Entity Management
- **View fields on entity**: `ddev drush field:info [entity_type] [bundle]`

## Best Practices
- If making configuration changes to a module's config/install, also apply to active configuration
- Always export configuration after making changes
- Check configuration diffs before importing
- If a module provides install configuration, use `config/install` not `hook_install`
- Prefer contrib modules over custom implementations
- Implement access control for all custom entities
- If phpcs/phpstan/phpunit unavailable: `ddev composer require --dev drupal/core-dev`

## File Size Guidelines
- **Maximum file size**: Assess files approaching 2000 lines for splitting
- **When to split**: Files with multiple distinct responsibilities or logical sections
- **PHP splitting strategy**:
  - Extract traits for shared behavior
  - Create service classes for distinct responsibilities
  - Use composition over inheritance
- **JavaScript splitting strategy**:
  - Split by functional concern (e.g., `module.helpers.js`, `module.renderers.js`)
  - Keep related functionality together
  - Use Drupal behaviors pattern with `once()` for initialization
  - Register all files in `*.libraries.yml` with proper dependencies

## Code Style Guidelines
- **PHP Version**: 8.4 (8.3+ compatible; use PHP 8.1+ features)
- **Coding Standard**: Drupal coding standards
- **Indentation**: 2 spaces, no tabs
- **Line Length**: 120 characters maximum
- **Comment**: 80 characters maximum line length, always finishing with a full stop
- **Namespaces**: PSR-4 standard, `Drupal\{module_name}\{subdirectory}`
- **Strict Types**: Add `declare(strict_types=1);` after opening PHP tag in:
  - Entities (always)
  - Interfaces (always)
  - Enums (always)
  - Skip in: Plugins, Services, Forms, Controllers
- **Types**:
  - Always declare property types
  - Use PHP 8.1+ features: enums, attributes, union types, match expressions
  - Nullable: `?string` or `Type|NULL`
  - Constructor property promotion for dependency injection
- **Documentation**:
  - PHPDoc required for all properties and methods
  - Use `{@inheritDoc}` for inherited methods
  - Include `@var` for complex property types
- **Error Handling**: Specific exception types with `@throws` annotations, meaningful messages
- **Plugins**: Use PHP 8 attributes (not annotations)
  - Example: `#[AiAgent(id: 'my_agent', label: new TranslatableMarkup('Label'))]`
- **Enums**: Use PHP 8.1 backed enums with helper methods and match expressions

### Naming Conventions
- **Classes/Interfaces/Traits**: PascalCase (e.g., `AiAgent`, `AgentHelper`)
- **Methods**: camelCase (e.g., `getConnectorData()`, `buildForm()`)
- **Properties**: camelCase for service injection (e.g., `$entityTypeManager`), snake_case acceptable for entity/config properties (e.g., `$bundle_definitions`)
- **Method parameters**: snake_case in traditional constructors (e.g., `$plugin_id`, `$entity_type`)
- **Local variables**: snake_case for clarity (e.g., `$field_name`, `$entity_type`)
- **Enum Cases**: PascalCase (e.g., `case Started`, `case Finished`)
- **Constants**: ALL_CAPS with underscores (e.g., `DEFAULT_TIMEOUT`)

### Class Structure
- Properties before methods (with explicit visibility)
- Dependency injection via constructor with property promotion
- Use service container instead of `\Drupal::service()`
- Use `final` modifier for entity classes
- Use traits for common functionality (e.g., `DependencySerializationTrait`)
- Follow Drupal plugin conventions with PHP 8 attributes

### Example Code Structure
```php
<?php

declare(strict_types=1);

namespace Drupal\my_module\Entity;

/**
 * Defines the My Entity type.
 */
final class MyEntity extends ConfigEntityBase {

  /**
   * The entity ID.
   */
  protected string $id;

  /**
   * The optional description.
   */
  protected ?string $description = NULL;

}
```

### Example Service with Constructor Promotion
```php
<?php

namespace Drupal\my_module\Service;

class MyService {

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

}
```

### Example Plugin with Attributes
```php
<?php

namespace Drupal\my_module\Plugin\MyPluginType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\my_module\Attribute\MyPlugin;

#[MyPlugin(
  id: 'my_plugin_id',
  label: new TranslatableMarkup('My Plugin Label'),
  description: new TranslatableMarkup('Plugin description'),
)]
class MyPluginClass extends PluginBase {
  // Implementation
}
```

### Example Enum
```php
<?php

declare(strict_types=1);

namespace Drupal\my_module\Enum;

enum MyEnum: string {
  case OptionOne = 'option_one';
  case OptionTwo = 'option_two';

  public function getLabel(): string {
    return match ($this) {
      self::OptionOne => 'Option One',
      self::OptionTwo => 'Option Two',
    };
  }
}
```

## Security Guidelines
1. **Sanitize all user input**: Use `Xss::filter()`, `Html::escape()`
2. **Use CSRF tokens**: For forms and links
3. **Check access**: Use access control handlers and permissions
4. **Validate file uploads**: Use file validators
5. **No SQL injection**: Use Entity Query or Database API with placeholders
6. **Never commit secrets**: Use environment variables for API keys

## Frontend Architecture

### SASS/CSS Guidelines
- Use SCSS syntax with BEM naming
- 2 spaces indentation
- Avoid `!important` unless necessary

### JavaScript Guidelines
- ES6+ modules with exports
- Use `const`/`let`, never `var`
- No `console.log()` in committed code
- Use Drupal behaviors for Drupal-specific JS
- Component scripts prefixed with underscore (e.g., `_header.js`)

## Directory Structure
```
/var/www/html/                # Repository root (DDEV project root)
├── .ddev/                    # DDEV configuration
├── datos/                    # Seeds and import data (CSV)
├── docs/                     # Project documentation (docs/fuentes is read-only)
├── plantilla/                # Visual prototype (HTML/CSS/JS)
└── drupal/                   # Composer root
    ├── composer.json
    ├── config/sync/          # Configuration files
    ├── vendor/               # Composer dependencies (DO NOT MODIFY)
    └── web/                  # Drupal webroot
        ├── core/             # Drupal core (DO NOT MODIFY)
        ├── modules/
        │   ├── contrib/      # Contributed modules (DO NOT MODIFY)
        │   └── custom/       # Custom modules (DEVELOPMENT TARGET)
        ├── themes/
        │   ├── contrib/      # Contributed themes
        │   └── custom/       # Custom themes
        └── sites/
```

## Forbidden Actions
- DO NOT modify files in `drupal/web/core/`
- DO NOT modify files in `drupal/vendor/`
- DO NOT modify files in `drupal/web/modules/contrib/`
- DO NOT commit API keys or passwords
- DO NOT use `chmod 777`
- DO NOT force push to main/master branches

## Getting Help
- **Drupal documentation**: https://www.drupal.org/docs
- **Drush commands**: `ddev drush list`
- **Module info**: `ddev drush pm:info [module]`


---

## Contexto del proyecto Unillanos

# CLAUDE.md · Contexto del proyecto

## Qué es
Sistema de Información de Autoevaluación de la **Universidad de los Llanos (Unillanos)**, en **Drupal 11**. Publica, por programa académico y a nivel institucional:

- los procesos de autoevaluación (registro calificado y acreditación en alta calidad);
- las valoraciones por factor y característica;
- las fortalezas y los aspectos por mejorar;
- el plan de mejoramiento (formato FO-GCL-20) con su seguimiento semestral;
- los documentos y un formulario de participación.

Idioma del sitio y de la documentación: **español**. Público: comunidad universitaria y pares evaluadores.

## Documentos que debes leer antes de tocar código
1. `docs/IMPLEMENTACION_DRUPAL.md`: fases, comandos y criterios de "hecho".
2. `docs/mer/MER.md` y `docs/mer/diccionario-datos.md`: modelo de datos (MER v2, 20 entidades).
3. `docs/drupal/modelo-de-contenido.md`: **nombres de máquina y tipos de campo**. Es la fuente de verdad; no inventes nombres.
4. `plantilla/`: prototipo visual (HTML/CSS/JS). El tema de Drupal debe verse y comportarse igual.
5. `docs/fuentes/`: documentos originales. Son de solo lectura.

## Estructura
- `drupal/`: proyecto Composer (docroot `drupal/web`, configuración en `drupal/config/sync`).
- `drupal/web/modules/custom/unillanos_autoeval`: reglas de negocio.
- `drupal/web/modules/custom/unillanos_migrate`: migraciones CSV → Drupal.
- `drupal/web/themes/custom/unillanos`: tema basado en `plantilla/`.
- `datos/semillas/`: catálogos comunes (lineamientos, elementos del modelo CNA 2020 / CESU 2025 / Decreto 1330, escala, estamentos, sedes, facultades, programas, proyectos).
- `datos/importacion/<programa>/`: datos de cada programa. La carpeta contenedora es `/var/www/html/datos/...`.
- `datos/herramientas/plan_excel_a_csv.py`: conversor del Excel FO-GCL-20.

## Entorno y comandos
DDEV, configurado en la raíz del repositorio (`composer_root: drupal`, `docroot: drupal/web`). Usa siempre `ddev …`:
- `ddev drush cr` · `ddev drush cex -y` · `ddev drush cim -y` · `ddev drush updb -y`
- `ddev composer require drupal/<modulo>`
- `ddev drush migrate:import --group=unillanos` / `migrate:rollback`
- Logs: `ddev drush watchdog:show`

## Convenciones
- Nombres de máquina en español, sin tildes, en snake_case (`plan_mejoramiento`, `field_tipo_hallazgo`), tal como aparecen en `modelo-de-contenido.md`.
- Código según los Drupal coding standards. Comentarios y etiquetas de interfaz en español.
- Después de **cualquier** cambio de configuración: `ddev drush cex -y` e incluir `drupal/config/sync` en el commit.
- Nunca edites `web/core`, `web/modules/contrib` ni `vendor/`. Si necesitas un parche, usa `cweagans/composer-patches`.
- Los datos entran por migraciones desde CSV, nunca a mano. Si falta un dato, agrégalo al CSV.
- JS del tema: `Drupal.behaviors` + `once()`. Conserva los atributos `data-ua-*` del prototipo.
- Cada cambio de MER se refleja en los tres documentos: `docs/mer/MER.md`, `diccionario-datos.md` y `modelo-de-contenido.md`.

## Reglas del dominio (no obvias)
- Escala de la Universidad (Tabla 3.1 del informe): Pleno 4,8–5,0 · Alto 4,0–4,7 · Aceptable 3,0–3,9 · Insatisfactorio 2,6–2,9 · No se cumple 1,0–2,5. El grado se calcula; no se digita.
- Las valoraciones se guardan solo para factor y característica. En condiciones de registro calificado se guarda el estado (cumple / cumple parcialmente / no cumple) más el documento soporte.
- Aspectos valorados por debajo de 4 van al plan de mejoramiento; los valorados en 4 van al plan de acción del programa.
- En el Excel FO-GCL-20 una meta puede atender varias oportunidades de mejora y tener varios indicadores, con programación anual de 2024 a 2030. Las hojas `2024-2` … `2030-2` son seguimientos semestrales.
- Algunos planes nombran factores con la numeración CNA 2013 (p. ej. "Factor 5 - Visibilidad"). Para eso existe `equivalencias_factores_plan.csv`.

## Antes de hacer algo destructivo, pregunta
`ddev delete`, `drush sql:drop`, `drush si` sobre una base con contenido, `migrate:rollback` de todo el grupo, `git push --force`, o borrar archivos de `docs/fuentes/`.
