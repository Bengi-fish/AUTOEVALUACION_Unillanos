<?php

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba la lista de documentos de la página /documentos y del programa.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class DocumentosTest extends UnillanosKernelTestBase {

  use UserCreationTrait;

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', ['file_usage']);
    $this->setUpCurrentUser([], ['access content', 'view media']);
    $tipo = MediaType::create(['id' => 'documento', 'label' => 'Documento', 'source' => 'file']);
    $tipo->save();
    $campo = $tipo->getSource()->createSourceField($tipo);
    $almacen = $campo->getFieldStorageDefinition();
    if ($almacen instanceof FieldStorageConfig) {
      $almacen->save();
    }
    $campo->save();
    $tipo->set('source_configuration', ['source_field' => $campo->getName()])->save();
    $this->crearCampo('media', 'documento', 'field_categoria', 'list_string', [
      'allowed_values' => [
        'normatividad' => 'Normatividad',
        'documento_base' => 'Documento base',
        'informe' => 'Informe',
        'acto_administrativo' => 'Acto administrativo',
        'soporte' => 'Soporte',
      ],
    ]);
    $this->crearCampo('media', 'documento', 'field_fecha_publicacion', 'string');
    $this->crearCampo('media', 'documento', 'field_proceso', 'entity_reference', ['target_type' => 'node']);
    $this->crearCampo('media', 'documento', 'field_lineamiento', 'entity_reference', ['target_type' => 'node']);
  }

  /**
   * Crea un documento (archivo y medio).
   *
   * @param string $nombre
   *   Título del documento.
   * @param string $archivo
   *   Nombre del archivo.
   * @param int $bytes
   *   Tamaño del archivo.
   * @param string $categoria
   *   Clave de la categoría.
   * @param array $extra
   *   Otros valores del medio.
   */
  protected function documento(string $nombre, string $archivo, int $bytes, string $categoria, array $extra = []): Media {
    $file = File::create([
      'uri' => 'public://documentos/' . $archivo,
      'filename' => $archivo,
      'filesize' => $bytes,
      'status' => 1,
    ]);
    $file->save();
    $medio = Media::create($extra + [
      'bundle' => 'documento',
      'name' => $nombre,
      'status' => 1,
      'field_media_file' => ['target_id' => $file->id()],
      'field_categoria' => $categoria,
    ]);
    $medio->save();
    return $medio;
  }

  /**
   * Crea un programa con un proceso.
   *
   * @return \Drupal\node\Entity\Node[]
   *   Con programa y proceso.
   */
  protected function programa(string $nombre, array $extra = []): array {
    $programa = $this->crearNodo('programa', ['title' => $nombre, 'field_clave' => strtolower($nombre)] + $extra);
    $proceso = $this->crearNodo('proceso', ['title' => 'Proceso ' . $nombre, 'field_programa' => $programa->id()]);
    return ['programa' => $programa, 'proceso' => $proceso];
  }

  /**
   * Sin documentos no hay nada que mostrar.
   */
  public function testSinDocumentos(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $datos = $presentador->documentosPublicados(new CacheableMetadata());
    $this->assertSame(0, $datos['total']);
    $this->assertSame([], $datos['generales']);
    $this->assertSame([], $datos['programas']);
    $this->assertFalse($datos['salto']);
    $d = $this->programa('Electrónica');
    $this->assertSame([], $presentador->documentosDelPrograma($d['programa'], new CacheableMetadata()));
  }

  /**
   * Cuatro documentos: normativa general y los del programa, bien ordenados.
   */
  public function testCuatroDocumentos(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $modelo = $this->crearLineamiento('Lineamientos modelo 2020');
    $d = $this->programa('Electrónica');
    $this->documento('Acuerdo CESU 02 de 2020', 'acuerdo-2020.pdf', 1168556, 'normatividad', [
      'field_lineamiento' => $modelo->id(),
    ]);
    $this->documento('Acuerdo CESU 01 de 2025', 'acuerdo-2025.docx', 1016012, 'normatividad');
    $this->documento('Plan FO-GCL-20', 'plan.xlsx', 201497, 'documento_base', ['field_proceso' => $d['proceso']->id()]);
    $this->documento('Informe 2022', 'informe.pdf', 5434130, 'informe', ['field_proceso' => $d['proceso']->id()]);

    $cache = new CacheableMetadata();
    $datos = $presentador->documentosPublicados($cache);
    $this->assertSame(4, $datos['total']);
    $this->assertSame(['normatividad'], array_column($datos['generales'], 'categoria'));
    $normativa = $datos['generales'][0]['items'];
    $this->assertSame(['Acuerdo CESU 01 de 2025', 'Acuerdo CESU 02 de 2020'], array_column($normativa, 'title'));
    $this->assertSame('DOCX', $normativa[0]['kind']);
    $this->assertSame('DOCX · 992 KB', $normativa[0]['meta']);
    $this->assertSame('PDF · 1,1 MB', $normativa[1]['meta']);
    $this->assertSame('Normatividad', $normativa[1]['cat']);
    // La descripción sale del lineamiento; sin él, vacía.
    $this->assertSame('Lineamientos modelo 2020', $normativa[1]['desc']);
    $this->assertSame('', $normativa[0]['desc']);
    // En la prueba el sistema de archivos es virtual: solo se mira el final.
    $this->assertStringEndsWith('/documentos/acuerdo-2020.pdf', $normativa[1]['url']);

    // Un programa: primero el informe y luego el documento base.
    $this->assertCount(1, $datos['programas']);
    $this->assertSame('Electrónica', $datos['programas'][0]['nombre']);
    $this->assertSame(['Informe 2022', 'Plan FO-GCL-20'], array_column($datos['programas'][0]['items'], 'title'));
    $this->assertSame('PDF · 5,2 MB', $datos['programas'][0]['items'][0]['meta']);
    $this->assertSame('XLSX · 197 KB', $datos['programas'][0]['items'][1]['meta']);
    $this->assertSame('Proceso Electrónica', $datos['programas'][0]['items'][0]['desc']);
    $this->assertFalse($datos['salto']);
    $this->assertContains('media_list', $cache->getCacheTags());

    $propios = $presentador->documentosDelPrograma($d['programa'], new CacheableMetadata());
    $this->assertCount(2, $propios['items']);
    $this->assertSame('Informe 2022', $propios['informe']['title']);
    $this->assertSame('Plan FO-GCL-20', $propios['plan']['title']);
    $this->assertSame('XLSX', $propios['plan']['kind']);
  }

  /**
   * Lo no publicado, o de un programa que no se ve, no se muestra.
   */
  public function testSinPublicarNiProgramaOculto(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $visible = $this->programa('Visible');
    $oculto = $this->programa('Oculto', ['status' => 0]);
    $proceso_borrador = $this->crearNodo('proceso', [
      'field_programa' => $visible['programa']->id(),
      'status' => 0,
    ]);
    $institucional = $this->crearNodo('proceso', ['title' => 'Proceso institucional']);
    $this->documento('Del visible', 'a.pdf', 1000, 'informe', ['field_proceso' => $visible['proceso']->id()]);
    $this->documento('Sin publicar', 'b.pdf', 1000, 'informe', [
      'status' => 0,
      'field_proceso' => $visible['proceso']->id(),
    ]);
    $this->documento('Del programa oculto', 'c.pdf', 1000, 'informe', ['field_proceso' => $oculto['proceso']->id()]);
    $this->documento('De un proceso sin publicar', 'd.pdf', 1000, 'informe', ['field_proceso' => $proceso_borrador->id()]);
    $this->documento('Institucional', 'e.pdf', 1000, 'soporte', ['field_proceso' => $institucional->id()]);

    $datos = $presentador->documentosPublicados(new CacheableMetadata());
    $this->assertSame(2, $datos['total']);
    $this->assertSame(['Del visible'], array_column($datos['programas'][0]['items'], 'title'));
    // Un proceso sin programa (institucional) es un documento general.
    $this->assertSame(['soporte'], array_column($datos['generales'], 'categoria'));
    $this->assertSame(['Institucional'], array_column($datos['generales'][0]['items'], 'title'));
    $this->assertSame([], $presentador->documentosDelPrograma($oculto['programa'], new CacheableMetadata()));
  }

  /**
   * Con muchos programas se ofrece la lista de salto y salen por nombre.
   */
  public function testMuchosProgramas(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    foreach (['Zoología', 'Biología', 'Contaduría', 'Agronomía', 'Enfermería'] as $nombre) {
      $d = $this->programa($nombre);
      $this->documento('Informe ' . $nombre, strtolower($nombre) . '.pdf', 2048, 'informe', ['field_proceso' => $d['proceso']->id()]);
    }
    $cinco = $presentador->documentosPublicados(new CacheableMetadata());
    $this->assertCount(5, $cinco['programas']);
    $this->assertFalse($cinco['salto']);
    $d = $this->programa('Matemáticas');
    $this->documento('Informe Matemáticas', 'mate.pdf', 2048, 'informe', ['field_proceso' => $d['proceso']->id()]);
    $seis = $presentador->documentosPublicados(new CacheableMetadata());
    $this->assertCount(6, $seis['programas']);
    $this->assertTrue($seis['salto']);
    $nombres = array_column($seis['programas'], 'nombre');
    $ordenados = $nombres;
    usort($ordenados, 'strcoll');
    $this->assertSame($ordenados, $nombres);
    $this->assertSame(6, $seis['total']);
  }

  /**
   * Dentro de una categoría, la fecha más reciente primero.
   */
  public function testFechasOrden(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $this->documento('Antiguo', 'a.pdf', 1000, 'normatividad', ['field_fecha_publicacion' => '2022-01-10']);
    $this->documento('Reciente', 'b.pdf', 1000, 'normatividad', ['field_fecha_publicacion' => '2024-06-19']);
    $this->documento('Sin fecha', 'c.pdf', 1000, 'normatividad');
    $items = $presentador->documentosPublicados(new CacheableMetadata())['generales'][0]['items'];
    $this->assertSame(['Reciente', 'Antiguo', 'Sin fecha'], array_column($items, 'title'));
    $this->assertSame('PDF · 1000 B · junio de 2024', $items[0]['meta']);
    $this->assertSame('PDF · 1000 B', $items[2]['meta']);
  }

  /**
   * El periodo del título no se parte al cambiar de línea.
   *
   * Solo cambia lo que se muestra: el título guardado queda igual.
   */
  public function testPeriodoSinCortes(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $guardado = 'Plan de mejoramiento FO-GCL-20 · 2024-2 – 2030';
    $medio = $this->documento($guardado, 'p.xlsx', 1000, 'documento_base');
    $items = $presentador->documentosPublicados(new CacheableMetadata())['generales'][0]['items'];
    $esperado = "Plan de mejoramiento FO-GCL-20 · 2024-\u{2060}2 –\u{2060}\u{00A0}2030";
    $this->assertSame($esperado, $items[0]['title']);
    // Sin los marcadores, el texto es el mismo que está guardado.
    $this->assertSame($guardado, str_replace(["\u{2060}", "\u{00A0}"], ['', ' '], $items[0]['title']));
    $this->assertSame($guardado, $medio->label());
    // Un título sin periodo no cambia.
    $this->assertSame('FO-GCL-20 sin periodo', $presentador->sinCortesEnPeriodo('FO-GCL-20 sin periodo'));
  }

  /**
   * Los tamaños se escriben con coma decimal y la unidad que corresponde.
   */
  public function testTamano(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $this->assertSame('', $presentador->tamano(0));
    $this->assertSame('', $presentador->tamano(-5));
    $this->assertSame('512 B', $presentador->tamano(512));
    $this->assertSame('197 KB', $presentador->tamano(201497));
    $this->assertSame('992 KB', $presentador->tamano(1016012));
    $this->assertSame('1,1 MB', $presentador->tamano(1168556));
    $this->assertSame('5,2 MB', $presentador->tamano(5434130));
  }

  /**
   * Los tamaños del borde de 1 MB no salen como «1.024 KB».
   */
  public function testTamanoEnElBordeDeUnMegabyte(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $this->assertSame('1.022 KB', $presentador->tamano(1046528));
    $this->assertSame('1.023 KB', $presentador->tamano(1048000));
    $this->assertSame('1,0 MB', $presentador->tamano(1048575));
    $this->assertSame('1,0 MB', $presentador->tamano(1048576));
  }

  /**
   * Los nombres con acentos se ordenan como en español.
   */
  public function testOrdenConAcentos(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    foreach (['Zoología', 'Álgebra', 'Biología', 'Économía'] as $nombre) {
      $d = $this->programa($nombre);
      $this->documento('Informe ' . $nombre, 'a' . md5($nombre) . '.pdf', 2048, 'informe', ['field_proceso' => $d['proceso']->id()]);
    }
    $this->documento('Zeta', 'z.pdf', 100, 'normatividad');
    $this->documento('Ácido', 'x.pdf', 100, 'normatividad');
    $this->documento('Beta', 'y.pdf', 100, 'normatividad');
    $datos = $presentador->documentosPublicados(new CacheableMetadata());
    $this->assertSame(['Álgebra', 'Biología', 'Économía', 'Zoología'], array_column($datos['programas'], 'nombre'));
    $this->assertSame(['Ácido', 'Beta', 'Zeta'], array_column($datos['generales'][0]['items'], 'title'));
  }

  /**
   * Varios informes o varios documentos base: ninguno se destaca como único.
   */
  public function testNoSeDestacaSiHayVarios(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $d = $this->programa('Programa');
    $this->documento('Informe 2018', 'i1.pdf', 100, 'informe', ['field_proceso' => $d['proceso']->id()]);
    $this->documento('Informe 2022', 'i2.pdf', 100, 'informe', ['field_proceso' => $d['proceso']->id()]);
    $this->documento('Plan', 'p1.xlsx', 100, 'documento_base', ['field_proceso' => $d['proceso']->id()]);
    $propios = $presentador->documentosDelPrograma($d['programa'], new CacheableMetadata());
    $this->assertCount(3, $propios['items']);
    $this->assertNull($propios['informe']);
    $this->assertSame('Plan', $propios['plan']['title']);
    $this->documento('Matriz', 'm.xlsx', 100, 'documento_base', ['field_proceso' => $d['proceso']->id()]);
    $propios = $presentador->documentosDelPrograma($d['programa'], new CacheableMetadata());
    $this->assertNull($propios['plan']);
  }

  /**
   * Categoría desconocida, archivo sin extensión y lineamiento sin publicar.
   */
  public function testCasosRaros(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $borrador = $this->crearLineamiento('Lineamiento sin publicar');
    $borrador->setUnpublished()->save();
    $this->documento('Rara', 'sin-extension', 100, 'otra_categoria');
    $this->documento('Con lineamiento oculto', 'l.pdf', 100, 'normatividad', ['field_lineamiento' => $borrador->id()]);
    $datos = $presentador->documentosPublicados(new CacheableMetadata());
    // La categoría desconocida va al final.
    $this->assertSame(['normatividad', 'otra_categoria'], array_column($datos['generales'], 'categoria'));
    $rara = $datos['generales'][1]['items'][0];
    $this->assertSame('DOC', $rara['kind']);
    $this->assertSame('DOC · 100 B', $rara['meta']);
    // El título de un lineamiento sin publicar no se usa como descripción.
    $this->assertSame('', $datos['generales'][0]['items'][0]['desc']);
  }

  /**
   * Sin el permiso de ver medios no se muestra ningún documento.
   */
  public function testSinPermisoDeVerMedios(): void {
    $this->documento('Visible solo con permiso', 'v.pdf', 100, 'normatividad');
    $this->setUpCurrentUser(['uid' => 5], ['access content']);
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $this->assertSame(0, $presentador->documentosPublicados(new CacheableMetadata())['total']);
  }

}
