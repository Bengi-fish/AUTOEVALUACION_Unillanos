<?php

declare(strict_types=1);

namespace Drupal\Tests\unillanos_autoeval\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;

/**
 * Prueba el borrado de Acreditación: solo lo propio y sin dependientes.
 *
 * «Propio» es el autor (uid): lo cargado por migración (autor anónimo) no es
 * de Acreditación. Si otro contenido apunta al que se quiere borrar, el borrado
 * se niega. Cada borrado queda en el registro del sitio.
 */
#[Group('unillanos_autoeval')]
#[RunTestsInSeparateProcesses]
class EliminarAcreditacionTest extends EliminarKernelBase {

  /**
   * Acreditación borra lo propio cuando nada depende de ello.
   */
  #[DataProvider('tipos')]
  public function testBorraLoPropio(string $tipo): void {
    $nodo = $this->nodoDe($tipo, (int) $this->acreditacion->id());
    $this->assertTrue($nodo->access('delete', $this->acreditacion), $tipo);
    $this->assertTrue($this->documentoDe((int) $this->acreditacion->id())->access('delete', $this->acreditacion));
  }

  /**
   * No borra lo de otro autor ni lo cargado por la migración.
   */
  #[DataProvider('tipos')]
  public function testNoBorraLoAjenoNiLoMigrado(string $tipo): void {
    $otro = $this->usuario(['authenticated']);
    $this->assertFalse($this->nodoDe($tipo, (int) $otro->id())->access('delete', $this->acreditacion), "$tipo de otro autor");
    $this->assertFalse($this->nodoDe($tipo, 0)->access('delete', $this->acreditacion), "$tipo migrado");
    $this->assertFalse($this->documentoDe((int) $otro->id())->access('delete', $this->acreditacion));
    $this->assertFalse($this->documentoDe(0)->access('delete', $this->acreditacion));
  }

  /**
   * El anónimo nunca borra.
   */
  public function testElAnonimoNuncaBorra(): void {
    $anonimo = new AnonymousUserSession();
    foreach (self::TIPOS as $tipo) {
      $this->assertFalse($this->nodoDe($tipo, 0)->access('delete', $anonimo), $tipo);
      $this->assertFalse($this->nodoDe($tipo, (int) $this->acreditacion->id())->access('delete', $anonimo), $tipo);
    }
    $this->assertFalse($this->documentoDe(0)->access('delete', $anonimo));
  }

  /**
   * Con dependientes no se borra; al quitarlos, sí (la caché se invalida).
   */
  public function testConDependientesNoSeBorra(): void {
    $yo = (int) $this->acreditacion->id();
    $programa = $this->nodoDe('programa', $yo);
    $proceso = $this->nodoDe('proceso', $yo, ['field_programa' => $programa->id()]);
    $valoracion = $this->nodoDe('valoracion', $yo, ['field_proceso' => $proceso->id()]);
    $hallazgo = $this->nodoDe('hallazgo', $yo, ['field_proceso' => $proceso->id()]);
    $plan = $this->nodoDe('plan_mejoramiento', $yo, ['field_proceso' => $proceso->id()]);
    $campos_meta = ['field_plan' => $plan->id(), 'field_hallazgos' => [$hallazgo->id()]];
    $meta = $this->nodoDe('meta', $yo, $campos_meta);
    $seguimiento = $this->nodoDe('seguimiento', $yo, ['field_meta' => $meta->id()]);
    $documento = $this->documentoDe($yo, ['field_proceso' => $proceso->id()]);
    $con_documento = $this->nodoDe('valoracion', $yo, ['field_proceso' => $proceso->id()]);
    $cuenta = $this->acreditacion;
    // Cada uno tiene algo que depende de él.
    $this->assertFalse($programa->access('delete', $cuenta), 'programa con proceso');
    $this->assertFalse($proceso->access('delete', $cuenta), 'proceso con valoraciones, hallazgos, plan y documento');
    $this->assertFalse($hallazgo->access('delete', $cuenta), 'hallazgo con meta');
    $this->assertFalse($plan->access('delete', $cuenta), 'plan con metas');
    $this->assertFalse($meta->access('delete', $cuenta), 'meta con seguimiento');
    // Lo que no tiene dependientes sí se puede borrar.
    $this->assertTrue($seguimiento->access('delete', $cuenta));
    $this->assertTrue($valoracion->access('delete', $cuenta));
    $this->assertTrue($documento->access('delete', $cuenta));
    // Un documento enlazado desde una valoración tampoco se borra.
    $con_documento->set('field_documento', [$documento->id()])->save();
    // El acceso de un medio se guarda en memoria por petición: se reinicia.
    $this->reiniciarAcceso();
    $this->assertFalse($documento->access('delete', $cuenta), 'documento enlazado desde una valoración');
    $con_documento->delete();
    $this->reiniciarAcceso();
    $this->assertTrue($documento->access('delete', $cuenta));
    // Se borra desde lo más dependiente hacia arriba y cada paso lo libera.
    $seguimiento->delete();
    $this->reiniciarAcceso();
    $this->assertTrue($meta->access('delete', $cuenta));
    $meta->delete();
    $this->reiniciarAcceso();
    $this->assertTrue($plan->access('delete', $cuenta));
    $plan->delete();
    $this->reiniciarAcceso();
    $this->assertTrue($hallazgo->access('delete', $cuenta));
    $hallazgo->delete();
    $this->reiniciarAcceso();
    $valoracion->delete();
    $this->reiniciarAcceso();
    $this->assertFalse($proceso->access('delete', $cuenta), 'aún queda el documento');
    $documento->delete();
    $this->reiniciarAcceso();
    $this->assertTrue($proceso->access('delete', $cuenta));
    $proceso->delete();
    $this->reiniciarAcceso();
    $this->assertTrue($programa->access('delete', $cuenta));
  }

  /**
   * Quien administra el contenido conserva su acceso, con o sin dependientes.
   */
  public function testElAdministradorNoSeBloquea(): void {
    $rol = Role::create([
      'id' => 'admin_contenido',
      'label' => 'Admin',
      'permissions' => ['bypass node access', 'administer media'],
    ]);
    $rol->save();
    $admin = $this->usuario(['admin_contenido']);
    $programa = $this->nodoDe('programa', 0);
    $this->nodoDe('proceso', 0, ['field_programa' => $programa->id()]);
    $this->assertTrue($programa->access('delete', $admin));
    $this->assertFalse($programa->access('delete', $this->acreditacion));
  }

  /**
   * Cada borrado queda en el registro con usuario, fecha, tipo, id y título.
   */
  public function testElBorradoQuedaEnElRegistro(): void {
    $mensajes = [];
    $registro = new class($mensajes) implements LoggerInterface {

      use \Drupal\Core\Logger\RfcLoggerTrait;

      /**
       * Constructor.
       *
       * @param array<int, array{0: string, 1: string|\Stringable, 2: array<string, mixed>}> $mensajes
       *   Recibe lo que se registra.
       */
      public function __construct(public array &$mensajes) {}

      /**
       * {@inheritdoc}
       */
      public function log($level, string|\Stringable $message, array $context = []): void {
        $this->mensajes[] = [(string) $context['channel'], $message, $context];
      }

    };
    $this->container->get('logger.factory')->addLogger($registro);
    $meta = $this->nodoDe('meta', (int) $this->acreditacion->id(), ['title' => 'Meta de prueba']);
    $id = $meta->id();
    $meta->delete();
    $mios = array_values(array_filter($mensajes, fn($m) => $m[0] === 'unillanos_autoeval'));
    $this->assertCount(1, $mios);
    $contexto = $mios[0][2];
    $this->assertSame('node:meta', $contexto['@tipo']);
    $this->assertSame('Meta de prueba', $contexto['@titulo']);
    $this->assertEquals($id, $contexto['@id']);
    $this->assertSame($this->acreditacion->getAccountName(), $contexto['@usuario']);
    $this->assertEquals($this->acreditacion->id(), $contexto['@uid']);
    $this->assertMatchesRegularExpression('~^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$~', $contexto['@fecha']);
    // Un tipo que no es de autoevaluación no se anota.
    $this->crearNodo('noticia', ['title' => 'Noticia'])->delete();
    $this->assertCount(1, array_filter($mensajes, fn($m) => $m[0] === 'unillanos_autoeval'));
  }

}
