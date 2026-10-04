# Security Guidelines

## Input Sanitization

### User-Provided Text
```php
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;

// Plain text (escapes all HTML)
$safe = Html::escape($user_input);

// Allow basic formatting (admin filter)
$safe = Xss::filterAdmin($user_input);

// Custom allowed tags
$safe = Xss::filter($user_input, ['p', 'br', 'strong']);
```

### URLs
```php
use Drupal\Component\Utility\UrlHelper;

// Validate external URL
if (UrlHelper::isValid($url, TRUE)) {
  // Safe to use
}

// Generate safe internal URL
$url = Url::fromRoute('entity.node.canonical', ['node' => $nid])->toString();
```

## Access Control

### Permissions
Define in `MODULE.permissions.yml`:
```yaml
administer my entities:
  title: 'Administer my entities'
  description: 'Full administrative access'
  restrict access: TRUE
```

### Entity Access Handler
```php
// src/Access/MyEntityAccessControlHandler.php
<?php
namespace Drupal\my_module\Access;

use Drupal\Core\Entity\EntityAccessControlHandler;

class MyEntityAccessControlHandler extends EntityAccessControlHandler {

  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    return match ($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, 'view my entities'),
      'update' => AccessResult::allowedIfHasPermission($account, 'edit my entities'),
      'delete' => AccessResult::allowedIfHasPermission($account, 'delete my entities'),
      default => AccessResult::neutral(),
    };
  }

  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'create my entities');
  }
}
```

### Route Access
```yaml
# my_module.routing.yml
my_module.admin:
  path: '/admin/my-module'
  defaults:
    _controller: '\Drupal\my_module\Controller\AdminController::overview'
  requirements:
    _permission: 'administer my entities'
```

## CSRF Protection

### Forms
Forms automatically include CSRF tokens. Always extend `FormBase` or `ConfigFormBase`.

### Links with Side Effects
```php
use Drupal\Core\Url;

$url = Url::fromRoute('my_module.delete', ['id' => $id]);
$url->setOption('query', [
  'token' => \Drupal::csrfToken()->get('my_module_delete_' . $id),
]);

// Validate in controller
$token = $request->query->get('token');
if (!\Drupal::csrfToken()->validate($token, 'my_module_delete_' . $id)) {
  throw new AccessDeniedHttpException();
}
```

## Database Security

### Entity Query (Preferred)
```php
// Safe - uses parameterized queries internally
$query = $this->entityTypeManager->getStorage('node')->getQuery();
$query->condition('type', $bundle)
  ->condition('status', 1)
  ->accessCheck(TRUE);  // ALWAYS include access check
$ids = $query->execute();
```

### Database API (When Needed)
```php
// Safe - uses placeholders
$result = $this->database->select('my_table', 't')
  ->fields('t', ['id', 'name'])
  ->condition('status', $status)  // Parameterized
  ->execute();
```

## File Upload Security

```php
use Drupal\file\FileUsage\FileUsageInterface;

// Validate file uploads
$validators = [
  'FileExtension' => ['extensions' => 'pdf doc docx'],
  'FileSizeLimit' => ['fileLimit' => 10 * 1024 * 1024], // 10MB
];

// For managed files
$file = file_save_upload('file_field', $validators, 'public://uploads/', 0);
```

## Sensitive Data

### Never Log Sensitive Data
```php
// BAD - NEVER log passwords/tokens
$this->logger->info('User @user logged in with password @pass', [
  '@user' => $username,
  '@pass' => $password,  // NEVER!
]);

// GOOD
$this->logger->info('User @user logged in', ['@user' => $username]);
```

### Environment Variables for Secrets
```php
// Use environment variables, not hardcoded values
$api_key = getenv('MY_API_KEY');

// Or Drupal's Key module for secure key storage
$key = \Drupal::service('key.repository')->getKey('my_api_key');
```

## Common OWASP Vulnerabilities to Avoid

1. **SQL Injection**: Always use Entity Query or Database API with placeholders
2. **XSS**: Always escape output with `Html::escape()` or `Xss::filter()`
3. **CSRF**: Use form tokens, validate on state-changing operations
4. **Broken Access Control**: Always `accessCheck(TRUE)` on queries, use proper permissions
5. **Security Misconfiguration**: Follow Drupal security advisories
6. **Sensitive Data Exposure**: Never log passwords/tokens, use secure key storage
7. **Insecure Deserialization**: Use Drupal's serialization APIs
8. **Insufficient Logging**: Log security events, but not sensitive data
