<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\taxonomy\Entity\Term;
use Drupal\unillanos_autoeval\Service\InstitucionalCifras;
use Drupal\unillanos_autoeval\Service\InstitucionalDependencias;
use Drupal\unillanos_autoeval\Service\InstitucionalDirectivos;
use Drupal\unillanos_autoeval\Service\InstitucionalNormas;
use Drupal\unillanos_autoeval\Service\InstitucionalPortada;
use Drupal\unillanos_autoeval\Service\InstitucionalProcesos;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba los servicios de las páginas de «Institucional» (Fase 9, etapa 3).
 *
 * Solo trae lo guardado: lo vacío se omite, un documento despublicado no sale
 * y cada página funciona para cualquier dato, no solo los de Acreditación.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class InstitucionalServiciosTest extends InstitucionalBaseKernel {

  /**
   * Metadatos de caché para cada llamada.
   */
  protected function cache(): CacheableMetadata {
    return new CacheableMetadata();
  }

  /**
   * La portada trae cinco tarjetas con ruta propia y la frase configurada.
   */
  public function testPortada(): void {
    $this->config('unillanos_autoeval.micrositio')->set('institucional', ['introduccion' => 'Frase de prueba.'])->save();
    $datos = $this->container->get(InstitucionalPortada::class)->datos($this->cache());
    $this->assertSame('Frase de prueba.', $datos['intro']);
    $this->assertSame(
      ['Procesos institucionales', 'Dependencias', 'Directivos', 'Normatividad', 'Cifras'],
      array_column($datos['tarjetas'], 'title'),
    );
    $this->assertSame(
      [
        '/institucional/procesos',
        '/institucional/dependencias',
        '/institucional/directivos',
        '/institucional/normatividad',
        '/institucional/cifras',
      ],
      array_column($datos['tarjetas'], 'url'),
    );
  }

  /**
   * La portada sin frase configurada no inventa texto.
   */
  public function testPortadaSinFrase(): void {
    $this->config('unillanos_autoeval.micrositio')->set('institucional', [])->save();
    $this->assertSame('', $this->container->get(InstitucionalPortada::class)->datos($this->cache())['intro']);
  }

  /**
   * Las dependencias salen en su orden, con los formatos por nivel.
   */
  public function testDependenciasPorNivelPrograma(): void {
    $segunda = $this->crearNodo('dependencia', ['title' => 'Biblioteca', 'field_orden' => 2]);
    $primera = $this->crearNodo('dependencia', ['title' => 'Admisiones', 'field_orden' => 1]);
    $this->crearNodo('dependencia', ['title' => 'Sin formatos', 'field_orden' => 3]);
    $this->crearNodo('dependencia', ['title' => 'Oculta', 'field_orden' => 0, 'status' => 0]);
    $pre = $this->crearNodo('programa', ['title' => 'Mercadeo', 'field_clave' => 'm', 'field_nivel' => 'pregrado']);
    $mae = $this->crearNodo('programa', ['title' => 'Maestría X', 'field_clave' => 'x', 'field_nivel' => 'maestria']);
    $this->enlace('Mercadeo', ['field_dependencia' => $primera->id(), 'field_programa' => $pre->id()]);
    $this->enlace('Msc. en X', ['field_dependencia' => $primera->id(), 'field_programa' => $mae->id()]);
    $this->enlace('Sin programa', ['field_dependencia' => $primera->id()]);
    $this->enlace('Formato de biblioteca', ['field_dependencia' => $segunda->id(), 'field_programa' => $pre->id()]);
    $this->enlace('Despublicado', ['field_dependencia' => $primera->id(), 'status' => 0]);

    $datos = $this->container->get(InstitucionalDependencias::class)->datos($this->cache());
    $this->assertFalse($datos['vacio']);
    // Sin formatos y despublicada: no aparecen (y sin edición no hay nada).
    $this->assertSame(['Admisiones', 'Biblioteca'], array_column($datos['dependencias'], 'titulo'));
    $this->assertSame(4, $datos['total']);
    $admisiones = $datos['dependencias'][0];
    $this->assertSame(3, $admisiones['total']);
    $this->assertSame(['Pregrados', 'Maestrías', 'Otros'], array_column($admisiones['grupos'], 'titulo'));
    $this->assertSame('Mercadeo', $admisiones['grupos'][0]['items'][0]['title']);
    $this->assertSame('Carpeta de Drive', $admisiones['grupos'][0]['items'][0]['rotulo']);
    $this->assertTrue($admisiones['grupos'][0]['items'][0]['externo']);
    $this->assertSame('', $admisiones['grupos'][0]['items'][0]['cat']);
    // Los identificadores sirven de id de HTML.
    $this->assertMatchesRegularExpression('/^dependencia-\d+$/', $admisiones['id']);
  }

  /**
   * Sin dependencias, la página queda vacía y lo dice.
   */
  public function testDependenciasVacio(): void {
    $datos = $this->container->get(InstitucionalDependencias::class)->datos($this->cache());
    $this->assertTrue($datos['vacio']);
    $this->assertSame([], $datos['dependencias']);
  }

  /**
   * Directivos: un documento va directo; varios, a una lista de la facultad.
   */
  public function testDirectivos(): void {
    $una = $this->facultad('Facultad Una', 'FU');
    $varias = $this->facultad('Facultad Varias', 'FV');
    $this->facultad('Facultad Sin Nada', 'FS');
    $this->enlace('Procesos FU', ['field_facultad' => $una->id(), 'field_categoria' => 'directivos'], 'https://drive.google.com/drive/folders/una');
    $this->enlace('Procesos FV 1', ['field_facultad' => $varias->id(), 'field_categoria' => 'directivos']);
    $this->enlace('Procesos FV 2', ['field_facultad' => $varias->id(), 'field_categoria' => 'directivos']);

    $datos = $this->container->get(InstitucionalDirectivos::class)->datos($this->cache());
    $this->assertSame(['Facultad Una', 'Facultad Varias'], array_column($datos['tarjetas'], 'title'));
    $this->assertSame('https://drive.google.com/drive/folders/una', $datos['tarjetas'][0]['url']);
    $this->assertTrue($datos['tarjetas'][0]['external']);
    $this->assertSame('#facultad-' . $varias->id(), $datos['tarjetas'][1]['url']);
    $this->assertCount(1, $datos['listas']);
    $this->assertSame(['Procesos FV 1', 'Procesos FV 2'], array_column($datos['listas'][0]['items'], 'title'));
  }

  /**
   * Normatividad: el orden de carga, las siete columnas y los conteos.
   */
  public function testNormatividad(): void {
    $base = ['field_numero_fecha' => '30 de 1992', 'field_entidad' => 'Congreso', 'field_capitulos' => 'Toda'];
    $this->crearNodo('norma', [
      'title' => 'Ley 30 de 1992',
      'field_origen_norma' => 'externo',
      'field_tipo_norma' => 'Ley',
      'field_epigrafe' => 'Por la cual…',
      'field_aplicacion' => 'Aplica',
    ] + $base);
    $this->crearNodo('norma', [
      'title' => 'Acuerdo Superior 1',
      'field_origen_norma' => 'interno',
      'field_tipo_norma' => 'Acuerdo Superior',
      'field_epigrafe' => 'Otro',
    ] + $base);
    $this->crearNodo('norma', [
      'title' => 'Ley 100',
      'field_origen_norma' => 'externo',
      'field_tipo_norma' => 'Ley',
      'field_epigrafe' => 'Tres',
    ] + $base);
    $this->crearNodo('norma', [
      'title' => 'Oculta',
      'field_origen_norma' => 'externo',
      'field_tipo_norma' => 'Ley',
      'field_epigrafe' => 'x',
      'status' => 0,
    ]);

    $datos = $this->container->get(InstitucionalNormas::class)->datos($this->cache());
    $this->assertSame(3, $datos['total']);
    $this->assertSame(['Ley', 'Acuerdo Superior', 'Ley'], array_column($datos['filas'], 'tipo'));
    $this->assertSame(['Externo', 'Interno', 'Externo'], array_column($datos['filas'], 'origen'));
    $this->assertSame(['externo' => 2, 'interno' => 1], array_column($datos['origenes'], 'conteo', 'valor'));
    $this->assertSame(['ley' => 2, 'acuerdo-superior' => 1], array_column($datos['tipos'], 'conteo', 'valor'));
    $this->assertStringContainsString('congreso', $datos['filas'][0]['buscar']);
    $this->assertNull($datos['filas'][0]['link']);
  }

  /**
   * Procesos: el más reciente con su documento, y las condiciones por sede.
   */
  public function testProcesosCondiciones(): void {
    $villavicencio = Term::create(['vid' => 'sede', 'name' => 'Sede Villavicencio']);
    $villavicencio->save();
    $granada = Term::create(['vid' => 'sede', 'name' => 'Sede Granada']);
    $granada->save();
    $this->crearNodo('proceso', [
      'title' => 'Acreditación 2010',
      'field_sede' => $villavicencio->id(),
      'field_tipo_proceso' => 'acreditacion',
    ]);
    $reciente = $this->crearNodo('proceso', [
      'title' => 'Acreditación 2015-2019',
      'field_sede' => $villavicencio->id(),
      'field_tipo_proceso' => 'acreditacion',
      'field_periodo_evaluado' => '2015-2019',
    ]);
    $cond = $this->crearNodo('proceso', [
      'title' => 'Condiciones Granada',
      'field_sede' => $granada->id(),
      'field_tipo_proceso' => 'registro_calificado',
    ]);
    $this->crearNodo('proceso', [
      'title' => 'Borrador',
      'field_sede' => $granada->id(),
      'field_tipo_proceso' => 'registro_calificado',
      'status' => 0,
    ]);
    $programa = $this->crearNodo('programa', ['title' => 'P', 'field_clave' => 'p']);
    $de_programa = $this->crearNodo('proceso', [
      'title' => 'De programa',
      'field_programa' => $programa->id(),
      'field_tipo_proceso' => 'acreditacion',
    ]);
    $this->enlace('Informe 2015-2019', ['field_proceso' => $reciente->id(), 'field_categoria' => 'informe']);
    $this->enlace('Soporte', ['field_proceso' => $reciente->id(), 'field_categoria' => 'soporte']);
    $this->enlace('Documento de Granada', ['field_proceso' => $cond->id(), 'field_categoria' => 'informe']);
    $this->enlace('Del programa', ['field_proceso' => $de_programa->id()]);

    $datos = $this->container->get(InstitucionalProcesos::class)->datos($this->cache());
    $this->assertFalse($datos['vacio']);
    $this->assertSame('Acreditación 2015-2019', $datos['principal']['titulo']);
    $this->assertSame('2015-2019', $datos['principal']['periodo']);
    $this->assertSame('Sede Villavicencio', $datos['principal']['sede']);
    // Informe antes que soporte; ninguno de otro proceso ni de un programa.
    // El guion de un rango de años lleva una unión invisible (no se parte).
    $titulos = array_map(fn(string $t): string => str_replace("\u{2060}", '', $t), array_column($datos['principal']['documentos'], 'title'));
    $this->assertSame(['Informe 2015-2019', 'Soporte'], $titulos);
    $this->assertCount(1, $datos['condiciones']);
    $this->assertSame('Sede Granada', $datos['condiciones'][0]['titulo']);
    $this->assertSame(['Documento de Granada'], array_column($datos['condiciones'][0]['procesos'][0]['items'], 'title'));
  }

  /**
   * Sin procesos institucionales, la página queda vacía.
   */
  public function testProcesosVacio(): void {
    $datos = $this->container->get(InstitucionalProcesos::class)->datos($this->cache());
    $this->assertTrue($datos['vacio']);
    $this->assertNull($datos['principal']);
  }

  /**
   * Cifras: solo enlaces https y un tablero con sus parámetros de incrustación.
   */
  public function testCifras(): void {
    $this->config('unillanos_autoeval.micrositio')->set('institucional', [
      'cifras' => [
        'enlaces' => [
          ['titulo' => 'Bueno', 'texto' => 'Texto', 'url' => 'https://example.org/a'],
          ['titulo' => 'Inseguro', 'texto' => '', 'url' => 'http://example.org/b'],
          ['titulo' => 'Javascript', 'texto' => '', 'url' => 'javascript:alert(1)'],
          ['titulo' => '', 'texto' => '', 'url' => 'https://example.org/c'],
        ],
        'tablero_url' => 'https://public.tableau.com/views/Tablero/Hoja1',
      ],
    ])->save();
    $datos = $this->container->get(InstitucionalCifras::class)->datos($this->cache());
    $this->assertSame(['Bueno'], array_column($datos['enlaces'], 'title'));
    $this->assertTrue($datos['enlaces'][0]['external']);
    $this->assertSame('https://public.tableau.com/views/Tablero/Hoja1', $datos['tablero']['url']);
    $this->assertSame('https://public.tableau.com/views/Tablero/Hoja1?:embed=y&:showVizHome=no&:toolbar=no', $datos['tablero']['embed_url']);
    $this->assertSame('public.tableau.com', $datos['tablero']['host']);
  }

  /**
   * Un tablero que no es https no se ofrece; sin nada, la página queda vacía.
   */
  public function testCifrasSinTableroSeguro(): void {
    $this->config('unillanos_autoeval.micrositio')->set('institucional', [
      'cifras' => ['enlaces' => [], 'tablero_url' => 'http://example.org/t'],
    ])->save();
    $datos = $this->container->get(InstitucionalCifras::class)->datos($this->cache());
    $this->assertNull($datos['tablero']);
    $this->assertTrue($datos['vacio']);
  }

}
