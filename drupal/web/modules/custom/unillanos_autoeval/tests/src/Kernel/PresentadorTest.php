<?php

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba el presentador de las páginas públicas.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class PresentadorTest extends UnillanosKernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->crearCampo('node', 'proceso', 'field_periodo_evaluado', 'string');
    $this->crearCampo('node', 'proceso', 'field_resultado', 'string');
    $this->setUpCurrentUser([], ['access content']);
    $this->crearCampo('taxonomy_term', 'elemento_modelo', 'field_tipo_elemento', 'string');
    $this->crearCampo('taxonomy_term', 'elemento_modelo', 'field_numero', 'integer');
    $this->crearCampo('node', 'hallazgo', 'field_tipo_hallazgo', 'string');
    $this->crearCampo('node', 'hallazgo', 'body', 'text_long');
    Vocabulary::create(['vid' => 'facultad', 'name' => 'facultad'])->save();
    $this->crearCampo('node', 'programa', 'field_facultad', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->crearCampo('node', 'programa', 'field_sede', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->crearCampo('node', 'programa', 'field_nivel', 'list_string', [
      'allowed_values' => ['pregrado' => 'Pregrado', 'maestria' => 'Maestría'],
    ]);
    $this->crearCampo('node', 'programa', 'field_modalidad', 'list_string', [
      'allowed_values' => ['presencial' => 'Presencial'],
    ]);
    $campos = [
      'director',
      'codigo_snies',
      'resolucion_registro',
      'registro_hasta',
      'resolucion_acreditacion',
      'acreditacion_hasta',
    ];
    foreach ($campos as $campo) {
      $this->crearCampo('node', 'programa', 'field_' . $campo, 'string');
    }
  }

  /**
   * Los números salen con coma decimal y lo no numérico queda vacío.
   */
  public function testNumeroConComaDecimal(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $this->assertSame('4,27', $presentador->numero('4.27'));
    $this->assertSame('4,50', $presentador->numero(4.5));
    $this->assertSame('4,5', $presentador->numero('4.50', 1));
    $this->assertSame('', $presentador->numero(NULL));
    $this->assertSame('', $presentador->numero(''));
    $this->assertSame('', $presentador->numero('abc'));
  }

  /**
   * Busca el programa por clave y su proceso más reciente publicado.
   */
  public function testBuscaProgramaConSuProceso(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $programa = $this->crearNodo('programa', [
      'title' => 'Ingeniería Electrónica',
      'field_clave' => 'ingenieria-electronica',
    ]);
    $otro = $this->crearNodo('programa', [
      'title' => 'Otro',
      'field_clave' => 'otro',
    ]);
    $this->crearNodo('proceso', ['title' => 'Antiguo', 'field_programa' => $programa->id()]);
    $reciente = $this->crearNodo('proceso', ['title' => 'Reciente', 'field_programa' => $programa->id()]);
    $this->crearNodo('proceso', ['title' => 'Borrador', 'field_programa' => $programa->id(), 'status' => 0]);

    $this->assertSame($programa->id(), $presentador->programaPorClave('ingenieria-electronica')->id());
    $this->assertNull($presentador->programaPorClave('no-existe'));
    $this->assertSame($reciente->id(), $presentador->procesoDelPrograma($programa)->id());
    $this->assertNull($presentador->procesoDelPrograma($otro));
  }

  /**
   * El resumen de la portada trae lo guardado y deja vacío lo que falta.
   */
  public function testProcesosPublicados(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $programa = $this->crearNodo('programa', [
      'title' => 'Ingeniería Electrónica',
      'field_clave' => 'ingenieria-electronica',
    ]);
    $this->crearNodo('proceso', [
      'title' => 'Renovación 2018–2022',
      'field_programa' => $programa->id(),
      'field_estado' => 'finalizado',
      'field_periodo_evaluado' => '2018–2022',
    ]);
    $cache = new CacheableMetadata();
    $resumen = $presentador->procesosPublicados($cache);
    $this->assertCount(1, $resumen);
    $this->assertSame('Ingeniería Electrónica', $resumen[0]['programa']);
    $this->assertSame('Finalizado', $resumen[0]['estado']);
    $this->assertSame('2018–2022', $resumen[0]['periodo']);
    $this->assertSame('', $resumen[0]['resultado']);
    $this->assertContains('node:' . $programa->id(), $cache->getCacheTags());
  }

  /**
   * Un programa sin publicar no se nombra ni se enlaza; sin estado, vacío.
   */
  public function testProgramaSinPublicarNoSeMuestra(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $oculto = $this->crearNodo('programa', [
      'title' => 'Oculto',
      'field_clave' => 'oculto',
      'status' => 0,
    ]);
    $this->crearNodo('proceso', [
      'title' => 'Proceso sin estado',
      'field_programa' => $oculto->id(),
    ]);
    $resumen = $presentador->procesosPublicados(new CacheableMetadata());
    $this->assertCount(1, $resumen);
    $this->assertSame('', $resumen[0]['programa']);
    $this->assertSame('', $resumen[0]['url_programa']);
    $this->assertSame('', $resumen[0]['estado']);
    $this->assertSame('Proceso sin estado', $resumen[0]['proceso']);
  }

  /**
   * La ficha del programa solo trae los datos que existen.
   */
  public function testDatosProgramaOmiteLoVacio(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $facultad = Term::create(['vid' => 'facultad', 'name' => 'Facultad de Ciencias']);
    $facultad->save();
    $sede = Term::create(['vid' => 'sede', 'name' => 'Sede Barcelona']);
    $sede->save();
    $programa = $this->crearNodo('programa', [
      'title' => 'Ingeniería Electrónica',
      'field_clave' => 'ingenieria-electronica',
      'field_facultad' => $facultad->id(),
      'field_sede' => $sede->id(),
      'field_nivel' => 'pregrado',
      'field_modalidad' => 'presencial',
      'field_director' => 'Persona Directora',
      'field_registro_hasta' => '2029-12-31',
    ]);
    $datos = $presentador->datosPrograma($programa);
    $this->assertSame('Ingeniería', $datos['nombre_inicio']);
    $this->assertSame('Electrónica', $datos['nombre_acento']);
    $this->assertSame('Facultad de Ciencias · Sede Barcelona', $datos['eyebrow']);
    $this->assertSame(['Pregrado', 'Presencial'], $datos['etiquetas']);
    $terminos = array_column($datos['ficha'], 'term');
    $this->assertSame([
      'Nivel',
      'Modalidad',
      'Sede',
      'Dirección del programa',
      'Registro vigente hasta',
    ], $terminos);
    $this->assertSame('diciembre de 2029', $datos['ficha'][4]['value']);

    // Una referencia rota (término borrado) se trata como dato vacío.
    $roto = $this->crearNodo('programa', [
      'title' => 'Doctorado en Ciencias del Mar y de la Costa',
      'field_clave' => 'd',
      'field_facultad' => 99999,
    ]);
    $datos = $presentador->datosPrograma($roto);
    $this->assertSame('', $datos['eyebrow']);
    // Más de cuatro palabras: solo las dos últimas van en acento.
    $this->assertSame('Doctorado en Ciencias del Mar y de', $datos['nombre_inicio']);
    $this->assertSame('la Costa', $datos['nombre_acento']);

    $vacio = $this->crearNodo('programa', ['title' => 'Maestría', 'field_clave' => 'm']);
    $datos = $presentador->datosPrograma($vacio);
    $this->assertSame('Maestría', $datos['nombre_inicio']);
    $this->assertSame('', $datos['nombre_acento']);
    $this->assertSame('', $datos['eyebrow']);
    $this->assertSame([], $datos['etiquetas']);
    $this->assertSame([], $datos['ficha']);
  }

  /**
   * Las fechas se escriben como mes y año, y lo inválido queda vacío.
   */
  public function testFechaMesAnio(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $this->assertSame('enero de 2030', $presentador->fechaMesAnio('2030-01-15'));
    $this->assertSame('diciembre de 2029', $presentador->fechaMesAnio('2029-12-31T00:00:00'));
    $this->assertSame('', $presentador->fechaMesAnio(NULL));
    $this->assertSame('', $presentador->fechaMesAnio(''));
    $this->assertSame('', $presentador->fechaMesAnio('2029-13-01'));
    $this->assertSame('', $presentador->fechaMesAnio('texto'));
  }

  /**
   * Crea un elemento del modelo con su tipo y número.
   */
  protected function elemento(string $nombre, int $numero, int $lineamiento, string $tipo = 'factor', array $padres = []): Term {
    $termino = Term::create([
      'vid' => 'elemento_modelo',
      'name' => $nombre,
      'field_clave' => $nombre,
      'field_tipo_elemento' => $tipo,
      'field_numero' => $numero,
      'field_lineamiento' => $lineamiento,
      'parent' => $padres,
    ]);
    $termino->save();
    return $termino;
  }

  /**
   * Crea un hallazgo de un proceso.
   */
  protected function hallazgo(int $proceso, string $tipo, string $texto, ?int $elemento = NULL, int $publicado = 1, string $formato = 'plain_text'): void {
    $this->crearNodo('hallazgo', [
      'field_proceso' => $proceso,
      'field_tipo_hallazgo' => $tipo,
      'field_elemento' => $elemento,
      'body' => ['value' => $texto, 'format' => $formato],
      'status' => $publicado,
    ]);
  }

  /**
   * Los factores salen del modelo del proceso, con sus datos guardados.
   */
  public function testFactoresDelPrograma(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $this->cargarGrados();
    $modelo = $this->crearLineamiento('Modelo A');
    $otro_modelo = $this->crearLineamiento('Modelo B');
    $f2 = $this->elemento('Estudiantes', 2, $modelo->id());
    $f1 = $this->elemento('Proyecto educativo', 1, $modelo->id());
    $f3 = $this->elemento('Profesores', 3, $modelo->id());
    $this->elemento('Factor de otro modelo', 1, $otro_modelo->id());
    $programa = $this->crearNodo('programa', ['title' => 'Programa', 'field_clave' => 'p']);
    $proceso = $this->crearNodo('proceso', [
      'title' => 'Proceso 2018–2022',
      'field_programa' => $programa->id(),
      'field_lineamiento' => $modelo->id(),
      'field_resultado' => 'Cumplimiento en alto grado (4,508 · 90 %)',
    ]);
    $pleno = $this->container->get('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties(['name' => 'Pleno']);
    $alto = $this->container->get('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties(['name' => 'Alto']);
    $this->crearNodo('valoracion', [
      'field_proceso' => $proceso->id(),
      'field_elemento' => $f1->id(),
      'field_valoracion' => '4.90',
      'field_grado' => reset($pleno)->id(),
    ]);
    $this->crearNodo('valoracion', [
      'field_proceso' => $proceso->id(),
      'field_elemento' => $f2->id(),
      'field_valoracion' => '4.27',
      'field_grado' => reset($alto)->id(),
    ]);
    $this->hallazgo((int) $proceso->id(), 'fortaleza', 'Fortaleza uno', (int) $f1->id());
    $this->hallazgo((int) $proceso->id(), 'aspecto_por_mejorar', '<p>Mejorar &amp; sostener</p>', (int) $f1->id(), 1, 'basic_html');
    $this->hallazgo((int) $proceso->id(), 'aspecto_por_mejorar', 'Puntaje < 4 y > 3', (int) $f1->id());
    $this->hallazgo((int) $proceso->id(), 'aspecto_por_mejorar', 'Aspecto del factor dos', (int) $f2->id());
    $this->hallazgo((int) $proceso->id(), 'aspecto_por_mejorar', 'Aspecto sin factor');
    $this->hallazgo((int) $proceso->id(), 'fortaleza', 'Sin publicar', (int) $f1->id(), 0);
    $this->hallazgo((int) $proceso->id(), 'fortaleza', '   ', (int) $f1->id());

    $cache = new CacheableMetadata();
    $datos = $presentador->factoresDelPrograma($programa, $cache);
    $this->assertSame('Proceso 2018–2022', $datos['proceso']);
    $this->assertSame('Cumplimiento en alto grado (4,508 · 90 %)', $datos['resultado']);
    // El factor 3 no tiene valoración ni hallazgos: no aparece.
    $this->assertSame(['f01', 'f02', 'sin-factor'], array_column($datos['pestanas'], 'key'));
    $this->assertSame(['f01', 'f02'], array_column($datos['tarjetas'], 'goto'));
    $this->assertSame(4.9, $datos['tarjetas'][0]['score']);
    $this->assertSame('Pleno', $datos['tarjetas'][0]['grade']);
    $this->assertSame('01', $datos['tarjetas'][0]['number']);
    $this->assertSame('Alto', $datos['tarjetas'][1]['grade']);
    $this->assertSame(['Fortaleza uno'], $datos['pestanas'][0]['strengths']);
    // HTML de otro formato: sin etiquetas; texto plano: tal cual.
    $this->assertSame(['Mejorar & sostener', 'Puntaje < 4 y > 3'], $datos['pestanas'][0]['improvements']);
    $this->assertSame('Factor 01', $datos['pestanas'][0]['label']);
    $this->assertSame(['Aspecto del factor dos'], $datos['pestanas'][1]['improvements']);
    $this->assertSame([], $datos['pestanas'][1]['strengths']);
    $this->assertSame('Sin factor asignado', $datos['pestanas'][2]['name']);
    $this->assertSame(['Aspecto sin factor'], $datos['pestanas'][2]['improvements']);
    $this->assertContains('node_list:hallazgo', $cache->getCacheTags());
    $this->assertNotContains('node:' . $f3->id(), $cache->getCacheTags());
  }

  /**
   * Un hallazgo sobre una característica o un aspecto va a su factor.
   */
  public function testHallazgoDeCaracteristicaVaAlFactor(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $modelo = $this->crearLineamiento('Modelo 2025');
    $factor = $this->elemento('Factor', 4, $modelo->id());
    $caracteristica = $this->elemento('Característica', 10, $modelo->id(), 'caracteristica', [$factor->id()]);
    $aspecto = $this->elemento('Aspecto', 11, $modelo->id(), 'aspecto', [$caracteristica->id()]);
    $suelto = $this->elemento('Característica suelta', 12, $modelo->id(), 'caracteristica');
    $programa = $this->crearNodo('programa', ['title' => 'Programa', 'field_clave' => 'p']);
    $proceso = $this->crearNodo('proceso', [
      'field_programa' => $programa->id(),
      'field_lineamiento' => $modelo->id(),
    ]);
    $this->hallazgo((int) $proceso->id(), 'fortaleza', 'Por aspecto', (int) $aspecto->id());
    $this->hallazgo((int) $proceso->id(), 'fortaleza', 'Por característica', (int) $caracteristica->id());
    $this->hallazgo((int) $proceso->id(), 'aspecto_por_mejorar', 'Sin padre', (int) $suelto->id());
    $datos = $presentador->factoresDelPrograma($programa, new CacheableMetadata());
    $this->assertSame(['f04', 'sin-factor'], array_column($datos['pestanas'], 'key'));
    $this->assertSame(['Por aspecto', 'Por característica'], $datos['pestanas'][0]['strengths']);
    $this->assertSame(['Sin padre'], $datos['pestanas'][1]['improvements']);
    // Sin valoración no hay tarjeta ni medidor.
    $this->assertSame([], $datos['tarjetas']);
    $this->assertArrayNotHasKey('score', $datos['pestanas'][0]);
  }

  /**
   * Sin proceso, sin modelo o sin factores no se devuelve nada.
   */
  public function testFactoresVaciosSinProcesoNiModelo(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $programa = $this->crearNodo('programa', ['title' => 'Programa', 'field_clave' => 'p']);
    $this->assertSame([], $presentador->factoresDelPrograma($programa, new CacheableMetadata()));
    $this->crearNodo('proceso', ['field_programa' => $programa->id()]);
    $this->assertSame([], $presentador->factoresDelPrograma($programa, new CacheableMetadata()));
    $modelo = $this->crearLineamiento('Modelo sin factores');
    $otro = $this->crearNodo('programa', ['title' => 'Otro', 'field_clave' => 'o']);
    $this->crearNodo('proceso', ['field_programa' => $otro->id(), 'field_lineamiento' => $modelo->id()]);
    $this->assertSame([], $presentador->factoresDelPrograma($otro, new CacheableMetadata()));
  }

  /**
   * Se usa el proceso más reciente que tenga factores y datos.
   */
  public function testUsaElProcesoMasRecienteConFactores(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $acreditacion = $this->crearLineamiento('Acreditación');
    $registro = $this->crearLineamiento('Registro calificado', 'registro_calificado');
    $factor = $this->elemento('Estudiantes', 2, $acreditacion->id());
    $this->elemento('Condición', 1, $registro->id(), 'condicion');
    $programa = $this->crearNodo('programa', ['title' => 'Programa', 'field_clave' => 'p']);
    $antiguo = $this->crearNodo('proceso', [
      'title' => 'Acreditación 2018',
      'field_programa' => $programa->id(),
      'field_lineamiento' => $acreditacion->id(),
    ]);
    $this->hallazgo((int) $antiguo->id(), 'fortaleza', 'Fortaleza antigua', (int) $factor->id());
    // Un proceso más reciente sin datos todavía y otro de registro calificado.
    $this->crearNodo('proceso', [
      'title' => 'Acreditación 2024 sin datos',
      'field_programa' => $programa->id(),
      'field_lineamiento' => $acreditacion->id(),
    ]);
    $this->crearNodo('proceso', [
      'title' => 'Registro 2025',
      'field_programa' => $programa->id(),
      'field_lineamiento' => $registro->id(),
    ]);
    $datos = $presentador->factoresDelPrograma($programa, new CacheableMetadata());
    $this->assertSame('Acreditación 2018', $datos['proceso']);
    $this->assertSame(['Fortaleza antigua'], $datos['pestanas'][0]['strengths']);
  }

  /**
   * Las claves de pestaña son únicas aunque falten o se repitan los números.
   */
  public function testClavesDePestanaUnicas(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $modelo = $this->crearLineamiento('Modelo');
    $a = $this->elemento('Factor A', 1, $modelo->id());
    $b = $this->elemento('Factor B repetido', 1, $modelo->id());
    $c = Term::create([
      'vid' => 'elemento_modelo',
      'name' => 'Factor sin número',
      'field_clave' => 'sin-numero',
      'field_tipo_elemento' => 'factor',
      'field_lineamiento' => $modelo->id(),
    ]);
    $c->save();
    $programa = $this->crearNodo('programa', ['title' => 'Programa', 'field_clave' => 'p']);
    $proceso = $this->crearNodo('proceso', [
      'field_programa' => $programa->id(),
      'field_lineamiento' => $modelo->id(),
    ]);
    foreach ([$a, $b, $c] as $factor) {
      $this->hallazgo((int) $proceso->id(), 'fortaleza', 'Fortaleza ' . $factor->label(), (int) $factor->id());
    }
    $datos = $presentador->factoresDelPrograma($programa, new CacheableMetadata());
    $claves = array_column($datos['pestanas'], 'key');
    $this->assertCount(3, $claves);
    $this->assertSame($claves, array_values(array_unique($claves)));
    $this->assertSame('f01', $claves[0]);
    $this->assertSame('', $datos['pestanas'][2]['number']);
    $this->assertSame('', $datos['pestanas'][2]['label']);
  }

}
