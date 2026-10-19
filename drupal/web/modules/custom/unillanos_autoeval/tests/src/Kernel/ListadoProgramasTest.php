<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Prueba el listado de programas y la regla «con autoevaluación».
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class ListadoProgramasTest extends UnillanosKernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpCurrentUser([], ['access content']);
    Vocabulary::create(['vid' => 'facultad', 'name' => 'facultad'])->save();
    $this->crearCampo('node', 'proceso', 'field_periodo_evaluado', 'string');
    $this->crearCampo('node', 'proceso', 'field_resultado', 'string');
    $this->crearCampo('taxonomy_term', 'elemento_modelo', 'field_tipo_elemento', 'string');
    $this->crearCampo('taxonomy_term', 'elemento_modelo', 'field_numero', 'integer');
    $this->crearCampo('node', 'hallazgo', 'field_tipo_hallazgo', 'string');
    $this->crearCampo('node', 'programa', 'field_facultad', 'entity_reference', ['target_type' => 'taxonomy_term']);
    $this->crearCampo('node', 'programa', 'field_sede', 'entity_reference', ['target_type' => 'taxonomy_term']);
    // Orden de los valores permitidos: pregrado antes que maestría.
    $this->crearCampo('node', 'programa', 'field_nivel', 'list_string', [
      'allowed_values' => ['pregrado' => 'Pregrado', 'maestria' => 'Maestría'],
    ]);
  }

  /**
   * Crea un término de facultad o de sede.
   */
  protected function termino(string $vocabulario, string $nombre): Term {
    $termino = Term::create(['vid' => $vocabulario, 'name' => $nombre]);
    $termino->save();
    return $termino;
  }

  /**
   * Crea un programa publicado.
   *
   * @param string $nombre
   *   Título.
   * @param array<string, mixed> $extra
   *   Valores de campos.
   */
  protected function programa(string $nombre, array $extra = []): Node {
    return $this->crearNodo('programa', $extra + [
      'title' => $nombre,
      'field_clave' => strtolower(str_replace(' ', '-', $nombre)),
    ]);
  }

  /**
   * Crea un proceso de un programa.
   */
  protected function proceso(Node $programa, int $estado = 1): Node {
    return $this->crearNodo('proceso', [
      'title' => 'Proceso ' . $programa->label(),
      'field_programa' => $programa->id(),
      'status' => $estado,
    ]);
  }

  /**
   * Los nombres de un listado, en orden.
   *
   * @param array<int, array<string, mixed>> $tarjetas
   *   Programas del listado.
   *
   * @return string[]
   *   Nombres.
   */
  protected function nombres(array $tarjetas): array {
    return array_column($tarjetas, 'nombre');
  }

  /**
   * Sin programas: nada que listar ni filtrar.
   */
  public function testSinProgramas(): void {
    $listado = $this->container->get('unillanos_autoeval.listado_programas');
    $datos = $listado->listado(new CacheableMetadata());
    $this->assertSame([], $datos['programas']);
    $this->assertSame(0, $datos['total']);
    $this->assertSame(0, $datos['con_autoevaluacion']);
    $this->assertSame([], $datos['filtros']);
  }

  /**
   * Un programa sin proceso ni sede: solo lo que existe, sin marca.
   */
  public function testUnProgramaSinProceso(): void {
    $listado = $this->container->get('unillanos_autoeval.listado_programas');
    $facultad = $this->termino('facultad', 'Facultad de Ciencias de la Salud');
    $programa = $this->programa('Enfermería', ['field_facultad' => $facultad->id(), 'field_nivel' => 'pregrado']);
    $datos = $listado->listado(new CacheableMetadata());
    $this->assertSame(1, $datos['total']);
    $this->assertSame(0, $datos['con_autoevaluacion']);
    $tarjeta = $datos['programas'][0];
    $this->assertSame('Enfermería', $tarjeta['nombre']);
    // El nombre de la facultad se entrega como está guardado, sin prefijo.
    $this->assertSame('Facultad de Ciencias de la Salud', $tarjeta['facultad']);
    $this->assertSame('Pregrado', $tarjeta['nivel']);
    $this->assertSame('/node/' . $programa->id(), $tarjeta['url']);
    $this->assertArrayNotHasKey('sede', $tarjeta);
    $this->assertArrayNotHasKey('sede_id', $tarjeta);
    $this->assertArrayNotHasKey('con_autoevaluacion', $tarjeta);
    $this->assertSame('enfermeria facultad de ciencias de la salud pregrado', $tarjeta['buscar']);
    $this->assertSame(['facultad', 'nivel'], array_keys($datos['filtros']));
  }

  /**
   * Un programa sin facultad ni nivel solo trae nombre, enlace y búsqueda.
   */
  public function testProgramaMinimoOmiteLoVacio(): void {
    $listado = $this->container->get('unillanos_autoeval.listado_programas');
    $this->programa('Solo Nombre');
    $datos = $listado->listado(new CacheableMetadata());
    $this->assertSame(['id', 'nombre', 'url', 'buscar'], array_keys($datos['programas'][0]));
    $this->assertSame([], $datos['filtros']);
  }

  /**
   * Con proceso publicado lleva la marca aunque no tenga valoraciones.
   */
  public function testProcesoSinDatosTambienMarca(): void {
    $listado = $this->container->get('unillanos_autoeval.listado_programas');
    $programa = $this->programa('Electrónica');
    $this->proceso($programa);
    $datos = $listado->listado(new CacheableMetadata());
    $this->assertSame(1, $datos['con_autoevaluacion']);
    $this->assertTrue($datos['programas'][0]['con_autoevaluacion']);
  }

  /**
   * Cuarenta programas: con autoevaluación primero y luego alfabético.
   */
  public function testCuarentaProgramasOrdenConTildes(): void {
    $listado = $this->container->get('unillanos_autoeval.listado_programas');
    $nombres = ['Zoología', 'Álgebra', 'Ética', 'Economía', 'Física', 'Biología'];
    for ($i = 1; $i <= 34; $i++) {
      $nombres[] = sprintf('Programa %02d', $i);
    }
    $this->assertCount(40, $nombres);
    $creados = [];
    foreach ($nombres as $nombre) {
      $creados[$nombre] = $this->programa($nombre);
    }
    // Tres con proceso, uno de ellos el último alfabéticamente.
    foreach (['Zoología', 'Programa 07', 'Economía'] as $nombre) {
      $this->proceso($creados[$nombre]);
    }
    $datos = $listado->listado(new CacheableMetadata());
    $this->assertSame(40, $datos['total']);
    $this->assertSame(3, $datos['con_autoevaluacion']);
    $orden = $this->nombres($datos['programas']);
    // Primero los tres con proceso, alfabéticos entre sí.
    $this->assertSame(['Economía', 'Programa 07', 'Zoología'], array_slice($orden, 0, 3));
    $resto = array_slice($orden, 3);
    $this->assertCount(37, $resto);
    // Con el orden de intl, «Álgebra» va con la A y «Ética» con la E.
    $this->assertSame(['Álgebra', 'Biología', 'Ética', 'Física', 'Programa 01'], array_slice($resto, 0, 5));
    $this->assertSame('Programa 34', $resto[count($resto) - 1]);
  }

  /**
   * Un programa o un proceso sin publicar no cuenta.
   */
  public function testLoNoPublicadoNoCuenta(): void {
    $listado = $this->container->get('unillanos_autoeval.listado_programas');
    $a = $this->programa('Con proceso borrador');
    $this->proceso($a, 0);
    $b = $this->programa('Borrador con proceso', ['status' => 0]);
    $this->proceso($b);
    $c = $this->programa('Publicado con proceso');
    $this->proceso($c);
    $datos = $listado->listado(new CacheableMetadata());
    $this->assertSame(['Publicado con proceso', 'Con proceso borrador'], $this->nombres($datos['programas']));
    $this->assertSame(1, $datos['con_autoevaluacion']);
    $this->assertSame([(int) $c->id()], array_keys($listado->procesosPorPrograma(new CacheableMetadata())));
  }

  /**
   * La sede solo es filtro con dos o más valores distintos.
   */
  public function testFiltroDeSedeConVariosValores(): void {
    $listado = $this->container->get('unillanos_autoeval.listado_programas');
    $barcelona = $this->termino('sede', 'Sede Barcelona');
    $this->programa('Uno', ['field_sede' => $barcelona->id()]);
    $this->programa('Dos', ['field_sede' => $barcelona->id()]);
    $this->programa('Tres');
    $datos = $listado->listado(new CacheableMetadata());
    $this->assertArrayNotHasKey('sede', $datos['filtros']);
    $this->assertSame('Sede Barcelona', $datos['programas'][0]['sede']);

    $granada = $this->termino('sede', 'Granada');
    $this->programa('Cuatro', ['field_sede' => $granada->id()]);
    $datos = $listado->listado(new CacheableMetadata());
    $this->assertSame(['Granada', 'Sede Barcelona'], array_column($datos['filtros']['sede'], 'etiqueta'));
  }

  /**
   * Los niveles van en el orden de la lista de valores permitidos.
   */
  public function testNivelesEnElOrdenDelCampo(): void {
    $listado = $this->container->get('unillanos_autoeval.listado_programas');
    $this->programa('Posgrado', ['field_nivel' => 'maestria']);
    $this->programa('Grado', ['field_nivel' => 'pregrado']);
    $cache = new CacheableMetadata();
    $datos = $listado->listado($cache);
    $this->assertSame(
      [['valor' => 'pregrado', 'etiqueta' => 'Pregrado'], ['valor' => 'maestria', 'etiqueta' => 'Maestría']],
      $datos['filtros']['nivel'],
    );
    $this->assertContains('config:field.storage.node.field_nivel', $cache->getCacheTags());
  }

  /**
   * Otros de la facultad: sin el actual, hasta cuatro y con proceso primero.
   */
  public function testOtrosDeLaFacultad(): void {
    $listado = $this->container->get('unillanos_autoeval.listado_programas');
    $salud = $this->termino('facultad', 'Facultad de Salud');
    $otra = $this->termino('facultad', 'Facultad de Humanas');
    $actual = $this->programa('Actual', ['field_facultad' => $salud->id()]);
    $con = $this->programa('Zeta con proceso', ['field_facultad' => $salud->id()]);
    $this->proceso($con);
    foreach (['Beta', 'Alfa', 'Gamma', 'Delta', 'Épsilon'] as $nombre) {
      $this->programa($nombre, ['field_facultad' => $salud->id()]);
    }
    $this->programa('Borrador', ['field_facultad' => $salud->id(), 'status' => 0]);
    $this->programa('De otra facultad', ['field_facultad' => $otra->id()]);
    $this->programa('Sin facultad');

    $otros = $listado->otrosDeLaFacultad($actual, new CacheableMetadata());
    $this->assertSame(['Zeta con proceso', 'Alfa', 'Beta', 'Delta'], $this->nombres($otros));
    $this->assertCount(2, $listado->otrosDeLaFacultad($actual, new CacheableMetadata(), 2));
    $sin_facultad = $this->container->get('entity_type.manager')->getStorage('node')
      ->loadByProperties(['title' => 'Sin facultad']);
    $this->assertSame([], $listado->otrosDeLaFacultad(reset($sin_facultad), new CacheableMetadata()));
  }

  /**
   * El listado y la portada deciden con la misma regla (0, 1 y varios).
   */
  public function testListadoCoincideConLaPortada(): void {
    $listado = $this->container->get('unillanos_autoeval.listado_programas');
    $presentador = $this->container->get('unillanos_autoeval.presentador');
    $comparar = function () use ($listado, $presentador): array {
      $portada = $presentador->datosPortada(new CacheableMetadata());
      $datos = $listado->listado(new CacheableMetadata());
      $this->assertSame($datos['con_autoevaluacion'], $portada['total_programas']);
      return [$portada, $datos];
    };

    // Cero.
    $this->programa('Sin nada');
    [$portada] = $comparar();
    $this->assertSame([], $portada['programas']);

    // Uno, con proceso sin datos.
    $uno = $this->programa('Uno');
    $this->proceso($uno);
    [$portada, $datos] = $comparar();
    $this->assertSame(['Uno'], array_column($portada['programas'], 'title'));
    $this->assertSame(['Uno'], $this->nombres(array_filter($datos['programas'], fn($p) => !empty($p['con_autoevaluacion']))));

    // Varios: uno con dos procesos, uno con proceso despublicado, uno
    // despublicado con proceso y uno con proceso y una valoración.
    $dos = $this->programa('Dos');
    $this->proceso($dos);
    $this->proceso($dos);
    $borrador = $this->programa('Proceso borrador');
    $this->proceso($borrador, 0);
    $oculto = $this->programa('Programa oculto', ['status' => 0]);
    $this->proceso($oculto);
    $con_datos = $this->programa('Con datos');
    $proceso = $this->proceso($con_datos);
    $this->crearNodo('hallazgo', ['field_proceso' => $proceso->id(), 'field_tipo_hallazgo' => 'fortaleza']);
    [$portada, $datos] = $comparar();
    $en_portada = array_column($portada['programas'], 'title');
    $en_listado = $this->nombres(array_filter($datos['programas'], fn($p) => !empty($p['con_autoevaluacion'])));
    sort($en_portada);
    sort($en_listado);
    $this->assertSame(['Con datos', 'Dos', 'Uno'], $en_listado);
    $this->assertSame($en_listado, $en_portada);

    // Muchos: más programas que el tope de la portada.
    for ($i = 1; $i <= 14; $i++) {
      $this->proceso($this->programa('Muchos ' . $i));
    }
    [$portada, $datos] = $comparar();
    $this->assertSame(17, $datos['con_autoevaluacion']);
    $this->assertSame(17, $portada['total_programas']);
  }

  /**
   * Crear o despublicar un proceso invalida las etiquetas del listado.
   */
  public function testProcesoInvalidaElListado(): void {
    $listado = $this->container->get('unillanos_autoeval.listado_programas');
    $programa = $this->programa('Electrónica');
    $cache = new CacheableMetadata();
    $listado->listado($cache);
    $etiquetas = $cache->getCacheTags();
    foreach (['node_list:programa', 'node_list:proceso', 'taxonomy_term_list:facultad', 'taxonomy_term_list:sede'] as $etiqueta) {
      $this->assertContains($etiqueta, $etiquetas);
    }
    $this->assertContains('user.permissions', $cache->getCacheContexts());

    // Registra las etiquetas que Drupal invalida en cada operación.
    $registro = new class() implements CacheTagsInvalidatorInterface {

      /**
       * Etiquetas invalidadas.
       *
       * @var string[]
       */
      public array $etiquetas = [];

      /**
       * {@inheritdoc}
       */
      public function invalidateTags(array $tags): void {
        $this->etiquetas = array_merge($this->etiquetas, $tags);
      }

    };
    $this->container->get('cache_tags.invalidator')->addInvalidator($registro);

    $proceso = $this->proceso($programa);
    $this->assertNotEmpty(array_intersect($etiquetas, $registro->etiquetas), 'Crear un proceso invalida el listado.');
    $this->assertContains('node_list:proceso', $registro->etiquetas);

    $registro->etiquetas = [];
    $proceso->setUnpublished()->save();
    $this->assertNotEmpty(array_intersect($etiquetas, $registro->etiquetas), 'Despublicar un proceso invalida el listado.');
    $this->assertContains('node_list:proceso', $registro->etiquetas);
    // Y el resultado cambia de verdad.
    $this->assertSame(0, $listado->listado(new CacheableMetadata())['con_autoevaluacion']);
  }

  /**
   * Sin parámetros no se oculta nada.
   */
  public function testSinConsultaNoHayOcultos(): void {
    $listado = $this->container->get('unillanos_autoeval.listado_programas');
    $this->programa('Uno');
    $this->programa('Dos');
    $datos = $listado->listadoFiltrado([], new CacheableMetadata());
    $this->assertSame(2, $datos['total']);
    $this->assertSame(2, $datos['visibles']);
    $this->assertSame([], $datos['valores']);
    $this->assertFalse($datos['consulta']['invalida']);
    $this->assertFalse($datos['consulta']['con_texto']);
    foreach ($datos['programas'] as $programa) {
      $this->assertArrayNotHasKey('oculto', $programa);
    }
  }

  /**
   * El filtro marca como ocultos a los que no coinciden pero no recorta.
   */
  public function testListadoFiltradoNoRecortaLaLista(): void {
    $listado = $this->container->get('unillanos_autoeval.listado_programas');
    $salud = $this->termino('facultad', 'Facultad de Salud');
    $humanas = $this->termino('facultad', 'Facultad de Humanas');
    for ($i = 1; $i <= 40; $i++) {
      $this->programa(sprintf('Programa %02d', $i), [
        'field_facultad' => $i % 2 ? $salud->id() : $humanas->id(),
        'field_nivel' => $i % 4 === 0 ? 'maestria' : 'pregrado',
      ]);
    }
    $datos = $listado->listadoFiltrado(['nivel' => 'maestria'], new CacheableMetadata());
    // Los 40 siguen en la lista; solo 10 coinciden.
    $this->assertCount(40, $datos['programas']);
    $this->assertSame(40, $datos['total']);
    $this->assertSame(10, $datos['visibles']);
    $ocultos = array_filter($datos['programas'], fn(array $p): bool => !empty($p['oculto']));
    $this->assertCount(30, $ocultos);
    foreach ($datos['programas'] as $programa) {
      $this->assertSame($programa['nivel_clave'] !== 'maestria', !empty($programa['oculto']));
    }
    $this->assertSame(['level' => 'maestria'], $datos['valores']);

    // Facultad y nivel juntos: se combinan.
    $datos = $listado->listadoFiltrado(
      ['facultad' => (string) $salud->id(), 'nivel' => 'pregrado'],
      new CacheableMetadata(),
    );
    $this->assertCount(40, $datos['programas']);
    $this->assertSame(['Programa 01', 'Programa 03'], array_slice($this->visibles($datos), 0, 2));
    foreach ($datos['programas'] as $programa) {
      $esperado = $programa['facultad'] === 'Facultad de Salud' && $programa['nivel_clave'] === 'pregrado';
      $this->assertSame(!$esperado, !empty($programa['oculto']));
    }
  }

  /**
   * Los nombres de los programas que no están ocultos.
   *
   * @param array<string, mixed> $datos
   *   Resultado de listadoFiltrado().
   *
   * @return string[]
   *   Nombres, en el orden de la lista.
   */
  protected function visibles(array $datos): array {
    return array_column(array_filter($datos['programas'], fn(array $p): bool => empty($p['oculto'])), 'nombre');
  }

  /**
   * El texto busca sin tildes ni mayúsculas, como el JavaScript.
   */
  public function testTextoSinTildesNiMayusculas(): void {
    $listado = $this->container->get('unillanos_autoeval.listado_programas');
    $facultad = $this->termino('facultad', 'Facultad de Ciencias Básicas e Ingeniería');
    $this->programa('Ingeniería Electrónica', ['field_facultad' => $facultad->id(), 'field_nivel' => 'pregrado']);
    $this->programa('Enfermería', ['field_nivel' => 'maestria']);
    foreach (['electronica', 'ELECTRÓNICA', 'ingeniería electrónica', '  Electrónica  '] as $texto) {
      $datos = $listado->listadoFiltrado(['q' => $texto], new CacheableMetadata());
      $this->assertSame(['Ingeniería Electrónica'], $this->visibles($datos), $texto);
    }
    // También por facultad y por nivel (el campo buscar los incluye).
    $this->assertSame(['Ingeniería Electrónica'], $this->visibles($listado->listadoFiltrado(['q' => 'basicas'], new CacheableMetadata())));
    $this->assertSame(['Enfermería'], $this->visibles($listado->listadoFiltrado(['q' => 'maestria'], new CacheableMetadata())));
    $datos = $listado->listadoFiltrado(['q' => 'zzz'], new CacheableMetadata());
    $this->assertSame([], $this->visibles($datos));
    $this->assertSame(0, $datos['visibles']);
    $this->assertSame(2, $datos['total']);
  }

  /**
   * Lo que llega de la URL se valida y lo que no sirve se descarta.
   *
   * @param array<string, mixed> $crudo
   *   Parámetros de la URL.
   * @param array<string, mixed> $esperado
   *   Valores esperados de la consulta (q, facultad, nivel, sede, invalida y
   *   con_texto).
   */
  #[DataProvider('proveedorDeConsultas')]
  public function testConsultaDescartaLoInvalido(array $crudo, array $esperado): void {
    $listado = $this->container->get('unillanos_autoeval.listado_programas');
    $filtros = [
      'facultad' => [['valor' => '7', 'etiqueta' => 'Facultad A']],
      'nivel' => [['valor' => 'pregrado', 'etiqueta' => 'Pregrado']],
    ];
    $this->assertSame($esperado, $listado->consulta($crudo, $filtros));
  }

  /**
   * Casos: vacío, válidos, fuera de la lista, arreglos, largos y peligrosos.
   *
   * @return array<string, array{array<string, mixed>, array<string, mixed>}>
   *   Parámetros y consulta esperada.
   */
  public static function proveedorDeConsultas(): array {
    $base = ['q' => '', 'facultad' => '', 'nivel' => '', 'sede' => '', 'invalida' => FALSE, 'con_texto' => FALSE];
    return [
      'sin parámetros' => [[], $base],
      'selectores vacíos del formulario' => [['q' => '', 'facultad' => '', 'nivel' => '', 'sede' => ''], $base],
      'valores válidos' => [
        ['q' => 'hola', 'facultad' => '7', 'nivel' => 'pregrado'],
        array_merge($base, ['q' => 'hola', 'facultad' => '7', 'nivel' => 'pregrado', 'con_texto' => TRUE]),
      ],
      'facultad fuera de la lista' => [['facultad' => 'xyz'], array_merge($base, ['invalida' => TRUE])],
      'nivel fuera de la lista' => [['nivel' => 'doctorado'], array_merge($base, ['invalida' => TRUE])],
      'sede sin filtro de sede' => [['sede' => '1'], array_merge($base, ['invalida' => TRUE])],
      'arreglo en nivel' => [['nivel' => ['a']], array_merge($base, ['invalida' => TRUE])],
      'arreglo en q' => [['q' => ['x']], array_merge($base, ['invalida' => TRUE])],
      'q de 500 caracteres' => [
        ['q' => str_repeat('a', 500)],
        array_merge($base, ['q' => str_repeat('a', 100), 'con_texto' => TRUE]),
      ],
      'q de 500 caracteres con tilde' => [
        ['q' => str_repeat('é', 500)],
        array_merge($base, ['q' => str_repeat('é', 100), 'con_texto' => TRUE]),
      ],
      'q con espacios' => [['q' => '  hola  '], array_merge($base, ['q' => 'hola', 'con_texto' => TRUE])],
      'q con caracteres de control' => [
        ['q' => "ab\x00\x1Fcd\x7F"],
        array_merge($base, ['q' => 'abcd', 'con_texto' => TRUE]),
      ],
      'q con UTF-8 no válido' => [
        ['q' => "\xC3\x28"],
        array_merge($base, ['invalida' => TRUE]),
      ],
      'q con etiqueta' => [
        ['q' => '"><script>alert(1)</script>'],
        array_merge($base, ['q' => '"><script>alert(1)</script>', 'con_texto' => TRUE]),
      ],
    ];
  }

}
