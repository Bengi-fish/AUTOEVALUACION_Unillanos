<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\node\Entity\Node;
use Drupal\unillanos_autoeval\Service\SitioAcreditacion;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba las noticias y el equipo del sitio de Acreditación (Fase 9).
 *
 * Solo salen los publicados; las noticias, en el orden de carga; el equipo,
 * agrupado y por el campo orden; lo vacío o inválido se omite.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class SitioAcreditacionTest extends InstitucionalBaseKernel {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->crearCampo('node', 'noticia', 'body', 'text_with_summary');
    $this->crearCampo('node', 'noticia', 'field_imagen', 'image');
    foreach (['field_cargo', 'field_grupo', 'field_telefono'] as $campo) {
      $this->crearCampo('node', 'miembro_equipo', $campo, 'string');
    }
    $this->crearCampo('node', 'miembro_equipo', 'field_correo', 'email');
    $this->crearCampo('node', 'miembro_equipo', 'field_orden', 'integer');
    $this->setUpCurrentUser([], ['access content']);
  }

  /**
   * El servicio de la prueba.
   */
  protected function servicio(): SitioAcreditacion {
    return $this->container->get(SitioAcreditacion::class);
  }

  /**
   * Las noticias salen en orden de carga y solo las publicadas.
   */
  public function testNoticiasOrdenadasPublicadas(): void {
    foreach (['Primera', 'Segunda', 'Tercera'] as $titulo) {
      Node::create(['type' => 'noticia', 'title' => $titulo, 'status' => 1])->save();
    }
    Node::create(['type' => 'noticia', 'title' => 'Borrador', 'status' => 0])->save();
    $titulos = array_column($this->servicio()->noticias(new CacheableMetadata()), 'title');
    $this->assertSame(['Primera', 'Segunda', 'Tercera'], $titulos);
    $this->assertCount(2, $this->servicio()->noticias(new CacheableMetadata(), 2));
  }

  /**
   * El extracto no lleva etiquetas ni pasa del largo máximo.
   */
  public function testExtractoAcotado(): void {
    $largo = str_repeat('palabra ', 80);
    Node::create([
      'type' => 'noticia',
      'title' => 'Con texto',
      'status' => 1,
      'body' => ['value' => '<p><strong>Hola</strong> mundo.</p>', 'format' => 'plain_text'],
    ])->save();
    Node::create([
      'type' => 'noticia',
      'title' => 'Larga',
      'status' => 1,
      'body' => ['value' => '<p>' . $largo . '</p>', 'format' => 'plain_text'],
    ])->save();
    Node::create(['type' => 'noticia', 'title' => 'Sin texto', 'status' => 1])->save();
    Node::create([
      'type' => 'noticia',
      'title' => 'Con entidades',
      'status' => 1,
      'body' => ['value' => '<p>Ciencia &amp; tecnología&nbsp;hoy.</p>', 'format' => 'plain_text'],
    ])->save();
    $noticias = $this->servicio()->noticias(new CacheableMetadata());
    $this->assertSame('Hola mundo.', $noticias[0]['extracto']);
    $this->assertLessThanOrEqual(SitioAcreditacion::EXTRACTO + 3, mb_strlen($noticias[1]['extracto']));
    $this->assertStringNotContainsString('<', $noticias[1]['extracto']);
    $this->assertSame('', $noticias[2]['extracto']);
    // Sin imagen guardada no se inventa ninguna.
    $this->assertNull($noticias[0]['imagen']);
    // Las entidades se decodifican: Twig escapa una sola vez al imprimir.
    $this->assertStringContainsString('Ciencia & tecnología', $noticias[3]['extracto']);
  }

  /**
   * Sin noticias, la lista queda vacía.
   */
  public function testSinNoticias(): void {
    $this->assertSame([], $this->servicio()->noticias(new CacheableMetadata()));
  }

  /**
   * El equipo se agrupa, va por orden y omite lo vacío o inválido.
   */
  public function testEquipoAgrupado(): void {
    $datos = [
      ['Beta', 'Cargo B', 'Comité', 2, 'beta@example.org', 'Ext. 2'],
      ['Alfa', 'Cargo A', 'Comité', 1, 'no es un correo', ''],
      ['Gama', 'Cargo G', 'Equipo', 3, '', ''],
    ];
    foreach ($datos as [$nombre, $cargo, $grupo, $orden, $correo, $telefono]) {
      Node::create([
        'type' => 'miembro_equipo',
        'title' => $nombre,
        'status' => 1,
        'field_cargo' => $cargo,
        'field_grupo' => $grupo,
        'field_orden' => $orden,
        'field_correo' => $correo,
        'field_telefono' => $telefono,
      ])->save();
    }
    Node::create(['type' => 'miembro_equipo', 'title' => 'Oculto', 'status' => 0, 'field_grupo' => 'Comité'])->save();
    $grupos = $this->servicio()->equipo(new CacheableMetadata());
    $this->assertSame(['Comité', 'Equipo'], array_column($grupos, 'title'));
    $this->assertSame(['Alfa', 'Beta'], array_column($grupos[0]['personas'], 'nombre'));
    // Un correo inválido o un teléfono vacío no aparecen.
    $this->assertArrayNotHasKey('correo', $grupos[0]['personas'][0]);
    $this->assertArrayNotHasKey('telefono', $grupos[0]['personas'][0]);
    $this->assertSame('beta@example.org', $grupos[0]['personas'][1]['correo']);
    $this->assertSame('Ext. 2', $grupos[0]['personas'][1]['telefono']);
    $this->assertSame(['nombre' => 'Gama', 'cargo' => 'Cargo G'], $grupos[1]['personas'][0]);
  }

  /**
   * Los metadatos de caché cubren las listas y los permisos.
   */
  public function testMetadatosDeCache(): void {
    $cache = new CacheableMetadata();
    $this->servicio()->noticias($cache);
    $this->servicio()->equipo($cache);
    $this->assertContains('node_list:noticia', $cache->getCacheTags());
    $this->assertContains('node_list:miembro_equipo', $cache->getCacheTags());
    $this->assertContains('user.permissions', $cache->getCacheContexts());
    $this->assertContains('url.site', $cache->getCacheContexts());
  }

}
