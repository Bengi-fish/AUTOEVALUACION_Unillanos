<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Base de las pruebas de borrado: usuario Acreditación, campos y ayudantes.
 */
abstract class EliminarKernelBase extends UnillanosKernelTestBase {

  use UserCreationTrait;

  /**
   * Tipos de nodo en los que el rol borra lo propio.
   */
  const TIPOS = [
    'programa',
    'proceso',
    'valoracion',
    'hallazgo',
    'plan_mejoramiento',
    'meta',
    'seguimiento',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'options',
    'node',
    'taxonomy',
    'file',
    'image',
    'media',
    'unillanos_autoeval',
  ];

  /**
   * Usuario con el rol Acreditación.
   */
  protected User $acreditacion;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['filter']);
    // El primer usuario (uid 1) tiene todos los permisos: se reserva.
    User::create(['name' => 'superusuario', 'status' => 1])->save();
    $existentes = array_keys($this->container->get('user.permissions')->getPermissions());
    foreach (['anonymous', 'authenticated'] as $id) {
      $real = Yaml::decode((string) file_get_contents(DRUPAL_ROOT . "/../config/sync/user.role.$id.yml"));
      Role::create([
        'id' => $id,
        'label' => $id,
        'weight' => $real['weight'],
        'permissions' => array_values(array_intersect($real['permissions'], $existentes)),
      ])->save();
    }
    $tipo = MediaType::create(['id' => 'documento', 'label' => 'Documento', 'source' => 'file']);
    $tipo->save();
    $campo = $tipo->getSource()->createSourceField($tipo);
    $almacen = $campo->getFieldStorageDefinition();
    if ($almacen instanceof FieldStorageConfig) {
      $almacen->save();
    }
    $campo->save();
    $tipo->set('source_configuration', ['source_field' => $campo->getName()])->save();
    $this->crearCampoSiFalta('media', 'documento', 'field_proceso');
    $this->crearCampoSiFalta('node', 'valoracion', 'field_documento');
    foreach ([
      ['node', 'proceso', 'field_programa'],
      ['node', 'valoracion', 'field_proceso'],
      ['node', 'hallazgo', 'field_proceso'],
      ['node', 'plan_mejoramiento', 'field_proceso'],
      ['node', 'meta', 'field_plan'],
      ['node', 'meta', 'field_hallazgos'],
      ['node', 'seguimiento', 'field_meta'],
    ] as [$entidad, $bundle, $nombre]) {
      $this->crearCampoSiFalta($entidad, $bundle, $nombre);
    }
    $real = Yaml::decode((string) file_get_contents(DRUPAL_ROOT . '/../config/sync/user.role.acreditacion.yml'));
    $permisos = array_values(array_diff($real['permissions'], ['access navigation']));
    Role::create(['id' => 'acreditacion', 'label' => 'Acreditación', 'permissions' => $permisos])->save();
    $this->acreditacion = $this->usuario(['acreditacion']);
    $this->setCurrentUser($this->acreditacion);
  }

  /**
   * Crea un campo de referencia solo si no existe.
   */
  protected function crearCampoSiFalta(string $entidad, string $bundle, string $nombre): void {
    if (!FieldConfig::loadByName($entidad, $bundle, $nombre)) {
      $this->crearCampo($entidad, $bundle, $nombre, 'entity_reference', ['target_type' => 'node'], -1);
    }
  }

  /**
   * Crea un usuario activo con los roles dados.
   *
   * @param string[] $roles
   *   Ids de rol.
   */
  protected function usuario(array $roles = []): User {
    static $n = 0;
    $usuario = User::create(['name' => 'usuario' . ++$n, 'status' => 1, 'roles' => $roles]);
    $usuario->save();
    return $usuario;
  }

  /**
   * Contenido de un tipo cuyo autor es el usuario dado.
   *
   * @param string $tipo
   *   Tipo de nodo.
   * @param int $autor
   *   Uid del autor (0 = lo cargó la migración).
   * @param array<string, mixed> $valores
   *   Más valores del nodo.
   */
  protected function nodoDe(string $tipo, int $autor, array $valores = []): object {
    return $this->crearNodo($tipo, $valores + ['title' => $tipo . ' de prueba', 'uid' => $autor]);
  }

  /**
   * Un medio «documento» con el autor dado.
   */
  protected function documentoDe(int $autor, array $valores = []): Media {
    // El autor como texto, como llega de la base: el acceso de medios compara
    // el identificador del usuario con `===`.
    $base = ['bundle' => 'documento', 'name' => 'Informe', 'status' => 1, 'uid' => (string) $autor];
    $medio = Media::create($valores + $base);
    $medio->save();
    return $medio;
  }

  /**
   * Reinicia el acceso guardado en memoria (se guarda por petición).
   */
  protected function reiniciarAcceso(): void {
    foreach (['node', 'media'] as $tipo) {
      $this->container->get('entity_type.manager')->getAccessControlHandler($tipo)->resetCache();
    }
  }

  /**
   * Tipos de nodo del rol.
   *
   * @return array<string, array{0: string}>
   *   Un caso por tipo.
   */
  public static function tipos(): array {
    return array_combine(self::TIPOS, array_map(fn($t) => [$t], self::TIPOS));
  }

}
