# Architecture Patterns

## Module Structure (Drupal 11 Modern)
```
web/modules/custom/my_module/
├── config/
│   ├── install/              # Default configuration (YAML)
│   └── schema/               # Configuration schema
├── src/
│   ├── Access/               # Access control handlers
│   ├── Attribute/            # PHP 8 plugin attributes
│   ├── Controller/           # Route controllers
│   ├── Drush/Commands/       # Drush command classes
│   ├── Entity/               # Entity types
│   ├── Enum/                 # PHP 8.1 enums
│   ├── Event/                # Event classes
│   ├── EventSubscriber/      # Event listeners
│   ├── Exception/            # Custom exceptions
│   ├── Form/                 # Form classes
│   ├── Hook/                 # OOP hooks (Drupal 11)
│   ├── ListBuilder/          # Entity list builders
│   ├── Plugin/               # Plugin implementations
│   ├── PluginBase/           # Base classes for plugins
│   ├── PluginInterfaces/     # Plugin interfaces
│   ├── PluginManager/        # Plugin manager services
│   ├── Result/               # Result value objects
│   ├── Service/              # Service classes
│   │   └── Traits/           # Shared service behavior
│   └── Traits/               # General traits
├── tests/
│   ├── fixtures/             # Test data files
│   ├── modules/              # Test-only modules
│   └── src/
│       ├── Functional/       # Browser tests
│       ├── Kernel/           # Integration tests
│       ├── Traits/           # Test helper traits
│       └── Unit/             # Unit tests
├── css/                      # Stylesheets
├── js/                       # JavaScript
├── docs/                     # Documentation
├── my_module.info.yml        # Module info
├── my_module.services.yml    # Service definitions
├── my_module.routing.yml     # Routes
├── my_module.permissions.yml # Permissions
├── my_module.libraries.yml   # Asset libraries
└── my_module.install         # Install/update hooks
```

## Plugin System (PHP 8 Attributes)

### Attribute Definition
```php
// src/Attribute/MyPlugin.php
<?php
namespace Drupal\my_module\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;

#[\Attribute(\Attribute::TARGET_CLASS)]
class MyPlugin extends Plugin {
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup|string $label,
    public readonly ?TranslatableMarkup $description = NULL,
  ) {}
}
```

### Plugin Implementation
```php
// src/Plugin/MyPluginType/ConcretePlugin.php
<?php
namespace Drupal\my_module\Plugin\MyPluginType;

use Drupal\my_module\Attribute\MyPlugin;

#[MyPlugin(
  id: 'concrete_plugin',
  label: new TranslatableMarkup('Concrete Plugin'),
  description: new TranslatableMarkup('Does something specific'),
)]
class ConcretePlugin extends PluginBase {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected SomeServiceInterface $someService,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('my_module.some_service'),
    );
  }
}
```

## Result Objects Pattern
```php
// src/Result/OperationResult.php
<?php
namespace Drupal\my_module\Result;

class OperationResult {
  protected bool $success;
  protected array $messages = [];
  protected array $data = [];

  public function __construct(bool $success = TRUE) {
    $this->success = $success;
  }

  public function isSuccessful(): bool {
    return $this->success;
  }

  public function addMessage(string $message): static {
    $this->messages[] = $message;
    return $this;
  }
}
```

## Event-Driven Architecture
```php
// src/Event/MyEvent.php
<?php
namespace Drupal\my_module\Event;

use Drupal\Component\EventDispatcher\Event;

class MyEvent extends Event {
  public function __construct(
    protected readonly EntityInterface $entity,
    protected array $context = [],
  ) {}

  public function getEntity(): EntityInterface {
    return $this->entity;
  }
}

// Dispatching
$event = new MyEvent($entity, ['action' => 'create']);
$this->eventDispatcher->dispatch($event, MyEvents::PRE_EXECUTE);
```

## Service Pattern with Interface
```php
// src/Service/MyServiceInterface.php
<?php
namespace Drupal\my_module\Service;

interface MyServiceInterface {
  public function process(array $data): ResultInterface;
}

// src/Service/MyService.php
<?php
namespace Drupal\my_module\Service;

class MyService implements MyServiceInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected EventDispatcherInterface $eventDispatcher,
  ) {}

  public function process(array $data): ResultInterface {
    // Implementation
  }
}
```

## OOP Hooks (Drupal 11)
```php
// src/Hook/MyModuleHooks.php
<?php
namespace Drupal\my_module\Hook;

use Drupal\Core\Hook\Attribute\Hook;

class MyModuleHooks {

  #[Hook('entity_presave')]
  public function entityPresave(EntityInterface $entity): void {
    // Hook implementation
  }
}
```

## Configuration Pattern
- Install config: `config/install/*.yml`
- Schema: `config/schema/my_module.schema.yml`
- Always export config after changes: `drush config:export -y`
- Apply changes to both config/install AND active config

## Testing Strategy
- **Unit**: Pure logic, mocked dependencies (`tests/src/Unit/`)
- **Kernel**: Drupal integration, real services (`tests/src/Kernel/`)
- **Functional**: Browser/HTTP tests (`tests/src/Functional/`)
- Use Traits for shared test helpers (`tests/src/Traits/`)
