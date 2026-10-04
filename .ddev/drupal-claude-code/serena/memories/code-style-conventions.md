# Code Style & Conventions

## PHP Standards
- **Version**: 8.3+ (use PHP 8.1+ features: enums, attributes, union types, match)
- **Standard**: Drupal + DrupalPractice coding standards
- **Indentation**: 2 spaces, no tabs
- **Line Length**: 120 chars max (80 for comments)
- **Comments**: End with period

## Strict Types Declaration
```php
// USE in: Entities, Interfaces, Enums
<?php
declare(strict_types=1);

// SKIP in: Plugins, Services, Forms, Controllers
<?php
namespace Drupal\my_module\...
```

## Naming Conventions
| Element | Convention | Example |
|---------|------------|---------|
| Classes/Interfaces | PascalCase | `AiAgent`, `AgentHelper` |
| Methods | camelCase | `getConnectorData()` |
| Service Properties | camelCase | `$entityTypeManager` |
| Entity/Config Props | snake_case OK | `$bundle_definitions` |
| Method Parameters | snake_case | `$plugin_id`, `$entity_type` |
| Local Variables | snake_case | `$field_name` |
| Enum Cases | PascalCase | `case Started` |
| Constants | ALL_CAPS | `DEFAULT_TIMEOUT` |

## Class Structure Order
1. Properties (typed, with visibility)
2. Constructor (with property promotion for DI)
3. Methods

## Constructor Property Promotion (Services)
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

## Plugin with PHP 8 Attributes
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

## Enum Pattern
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

## Entity Pattern
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

## PHPDoc Requirements
- All properties and methods must have PHPDoc
- Use `{@inheritDoc}` for inherited methods
- Include `@var` for complex property types
- Use `@throws` annotations for exceptions

## Dependency Injection
- **DO**: Use constructor injection with service container
- **DON'T**: Use `\Drupal::service()` static calls
- Use `DependencySerializationTrait` for serialization in queued contexts

## Frontend
- **SCSS**: BEM naming, 2-space indent, avoid `!important`
- **JS**: ES6+ modules, `const`/`let` only, no `console.log()`
- Use Drupal behaviors pattern with `once()` for initialization
