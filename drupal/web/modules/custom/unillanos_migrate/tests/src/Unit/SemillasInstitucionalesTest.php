<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_migrate\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Prueba la integridad de las semillas institucionales (Fase 9, etapa 2).
 *
 * Lee los CSV de `datos/semillas/`: cada referencia apunta a una fila que
 * existe, los enlaces son https y el modelo institucional trae sus factores,
 * características y aspectos con su padre.
 */
#[Group('unillanos_migrate')]
class SemillasInstitucionalesTest extends UnitTestCase {

  /**
   * Lee un CSV de las semillas.
   *
   * @param string $nombre
   *   Nombre del archivo.
   *
   * @return array<int, array<string, string>>
   *   Filas con el encabezado como clave.
   */
  protected function csv(string $nombre): array {
    $ruta = dirname(__DIR__, 8) . '/datos/semillas/' . $nombre;
    $this->assertFileExists($ruta);
    $archivo = fopen($ruta, 'r');
    $encabezado = fgetcsv($archivo, 0, ',', '"', '');
    $filas = [];
    while (($fila = fgetcsv($archivo, 0, ',', '"', '')) !== FALSE) {
      $this->assertCount(count($encabezado), $fila, $nombre . ': ' . implode('|', array_slice($fila, 0, 2)));
      $filas[] = array_combine($encabezado, $fila);
    }
    fclose($archivo);
    return $filas;
  }

  /**
   * Los ids de una columna.
   *
   * @param array<int, array<string, string>> $filas
   *   Filas.
   * @param string $columna
   *   Columna.
   *
   * @return string[]
   *   Valores, que además deben ser únicos.
   */
  protected function ids(array $filas, string $columna = 'id'): array {
    $ids = array_column($filas, $columna);
    $this->assertSame($ids, array_unique($ids), 'ids únicos en ' . $columna);
    return $ids;
  }

  /**
   * Las 25 dependencias, en el orden del sitio de Acreditación.
   */
  public function testDependencias(): void {
    $filas = $this->csv('dependencias.csv');
    $this->assertCount(25, $filas);
    $this->ids($filas);
    $this->assertSame(range(1, 25), array_map('intval', array_column($filas, 'orden')));
    $this->assertSame('Admisiones', $filas[0]['nombre']);
    foreach ($filas as $fila) {
      $this->assertStringStartsWith('https://acreditacion.unillanos.edu.co/', $fila['fuente']);
    }
  }

  /**
   * Los enlaces de las dependencias apuntan a dependencias y programas reales.
   */
  public function testDocumentosDeDependencias(): void {
    $filas = $this->csv('documentos_dependencias.csv');
    $this->assertCount(220, $filas);
    $this->ids($filas);
    $dependencias = $this->ids($this->csv('dependencias.csv'));
    $programas = $this->ids($this->csv('programas.csv'));
    $sin_programa = 0;
    foreach ($filas as $fila) {
      $this->assertContains($fila['dependencia_id'], $dependencias, $fila['id']);
      $this->assertStringStartsWith('https://drive.google.com/', $fila['enlace'], $fila['id']);
      if ($fila['programa_clave'] === '') {
        $sin_programa++;
      }
      else {
        $this->assertContains($fila['programa_clave'], $programas, $fila['id']);
      }
    }
    // Solo «Esp. en Gestión de Proyectos» no es un programa de la Universidad.
    $this->assertSame(13, $sin_programa);
    foreach ($filas as $fila) {
      if ($fila['programa_clave'] === '') {
        $this->assertSame('Esp. en Gestión de Proyectos', $fila['nombre']);
      }
    }
  }

  /**
   * Una carpeta por facultad para los directivos.
   */
  public function testDocumentosDeDirectivos(): void {
    $filas = $this->csv('documentos_directivos.csv');
    $this->assertCount(5, $filas);
    $this->ids($filas);
    $facultades = $this->ids($this->csv('facultades.csv'));
    $this->assertEqualsCanonicalizing($facultades, $this->ids($filas, 'facultad_id'));
    foreach ($filas as $fila) {
      $this->assertStringStartsWith('https://drive.google.com/', $fila['enlace']);
    }
  }

  /**
   * Procesos institucionales con sede y sus documentos.
   */
  public function testProcesosInstitucionales(): void {
    $procesos = $this->csv('procesos_institucionales.csv');
    $this->assertCount(4, $procesos);
    $ids = $this->ids($procesos);
    $sedes = $this->ids($this->csv('sedes.csv'));
    foreach ($procesos as $proceso) {
      $this->assertContains($proceso['sede_id'], $sedes, $proceso['id']);
      $this->assertContains($proceso['tipo_proceso'], ['acreditacion', 'registro_calificado']);
    }
    $this->assertEqualsCanonicalizing(
      ['villavicencio', 'granada', 'el_retorno'],
      array_values(array_intersect($sedes, ['villavicencio', 'granada', 'el_retorno'])),
    );
    $documentos = $this->csv('documentos_procesos_institucionales.csv');
    $this->assertCount(14, $documentos);
    $this->ids($documentos);
    foreach ($documentos as $documento) {
      $this->assertContains($documento['proceso_id'], $ids, $documento['id']);
      $this->assertStringStartsWith('https://drive.google.com/', $documento['enlace'], $documento['id']);
    }
  }

  /**
   * La matriz legal: 40 normas con origen, tipo y epígrafe.
   */
  public function testNormas(): void {
    $filas = $this->csv('normas.csv');
    $this->assertCount(40, $filas);
    $this->ids($filas);
    foreach ($filas as $fila) {
      $this->assertContains($fila['origen'], ['externo', 'interno'], $fila['id']);
      $this->assertNotSame('', $fila['tipo'], $fila['id']);
      $this->assertNotSame('', $fila['epigrafe'], $fila['id']);
      $this->assertSame($fila['epigrafe'], trim(preg_replace('/\s+/', ' ', $fila['epigrafe'])), 'solo arreglos de espacios');
    }
  }

  /**
   * El modelo institucional 2025: 12 factores, 41 características y aspectos.
   */
  public function testModeloInstitucional2025(): void {
    $lineamientos = $this->csv('lineamientos.csv');
    $modelo = array_values(array_filter($lineamientos, fn($f) => $f['id'] === 'CESU2025-INST'));
    $this->assertCount(1, $modelo);
    $this->assertSame('institucional', $modelo[0]['ambito']);
    $this->assertSame('acreditacion', $modelo[0]['tipo_proceso']);

    $todas = $this->csv('elementos_modelo.csv');
    $this->ids($todas);
    $filas = array_values(array_filter($todas, fn($f) => $f['lineamiento_id'] === 'CESU2025-INST'));
    $por_tipo = array_count_values(array_column($filas, 'tipo'));
    $this->assertSame(12, $por_tipo['factor']);
    $this->assertSame(41, $por_tipo['caracteristica']);
    $this->assertSame(68, $por_tipo['aspecto']);
    $ids = array_column($filas, 'id');
    foreach ($filas as $fila) {
      $this->assertLessThanOrEqual(255, mb_strlen($fila['nombre']), $fila['id']);
      $this->assertNotSame('', $fila['fuente'], $fila['id']);
      $this->assertSame('0', $fila['propio']);
      if ($fila['tipo'] === 'factor') {
        $this->assertSame('', $fila['padre_id']);
      }
      else {
        $this->assertContains($fila['padre_id'], $ids, $fila['id']);
      }
    }
    // Las características se numeran de 1 a 41 y cada factor tiene alguna.
    $numeros = array_map('intval', array_column(array_filter($filas, fn($f) => $f['tipo'] === 'caracteristica'), 'numero'));
    $this->assertSame(range(1, 41), $numeros);
    // Los modelos de programa no se tocaron.
    $programa = array_count_values(array_column(array_filter($todas, fn($f) => $f['lineamiento_id'] !== 'CESU2025-INST'), 'tipo'));
    $this->assertSame(60 + 133 + 17, array_sum($programa));
  }

}
