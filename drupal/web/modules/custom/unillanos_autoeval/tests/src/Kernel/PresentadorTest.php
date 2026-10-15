<?php

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\unillanos_autoeval\Service\PresentadorAutoevaluacion;
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
    'entity_reference_revisions',
    'paragraphs',
    'unillanos_autoeval',
  ];

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
    $this->prepararPlan();
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

  /**
   * Crea los campos y paragraphs del plan de mejoramiento.
   */
  protected function prepararPlan(): void {
    $this->installEntitySchema('paragraph');
    foreach (['proyecto_institucional', 'responsable'] as $vid) {
      Vocabulary::create(['vid' => $vid, 'name' => $vid])->save();
    }
    foreach (['indicador', 'programacion_anual'] as $tipo) {
      ParagraphsType::create(['id' => $tipo, 'label' => $tipo])->save();
    }
    $paragraph = ['target_type' => 'paragraph'];
    foreach (['nombre', 'unidad', 'linea_base'] as $campo) {
      $this->crearCampo('paragraph', 'indicador', 'field_' . $campo, 'string');
    }
    $this->crearCampo('paragraph', 'indicador', 'field_programacion', 'entity_reference_revisions', $paragraph, -1);
    $this->crearCampo('paragraph', 'programacion_anual', 'field_anio', 'integer');
    $decimal = ['precision' => 12, 'scale' => 4];
    $this->crearCampo('paragraph', 'programacion_anual', 'field_programado', 'decimal', $decimal);
    $this->crearCampo('paragraph', 'programacion_anual', 'field_logrado', 'decimal', $decimal);

    $this->crearCampo('node', 'meta', 'body', 'text_long');
    $this->crearCampo('node', 'meta', 'field_codigo', 'string');
    $this->crearCampo('node', 'meta', 'field_numero', 'integer');
    $this->crearCampo('node', 'meta', 'field_actividades', 'text_long');
    $this->crearCampo('node', 'meta', 'field_recursos', 'string');
    $this->crearCampo('node', 'meta', 'field_tipo_meta', 'string');
    $this->crearCampo('node', 'meta', 'field_proyecto', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->crearCampo('node', 'meta', 'field_responsables', 'entity_reference', ['target_type' => 'taxonomy_term'], -1);
    $this->crearCampo('node', 'meta', 'field_indicadores', 'entity_reference_revisions', $paragraph, -1);
    foreach (['codigo_formato', 'version_formato', 'periodo', 'fecha_suscripcion'] as $campo) {
      $this->crearCampo('node', 'plan_mejoramiento', 'field_' . $campo, 'string');
    }
    $this->crearCampo('node', 'seguimiento', 'field_actividades', 'text_long');
    $this->crearCampo('node', 'seguimiento', 'field_medios_verificacion', 'string');
  }

  /**
   * Crea un programa con modelo, dos factores, un proceso y su plan.
   *
   * @return array<string, mixed>
   *   Con programa, proceso, plan, f1 y f2.
   */
  protected function programaConPlan(): array {
    $modelo = $this->crearLineamiento('Modelo');
    $f1 = $this->elemento('Estudiantes', 1, $modelo->id());
    $f2 = $this->elemento('Profesores', 2, $modelo->id());
    $programa = $this->crearNodo('programa', ['title' => 'Programa', 'field_clave' => 'p']);
    $proceso = $this->crearNodo('proceso', [
      'field_programa' => $programa->id(),
      'field_lineamiento' => $modelo->id(),
    ]);
    $plan = $this->crearNodo('plan_mejoramiento', [
      'field_proceso' => $proceso->id(),
      'field_codigo_formato' => 'FO-GCL-20',
      'field_version_formato' => '03',
      'field_periodo' => '2024-2 – 2030',
      'field_fecha_suscripcion' => '2024-06-19',
    ]);
    return ['programa' => $programa, 'proceso' => $proceso, 'plan' => $plan, 'f1' => $f1, 'f2' => $f2];
  }

  /**
   * Crea una meta de un plan con los hallazgos indicados.
   */
  protected function meta(Node $plan, int $numero, string $codigo, array $hallazgos = [], array $extra = []): Node {
    return $this->crearNodo('meta', $extra + [
      'title' => 'Meta',
      'field_plan' => $plan->id(),
      'field_numero' => $numero,
      'field_codigo' => $codigo,
      'body' => ['value' => 'Acción ' . $codigo, 'format' => 'plain_text'],
      'field_hallazgos' => $hallazgos,
    ]);
  }

  /**
   * Crea un seguimiento de una meta.
   */
  protected function seguimiento(Node $meta, string $periodo, ?string $avance, string $estado = 'reportado'): void {
    $this->crearNodo('seguimiento', [
      'field_meta' => $meta->id(),
      'field_periodo' => $periodo,
      'field_avance' => $avance,
      'field_estado' => $estado,
      'field_actividades' => ['value' => 'Hecho en ' . $periodo, 'format' => 'plain_text'],
      'field_medios_verificacion' => 'Evidencia ' . $periodo,
    ]);
  }

  /**
   * Arma el plan: filas por meta, factores de cada una y enlace desde ellos.
   */
  public function testPlanArmaFilasConEnlace(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $d = $this->programaConPlan();
    $id = (int) $d['proceso']->id();
    $this->hallazgo($id, 'aspecto_por_mejorar', 'Situación uno', (int) $d['f1']->id());
    $this->hallazgo($id, 'aspecto_por_mejorar', 'Situación dos', (int) $d['f2']->id());
    $this->hallazgo($id, 'fortaleza', 'Fortaleza', (int) $d['f1']->id());
    $h = $this->container->get('entity_type.manager')->getStorage('node')
      ->loadByProperties(['type' => 'hallazgo']);
    $h = array_values($h);
    usort($h, fn($a, $b) => (int) $a->id() <=> (int) $b->id());

    // Una meta con hallazgos de dos factores, otra de uno y otra sin hallazgos.
    $paragraphs = $this->container->get('entity_type.manager')->getStorage('paragraph');
    $tipo = ['type' => 'programacion_anual'];
    $anio_a = $paragraphs->create($tipo + ['field_anio' => 2024, 'field_programado' => '165.0000']);
    $anio_b = $paragraphs->create($tipo + [
      'field_anio' => 2025,
      'field_programado' => '0.6000',
      'field_logrado' => '0.5000',
    ]);
    $anio_c = $paragraphs->create($tipo + ['field_anio' => 2026]);
    foreach ([$anio_a, $anio_b, $anio_c] as $anio) {
      $anio->save();
    }
    $indicador = $paragraphs->create([
      'type' => 'indicador',
      'field_nombre' => 'Indicador uno',
      'field_linea_base' => '153 grupo',
      'field_programacion' => [$anio_a, $anio_b, $anio_c],
    ]);
    $indicador->save();
    $m1 = $this->meta($d['plan'], 1, '1', [$h[0]->id(), $h[1]->id()], [
      'field_indicadores' => [$indicador],
      'field_actividades' => ['value' => "1. Uno\n2. Dos", 'format' => 'plain_text'],
      'field_recursos' => 'Funcionamiento',
    ]);
    $m2 = $this->meta($d['plan'], 2, '2a.2b', [$h[2]->id()]);
    $this->meta($d['plan'], 3, '3');
    // Seguimientos: el último periodo de la meta 1 no trae porcentaje.
    $this->seguimiento($m1, '2024-2', '0.80');
    $this->seguimiento($m1, '2025-2', NULL);
    $this->seguimiento($m2, '2025-1', '0.50');

    $datos = $presentador->factoresDelPrograma($d['programa'], new CacheableMetadata());
    $cache = new CacheableMetadata();
    $resultado = $presentador->planDelPrograma($d['programa'], $datos, $cache);
    $plan = $resultado['plan'];
    $this->assertCount(3, $plan['filas']);
    $this->assertSame('Formato FO-GCL-20 · versión 03 · periodo 2024-2 – 2030 · suscrito en junio de 2024', $plan['lead']);

    // Meta con varios factores: aparece en ambos; sin hallazgos, sin factor.
    $this->assertSame(['f01', 'f02'], $plan['filas'][0]['keys']);
    $this->assertSame('F01, F02', $plan['filas'][0]['tag']);
    $this->assertSame(['f01'], $plan['filas'][1]['keys']);
    $this->assertSame(['sin-factor'], $plan['filas'][2]['keys']);
    $this->assertSame('', $plan['filas'][2]['tag']);
    $this->assertSame(['f01', 'f02', 'sin-factor'], array_column($plan['opciones'], 'value'));
    $this->assertSame('F01 · Estudiantes', $plan['opciones'][0]['label']);

    // Celdas: situación (lista si son varias), acción con su meta, avance.
    $this->assertSame(['type' => 'list', 'items' => ['Situación uno', 'Situación dos']], $plan['filas'][0]['cells'][0]);
    $this->assertSame(['type' => 'text', 'value' => 'Fortaleza'], $plan['filas'][1]['cells'][0]);
    $this->assertSame('Acción 1', $plan['filas'][0]['cells'][1]['value']);
    $this->assertSame('Meta 1', $plan['filas'][0]['cells'][1]['eyebrow']);
    $this->assertSame('Meta 2a.2b', $plan['filas'][1]['cells'][1]['eyebrow']);

    // Avance: siempre el periodo más reciente, sin tomar el % de uno anterior.
    $this->assertSame("2025-2\nReportado", $plan['filas'][0]['cells'][3]['value']);
    $this->assertSame("2025-1 · 50 %\nReportado", $plan['filas'][1]['cells'][3]['value']);
    $this->assertSame('', $plan['filas'][2]['cells'][3]['value']);

    // Detalle: actividades numeradas, indicadores y periodos (con su %).
    $grupos = array_column($plan['filas'][0]['cells'][1]['details']['groups'], NULL, 'label');
    $this->assertSame("1. Uno\n2. Dos", $grupos['Actividades']['text']);
    $this->assertSame('Funcionamiento', $grupos['Recursos']['text']);
    $this->assertSame(
      ['Indicador uno · Línea base: 153 grupo · Programación anual · 2024: 165 · 2025: 0,6 (logrado 0,5)'],
      $grupos['Indicadores']['items'],
    );
    $this->assertSame(
      [
        '2025-2 · Reportado — Hecho en 2025-2 (Medios de verificación: Evidencia 2025-2)',
        '2024-2 · 80 % · Reportado — Hecho en 2024-2 (Medios de verificación: Evidencia 2024-2)',
      ],
      $grupos['Avance reportado por periodo']['items'],
    );
    $this->assertTrue($plan['aviso']);

    // Enlace desde los factores: número de metas por factor.
    $cuentas = array_column($resultado['factores']['pestanas'], 'plan_count', 'key');
    $this->assertSame(['f01' => 2, 'f02' => 1], $cuentas);
    $this->assertSame('#plan', $resultado['factores']['pestanas'][0]['plan_url']);
    $this->assertContains('node_list:meta', $cache->getCacheTags());
  }

  /**
   * Sin plan, sin metas o sin factores el plan se comporta bien.
   */
  public function testPlanVacioSinMetasNiFactores(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $d = $this->programaConPlan();
    $this->hallazgo((int) $d['proceso']->id(), 'fortaleza', 'Fortaleza', (int) $d['f1']->id());
    $factores = $presentador->factoresDelPrograma($d['programa'], new CacheableMetadata());

    // Plan sin metas: no hay sección y los factores no traen enlace.
    $resultado = $presentador->planDelPrograma($d['programa'], $factores, new CacheableMetadata());
    $this->assertSame([], $resultado['plan']);
    $this->assertArrayNotHasKey('plan_count', $resultado['factores']['pestanas'][0]);

    // Programa sin ningún proceso.
    $otro = $this->crearNodo('programa', ['title' => 'Otro', 'field_clave' => 'o']);
    $resultado = $presentador->planDelPrograma($otro, [], new CacheableMetadata());
    $this->assertSame(['factores' => [], 'plan' => []], $resultado);

    // Sin factores (modelo sin ellos): el plan sale sin filtro por factor.
    $registro = $this->crearLineamiento('Registro', 'registro_calificado');
    $sin_modelo = $this->crearNodo('programa', ['title' => 'Sin factores', 'field_clave' => 's']);
    $proceso = $this->crearNodo('proceso', [
      'field_programa' => $sin_modelo->id(),
      'field_lineamiento' => $registro->id(),
    ]);
    $plan = $this->crearNodo('plan_mejoramiento', ['field_proceso' => $proceso->id()]);
    $this->meta($plan, 1, '1');
    $resultado = $presentador->planDelPrograma($sin_modelo, [], new CacheableMetadata());
    $this->assertCount(1, $resultado['plan']['filas']);
    $this->assertSame([], $resultado['plan']['filas'][0]['keys']);
    $this->assertSame([], $resultado['plan']['opciones']);
    $this->assertSame('', $resultado['plan']['lead']);
    $this->assertFalse($resultado['plan']['aviso']);
  }

  /**
   * El aviso de «no verificado» solo sale si hay seguimientos sin verificar.
   */
  public function testAvisoSoloConSeguimientosSinVerificar(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $d = $this->programaConPlan();
    $meta = $this->meta($d['plan'], 1, '1');
    $this->seguimiento($meta, '2025-1', '1.00', 'verificado');
    $plan = $presentador->planDelPrograma($d['programa'], [], new CacheableMetadata())['plan'];
    $this->assertFalse($plan['aviso']);
    $this->assertSame("2025-1 · 100 %\nVerificado", $plan['filas'][0]['cells'][3]['value']);
    $this->seguimiento($meta, '2025-2', '0.00');
    $plan = $presentador->planDelPrograma($d['programa'], [], new CacheableMetadata())['plan'];
    $this->assertTrue($plan['aviso']);
    // Un avance de 0 es un valor, no un hueco.
    $this->assertSame("2025-2 · 0 %\nReportado", $plan['filas'][0]['cells'][3]['value']);
  }

  /**
   * Los textos salen sin doble escape y los datos raros no rompen el plan.
   */
  public function testPlanSinDobleEscapeNiErrores(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $d = $this->programaConPlan();
    $id = (int) $d['proceso']->id();
    $this->hallazgo($id, 'aspecto_por_mejorar', "Aspecto de A & B y 'C'", (int) $d['f1']->id());
    $almacen = $this->container->get('entity_type.manager')->getStorage('node');
    $propio = array_values($almacen->loadByProperties(['type' => 'hallazgo']))[0];
    // Un hallazgo de otro proceso enlazado a la meta no se muestra.
    $otro_proceso = $this->crearNodo('proceso', ['field_programa' => $d['programa']->id()]);
    $this->hallazgo((int) $otro_proceso->id(), 'aspecto_por_mejorar', 'De otro proceso', (int) $d['f1']->id());
    $ajeno = array_values($almacen->loadByProperties(['type' => 'hallazgo']));
    $ajeno = array_filter($ajeno, fn($h) => (int) $h->id() !== (int) $propio->id());

    $paragraphs = $this->container->get('entity_type.manager')->getStorage('paragraph');
    $tipo = ['type' => 'programacion_anual'];
    $anios = [
      $paragraphs->create($tipo + ['field_anio' => 2026, 'field_logrado' => '3.0000']),
      $paragraphs->create($tipo + ['field_anio' => 2024, 'field_programado' => '1.0000']),
      $paragraphs->create($tipo + ['field_anio' => 2025]),
    ];
    foreach ($anios as $anio) {
      $anio->save();
    }
    $indicador = $paragraphs->create([
      'type' => 'indicador',
      'field_nombre' => "Indicador & 'uno'",
      'field_unidad' => '%',
      'field_programacion' => $anios,
    ]);
    $indicador->save();
    $meta = $this->meta($d['plan'], 1, "2a&b'", array_merge([$propio->id()], array_map(fn($h) => $h->id(), $ajeno)), [
      'field_indicadores' => [$indicador],
    ]);
    // Avance fuera de 0 a 1: no se muestra como porcentaje.
    $this->seguimiento($meta, '2025-2', '2.50');
    $this->crearNodo('seguimiento', [
      'field_meta' => $meta->id(),
      'field_periodo' => '2025-1',
      'field_avance' => '0.40',
      'field_estado' => 'reportado',
      'field_medios_verificacion' => "Acta & 'anexo'",
    ]);

    $factores = $presentador->factoresDelPrograma($d['programa'], new CacheableMetadata());
    $plan = $presentador->planDelPrograma($d['programa'], $factores, new CacheableMetadata())['plan'];
    $celdas = $plan['filas'][0]['cells'];
    // Una sola situación (la del mismo proceso).
    $this->assertSame(['type' => 'text', 'value' => "Aspecto de A & B y 'C'"], $celdas[0]);
    // Sin doble escape: el texto va tal cual y la plantilla lo escapa una vez.
    $this->assertSame("Meta 2a&b'", $celdas[1]['eyebrow']);
    $this->assertSame("Meta 2a&b'", $celdas[1]['details']['context']);
    $this->assertSame("2025-2\nReportado", $celdas[3]['value']);
    $grupos = array_column($celdas[1]['details']['groups'], NULL, 'label');
    $this->assertSame(
      ["Indicador & 'uno' · Unidad: % · Programación anual · 2024: 1 · 2026: logrado 3"],
      $grupos['Indicadores']['items'],
    );
    $periodos = $grupos['Avance reportado por periodo']['items'];
    // Sin porcentaje (2,50 está fuera de 0 a 1), pero con su narrativa.
    $this->assertStringStartsWith('2025-2 · Reportado — ', $periodos[0]);
    $this->assertSame("2025-1 · 40 % · Reportado (Medios de verificación: Acta & 'anexo')", $periodos[1]);
  }

  /**
   * Presentador con rutas simuladas.
   *
   * En la prueba no existen /programas ni /documentos.
   *
   * @param string[] $rutas
   *   Rutas que se darán por existentes.
   */
  protected function presentadorConRutas(array $rutas): PresentadorAutoevaluacion {
    return new class($this->container->get('entity_type.manager'), $this->container->get('path.validator'), $rutas) extends PresentadorAutoevaluacion {

      /**
       * Constructor.
       */
      public function __construct($gestor, $validador, protected array $rutas) {
        parent::__construct($gestor, $validador);
      }

      /**
       * {@inheritdoc}
       */
      protected function rutaExiste(string $ruta): bool {
        return in_array($ruta, $this->rutas, TRUE);
      }

    };
  }

  /**
   * Crea un programa con un proceso publicado de un modelo.
   *
   * @return \Drupal\node\Entity\Node[]
   *   Con programa y proceso.
   */
  protected function programaConProceso(string $nombre, int $modelo, array $extra = []): array {
    $programa = $this->crearNodo('programa', [
      'title' => $nombre,
      'field_clave' => strtolower(str_replace(' ', '-', $nombre)),
    ]);
    $proceso = $this->crearNodo('proceso', $extra + [
      'title' => 'Proceso ' . $nombre,
      'field_programa' => $programa->id(),
      'field_lineamiento' => $modelo,
      'field_periodo_evaluado' => '2018–2022',
    ]);
    return ['programa' => $programa, 'proceso' => $proceso];
  }

  /**
   * Sin procesos publicados la portada no trae datos.
   */
  public function testPortadaSinProcesos(): void {
    $datos = $this->container->get('unillanos_autoeval.presentador')->datosPortada(new CacheableMetadata());
    $this->assertSame('', $datos['eyebrow']);
    $this->assertSame([], $datos['cifras']);
    $this->assertSame([], $datos['programas']);
    $this->assertSame([], $datos['factores']);
    $this->assertFalse($datos['unico']);
    $this->assertSame(0, $datos['total_programas']);
    // La ruta /documentos existe (la define este módulo): la tarjeta de
    // documentos aparece sola.
    $this->assertSame('/documentos', $datos['documentos']);
  }

  /**
   * Con un programa: cifras de su proceso, fila destacada y chips con valor.
   */
  public function testPortadaConUnPrograma(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $this->cargarGrados();
    $d = $this->programaConPlan();
    $sede = Term::create(['vid' => 'sede', 'name' => 'Sede Barcelona']);
    $sede->save();
    $facultad = Term::create(['vid' => 'facultad', 'name' => 'Facultad de Ingeniería']);
    $facultad->save();
    $d['programa']->set('field_sede', $sede->id())->set('field_facultad', $facultad->id())->save();
    $d['proceso']->set('field_periodo_evaluado', '2018–2022');
    $d['proceso']->set('field_resultado', 'Cumplimiento en alto grado (4,508 · 90 %)');
    $d['proceso']->save();
    $id = (int) $d['proceso']->id();
    $alto = array_values($this->container->get('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties(['name' => 'Alto']));
    $this->crearNodo('valoracion', [
      'field_proceso' => $id,
      'field_elemento' => $d['f1']->id(),
      'field_valoracion' => '4.27',
      'field_grado' => $alto[0]->id(),
    ]);
    $this->hallazgo($id, 'fortaleza', 'Fortaleza', (int) $d['f1']->id());
    $this->hallazgo($id, 'fortaleza', 'Otra fortaleza', (int) $d['f1']->id());
    $this->hallazgo($id, 'aspecto_por_mejorar', 'Aspecto', (int) $d['f2']->id());
    $this->meta($d['plan'], 1, '1');

    $cache = new CacheableMetadata();
    $datos = $presentador->datosPortada($cache);
    $this->assertSame('Proceso de autoevaluación 2018–2022', $datos['eyebrow']);
    $this->assertTrue($datos['unico']);
    $this->assertSame(1, $datos['total_programas']);
    $this->assertSame([
      ['value' => '1', 'label' => 'Factores evaluados'],
      ['value' => '2', 'label' => 'Fortalezas identificadas'],
      ['value' => '1', 'label' => 'Aspectos por mejorar'],
      ['value' => '1', 'label' => 'Acciones de mejora formuladas'],
    ], $datos['cifras']);
    $this->assertCount(1, $datos['programas']);
    $this->assertSame('Programa', $datos['programas'][0]['title']);
    $this->assertSame(
      'Facultad de Ingeniería · Sede Barcelona · Periodo evaluado: 2018–2022 · Resultado: Cumplimiento en alto grado (4,508 · 90 %)',
      $datos['programas'][0]['text'],
    );
    $this->assertSame('/node/' . $d['programa']->id(), $datos['programas'][0]['url']);
    // Chips: los dos factores del modelo; con un solo programa, valor guardado.
    $this->assertSame([1, 2], array_column($datos['factores'], 'n'));
    $this->assertSame(4.27, $datos['factores'][0]['score']);
    $this->assertSame('Alto', $datos['factores'][0]['grade']);
    $this->assertArrayNotHasKey('score', $datos['factores'][1]);
    $this->assertTrue($datos['hay_grado']);
    $this->assertContains('node_list:meta', $cache->getCacheTags());
    $this->assertContains('routes', $cache->getCacheTags());
  }

  /**
   * Una cifra en cero se omite; sin ninguna no hay cifras.
   */
  public function testPortadaOmiteCifrasEnCero(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $modelo = $this->crearLineamiento('Modelo');
    $this->programaConProceso('Uno', (int) $modelo->id());
    $this->assertSame([], $presentador->datosPortada(new CacheableMetadata())['cifras']);
    $this->programaConProceso('Dos', (int) $modelo->id());
    // Con dos programas solo queda la cifra de programas.
    $this->assertSame(
      [['value' => '2', 'label' => 'Programas con autoevaluación']],
      $presentador->datosPortada(new CacheableMetadata())['cifras'],
    );
  }

  /**
   * Con varios programas: totales, orden por reciente, tope y chips sin valor.
   */
  public function testPortadaConVariosProgramas(): void {
    $this->cargarGrados();
    $modelo = $this->crearLineamiento('Modelo');
    $factor = $this->elemento('Estudiantes', 1, $modelo->id());
    $nombres = [];
    for ($i = 1; $i <= 14; $i++) {
      $nombres[$i] = 'Programa ' . $i;
      $d = $this->programaConProceso($nombres[$i], (int) $modelo->id());
      $this->hallazgo((int) $d['proceso']->id(), 'fortaleza', 'Fortaleza ' . $i, (int) $factor->id());
    }
    $sin_rutas = $this->presentadorConRutas([])->datosPortada(new CacheableMetadata());
    $this->assertSame(14, $sin_rutas['total_programas']);
    $this->assertFalse($sin_rutas['unico']);
    $this->assertCount(12, $sin_rutas['programas']);
    // Los más recientes primero; los dos más antiguos quedan fuera del tope.
    $this->assertSame('Programa 14', $sin_rutas['programas'][0]['title']);
    $this->assertSame('Programa 3', $sin_rutas['programas'][11]['title']);
    $this->assertSame('', $sin_rutas['ver_todos']);
    $this->assertSame('', $sin_rutas['documentos']);
    $this->assertSame('Programas con autoevaluación publicada', $sin_rutas['eyebrow']);
    $this->assertSame([
      ['value' => '14', 'label' => 'Programas con autoevaluación'],
      ['value' => '14', 'label' => 'Fortalezas identificadas'],
    ], $sin_rutas['cifras']);
    // Con varios programas, los chips son solo número y nombre (sin promedios).
    $this->assertSame([['n' => 1, 'name' => 'Estudiantes']], $sin_rutas['factores']);
    $this->assertFalse($sin_rutas['hay_grado']);

    // «Ver todos» y documentos solo si esas rutas existen.
    $con_rutas = $this->presentadorConRutas(['/programas', '/documentos'])->datosPortada(new CacheableMetadata());
    $this->assertSame('/programas', $con_rutas['ver_todos']);
    $this->assertSame('/documentos', $con_rutas['documentos']);
    // Con 12 o menos no hay «Ver todos» aunque la ruta exista.
    $pocos = $this->presentadorConRutas(['/programas']);
    $this->assertSame('/programas', $pocos->datosPortada(new CacheableMetadata())['ver_todos']);
  }

  /**
   * Un programa sin publicar y un proceso institucional no salen.
   */
  public function testPortadaIgnoraLoQueNoSeMuestra(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $modelo = $this->crearLineamiento('Modelo');
    $visible = $this->programaConProceso('Visible', (int) $modelo->id());
    $oculto = $this->crearNodo('programa', ['title' => 'Oculto', 'field_clave' => 'oculto', 'status' => 0]);
    $this->crearNodo('proceso', ['field_programa' => $oculto->id(), 'field_lineamiento' => $modelo->id()]);
    // Proceso de una sede (institucional): sin programa.
    $sede = Term::create(['vid' => 'sede', 'name' => 'Sede']);
    $sede->save();
    $this->crearNodo('proceso', ['field_sede' => $sede->id(), 'field_lineamiento' => $modelo->id()]);
    // Proceso sin publicar de un programa visible.
    $otro = $this->crearNodo('programa', ['title' => 'Otro', 'field_clave' => 'otro']);
    $this->crearNodo('proceso', ['field_programa' => $otro->id(), 'field_lineamiento' => $modelo->id(), 'status' => 0]);

    $datos = $presentador->datosPortada(new CacheableMetadata());
    $this->assertSame(1, $datos['total_programas']);
    $this->assertSame(['Visible'], array_column($datos['programas'], 'title'));
    $this->assertTrue($datos['unico']);
    $this->assertSame('/node/' . $visible['programa']->id(), $datos['programas'][0]['url']);
  }

  /**
   * Los chips usan el modelo del proceso más reciente que tenga factores.
   */
  public function testPortadaUsaElModeloDelProcesoMasReciente(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $viejo = $this->crearLineamiento('Modelo 2020');
    $nuevo = $this->crearLineamiento('Modelo 2025');
    $registro = $this->crearLineamiento('Registro', 'registro_calificado');
    $this->elemento('Factor del 2020', 1, $viejo->id());
    $this->elemento('Factor A del 2025', 1, $nuevo->id());
    $this->elemento('Factor B del 2025', 2, $nuevo->id());
    $this->programaConProceso('Antiguo', (int) $viejo->id());
    $this->programaConProceso('Nuevo', (int) $nuevo->id());
    // El más reciente es de registro calificado (sin factores): se salta.
    $this->programaConProceso('Registro', (int) $registro->id());
    $datos = $presentador->datosPortada(new CacheableMetadata());
    $this->assertSame(['Factor A del 2025', 'Factor B del 2025'], array_column($datos['factores'], 'name'));
    $this->assertSame(3, $datos['total_programas']);
  }

  /**
   * Se usa el proceso más reciente con datos, no uno nuevo todavía vacío.
   */
  public function testPortadaUsaElProcesoConDatos(): void {
    $this->cargarGrados();
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $d = $this->programaConPlan();
    $d['proceso']->set('field_periodo_evaluado', '2018–2022')->save();
    $alto = array_values($this->container->get('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties(['name' => 'Alto']));
    $this->crearNodo('valoracion', [
      'field_proceso' => $d['proceso']->id(),
      'field_elemento' => $d['f1']->id(),
      'field_valoracion' => '4.10',
      'field_grado' => $alto[0]->id(),
    ]);
    $this->hallazgo((int) $d['proceso']->id(), 'fortaleza', 'Fortaleza', (int) $d['f1']->id());
    // Un proceso más reciente del mismo programa, todavía sin datos.
    $this->crearNodo('proceso', [
      'field_programa' => $d['programa']->id(),
      'field_lineamiento' => $d['proceso']->get('field_lineamiento')->target_id,
      'field_periodo_evaluado' => '2024–2028',
    ]);
    $datos = $presentador->datosPortada(new CacheableMetadata());
    $this->assertSame(1, $datos['total_programas']);
    $this->assertSame('Proceso de autoevaluación 2018–2022', $datos['eyebrow']);
    $this->assertSame([
      ['value' => '1', 'label' => 'Factores evaluados'],
      ['value' => '1', 'label' => 'Fortalezas identificadas'],
    ], $datos['cifras']);
    // Los chips muestran el mismo proceso que las cifras.
    $this->assertSame(4.1, $datos['factores'][0]['score']);
    $this->assertStringContainsString('2018–2022', $datos['programas'][0]['text']);
  }

  /**
   * Las acciones de mejora cuentan solo metas de planes publicados.
   */
  public function testPortadaCuentaSoloMetasDePlanesPublicados(): void {
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $d = $this->programaConPlan();
    $this->meta($d['plan'], 1, '1');
    $this->meta($d['plan'], 2, '2');
    $borrador = $this->crearNodo('plan_mejoramiento', ['field_proceso' => $d['proceso']->id(), 'status' => 0]);
    $this->meta($borrador, 1, '1');
    $this->meta($borrador, 2, '2');
    $this->meta($borrador, 3, '3');
    $cifras = $presentador->datosPortada(new CacheableMetadata())['cifras'];
    $this->assertSame([['value' => '2', 'label' => 'Acciones de mejora formuladas']], $cifras);
  }

}
