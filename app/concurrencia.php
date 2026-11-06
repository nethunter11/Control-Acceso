<?php

final class BloqueoRutOcupado extends RuntimeException {}

function ejecutar_por_rut(PDO $pdo, string $ambito, string $rut, callable $accion): array {
  if ($pdo->inTransaction()) {
    throw new LogicException('El bloqueo por RUT debe comenzar antes de la transacción.');
  }

  // El nombre incluye la base: las pruebas no bloquean la versión estable.
  $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
  $lockName = 'ca:' . substr(hash('sha256', $database . "\0" . $ambito . "\0" . $rut), 0, 60);
  $lock = $pdo->prepare('SELECT GET_LOCK(?, 5)');
  $lock->execute([$lockName]);
  $obtained = $lock->fetchColumn();
  if ($obtained === null || $obtained === false) {
    throw new RuntimeException('No se pudo coordinar el movimiento.');
  }
  if ((int)$obtained !== 1) {
    throw new BloqueoRutOcupado('Hay otro movimiento de este RUT en proceso. Inténtalo nuevamente.');
  }

  try {
    // La transacción comienza después de esperar el turno de este RUT.
    $pdo->beginTransaction();
    $response = $accion();
    $pdo->commit();
    return $response;
  } catch (Throwable $error) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    throw $error;
  } finally {
    // GET_LOCK no se libera con COMMIT ni ROLLBACK.
    try {
      $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
      $release->execute([$lockName]);
    } catch (Throwable $releaseError) {
      // La conexión no es persistente y también libera sus bloqueos al cerrarse.
      error_log('No se pudo liberar el bloqueo de movimiento: ' . $releaseError->getMessage());
    }
  }
}
