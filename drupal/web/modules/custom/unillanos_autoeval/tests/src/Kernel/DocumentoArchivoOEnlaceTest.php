<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormState;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\media\Entity\MediaType;
use Drupal\unillanos_autoeval\Hook\DocumentoArchivoOEnlaceHooks;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Prueba el documento con archivo o enlace externo (Fase 9, etapa 1).
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class DocumentoArchivoOEnlaceTest extends UnillanosKernelTestBase {

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
    'link',
    'media',
    'sdc',
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
    // Igual que en el sitio: el archivo ya no es obligatorio.
    $campo->set('required', FALSE)->save();
    $tipo->set('source_configuration', ['source_field' => $campo->getName()])->save();
    $this->crearCampo('media', 'documento', 'field_categoria', 'list_string', [
      'allowed_values' => ['informe' => 'Informe', 'soporte' => 'Soporte'],
    ]);
    $this->crearCampo('media', 'documento', 'field_fecha_publicacion', 'string');
    $this->crearCampo('media', 'documento', 'field_proceso', 'entity_reference', ['target_type' => 'node']);
    $this->crearCampo('media', 'documento', 'field_lineamiento', 'entity_reference', ['target_type' => 'node']);
    $this->crearCampo('media', 'documento', 'field_enlace', 'link');
    $campo_enlace = \Drupal::entityTypeManager()->getStorage('field_config')->load('media.documento.field_enlace');
    $campo_enlace->setSetting('link_type', 16)->setSetting('title', 0)->save();
  }

  /**
   * Crea un medio sin guardar.
   *
   * @param array<string, mixed> $valores
   *   Valores del medio.
   */
  protected function medio(array $valores): Media {
    return Media::create($valores + [
      'bundle' => 'documento',
      'name' => 'Documento de prueba',
      'status' => 1,
      'field_categoria' => 'informe',
    ]);
  }

  /**
   * Crea el valor de un archivo ya guardado.
   *
   * @return array<string, int|string|null>
   *   Valor para `field_media_file`.
   */
  protected function archivo(string $nombre = 'informe.pdf', int $bytes = 2048): array {
    $file = File::create([
      'uri' => 'public://documentos/' . $nombre,
      'filename' => $nombre,
      'filesize' => $bytes,
      'status' => 1,
    ]);
    $file->save();
    return ['target_id' => $file->id()];
  }

  /**
   * Devuelve los mensajes de las infracciones de un medio.
   *
   * @return array<string, string>
   *   Mensaje por ruta del campo.
   */
  protected function infracciones(Media $medio): array {
    $mensajes = [];
    foreach ($medio->validate() as $infraccion) {
      $mensajes[$infraccion->getPropertyPath()] = (string) $infraccion->getMessage();
    }
    return $mensajes;
  }

  /**
   * Un archivo solo es válido.
   */
  public function testArchivoSolo(): void {
    $medio = $this->medio(['field_media_file' => $this->archivo()]);
    $this->assertSame([], $this->infracciones($medio));
    $medio->save();
    $this->assertNotNull($medio->id());
  }

  /**
   * Un enlace https solo es válido y se guarda sin archivo.
   */
  public function testEnlaceSolo(): void {
    $medio = $this->medio(['field_enlace' => ['uri' => 'https://example.org/documento']]);
    $this->assertSame([], $this->infracciones($medio));
    $medio->save();
    $this->assertNotNull($medio->id());
    $this->assertTrue($medio->get('field_media_file')->isEmpty());
  }

  /**
   * Sin archivo ni enlace no se puede guardar.
   */
  public function testNingunoEsError(): void {
    $mensajes = $this->infracciones($this->medio([]));
    $this->assertSame(['field_media_file' => 'Suba un archivo o escriba un enlace externo.'], $mensajes);
  }

  /**
   * Con archivo y enlace a la vez tampoco.
   */
  public function testAmbosEsError(): void {
    $medio = $this->medio([
      'field_media_file' => $this->archivo(),
      'field_enlace' => ['uri' => 'https://example.org/documento'],
    ]);
    $this->assertSame(
      ['field_enlace' => 'Un documento lleva un archivo o un enlace, no ambos. Deje solo uno.'],
      $this->infracciones($medio),
    );
  }

  /**
   * Un enlace http:// se rechaza: solo https.
   */
  public function testHttpEsError(): void {
    $medio = $this->medio(['field_enlace' => ['uri' => 'http://example.org/documento']]);
    $this->assertSame(['field_enlace' => 'El enlace debe empezar por https://.'], $this->infracciones($medio));
  }

  /**
   * Un enlace no entra a la lista si no es https aunque llegue guardado.
   */
  public function testEnlaceInseguroNoSeLista(): void {
    $medio = $this->medio(['field_enlace' => ['uri' => 'http://example.org/inseguro']]);
    // Se fuerza el guardado saltando la validación.
    $medio->save();
    $datos = $this->documentos()->publicados(new CacheableMetadata());
    $this->assertSame(0, $datos['total']);
  }

  /**
   * Una carpeta de Drive se rotula como carpeta; otro enlace, como enlace.
   */
  public function testCarpetaDeDriveEnlaceRotulados(): void {
    $this->medio([
      'name' => 'Carpeta de evidencias',
      'field_enlace' => ['uri' => 'https://drive.google.com/drive/folders/abc123?usp=sharing'],
      'field_fecha_publicacion' => '2025-03-01',
    ])->save();
    $this->medio([
      'name' => 'Matriz en línea',
      'field_enlace' => ['uri' => 'https://example.org/matriz'],
    ])->save();
    $this->medio(['name' => 'Informe', 'field_media_file' => $this->archivo('informe.pdf', 5434130)])->save();

    $datos = $this->documentos()->publicados(new CacheableMetadata());
    $this->assertSame(3, $datos['total']);
    $items = [];
    foreach ($datos['generales'] as $lista) {
      foreach ($lista['items'] as $item) {
        $items[$item['title']] = $item;
      }
    }
    $carpeta = $items['Carpeta de evidencias'];
    $this->assertTrue($carpeta['externo']);
    $this->assertSame('Carpeta de Drive', $carpeta['rotulo']);
    $this->assertSame('CARP', $carpeta['kind']);
    $this->assertStringStartsWith('Carpeta de Drive', $carpeta['meta']);
    $this->assertStringNotContainsString('KB', $carpeta['meta']);
    $this->assertStringNotContainsString('MB', $carpeta['meta']);
    $this->assertSame('https://drive.google.com/drive/folders/abc123?usp=sharing', $carpeta['url']);

    $enlace = $items['Matriz en línea'];
    $this->assertTrue($enlace['externo']);
    $this->assertSame('Enlace', $enlace['rotulo']);
    $this->assertSame('ENL', $enlace['kind']);
    $this->assertSame('Enlace', $enlace['meta']);

    // Un archivo se lista como siempre.
    $archivo = $items['Informe'];
    $this->assertFalse($archivo['externo']);
    $this->assertSame('PDF', $archivo['kind']);
    $this->assertSame('PDF · 5,2 MB', $archivo['meta']);
  }

  /**
   * El componente abre los enlaces en pestaña nueva y con aviso.
   */
  public function testListaMarcaLosEnlacesExternos(): void {
    $this->container->get('theme_installer')->install(['unillanos']);
    $this->config('system.theme')->set('default', 'unillanos')->save();
    $this->container->get('theme.manager')->setActiveTheme(
      $this->container->get('theme.initialization')->initTheme('unillanos')
    );
    $items = [
      [
        'title' => 'Carpeta de evidencias',
        'meta' => 'Carpeta de Drive',
        'kind' => 'CARP',
        'url' => 'https://drive.google.com/drive/folders/abc123',
        'externo' => TRUE,
      ],
      [
        'title' => 'Informe',
        'meta' => 'PDF · 2 KB',
        'kind' => 'PDF',
        'url' => '/sites/default/files/informe.pdf',
        'externo' => FALSE,
      ],
    ];
    $construccion = [
      '#type' => 'component',
      '#component' => 'unillanos:doc-list',
      '#props' => ['items' => $items],
    ];
    $html = (string) $this->container->get('renderer')->renderInIsolation($construccion);
    $this->assertStringContainsString('target="_blank"', $html);
    $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    $this->assertStringContainsString('se abre en una pestaña nueva', $html);
    $this->assertStringContainsString('Abrir Carpeta de evidencias (Carpeta de Drive)', $html);
    // El archivo conserva el botón de descarga, sin pestaña nueva.
    $this->assertStringContainsString('Descargar Informe (PDF · 2 KB)', $html);
    $this->assertSame(1, substr_count($html, 'target="_blank"'));
  }

  /**
   * El selector «¿Qué va a cargar?» y lo que conserva al guardar.
   */
  public function testSelectorEntityBuilder(): void {
    $medio = $this->medio(['field_enlace' => ['uri' => 'https://example.org/x']]);
    $objeto = $this->container->get('entity_type.manager')->getFormObject('media', 'default');
    $objeto->setEntity($medio);
    $form_state = new FormState();
    $form_state->setFormState(['build_info' => ['callback_object' => $objeto]]);
    $form = ['field_media_file' => ['#type' => 'container'], 'field_enlace' => ['#type' => 'container']];
    (new DocumentoArchivoOEnlaceHooks())->formAlter($form, $form_state);
    $this->assertSame('radios', $form['tipo_carga']['#type']);
    $this->assertSame(['archivo', 'enlace'], array_keys($form['tipo_carga']['#options']));
    // Con un enlace guardado y sin archivo, arranca en «Enlace».
    $this->assertSame('enlace', $form['tipo_carga']['#default_value']);
    // Sin JavaScript no hay #states que apliquen: los dos campos se ven.
    $this->assertArrayHasKey('#states', $form['field_media_file']);
    $this->assertArrayHasKey('#states', $form['field_enlace']);

    // Al guardar con «Enlace» se descarta el archivo, y al revés.
    $mixto = $this->medio([
      'field_media_file' => $this->archivo(),
      'field_enlace' => ['uri' => 'https://example.org/x'],
    ]);
    $estado = new FormState();
    $estado->setValue('tipo_carga', 'enlace');
    DocumentoArchivoOEnlaceHooks::conservarLoElegido('media', $mixto, $form, $estado);
    $this->assertTrue($mixto->get('field_media_file')->isEmpty());
    $this->assertFalse($mixto->get('field_enlace')->isEmpty());
    $this->assertSame([], $this->infracciones($mixto));

    $mixto2 = $this->medio([
      'field_media_file' => $this->archivo(),
      'field_enlace' => ['uri' => 'https://example.org/x'],
    ]);
    $estado->setValue('tipo_carga', 'archivo');
    DocumentoArchivoOEnlaceHooks::conservarLoElegido('media', $mixto2, $form, $estado);
    $this->assertTrue($mixto2->get('field_enlace')->isEmpty());
    $this->assertSame([], $this->infracciones($mixto2));
  }

}
