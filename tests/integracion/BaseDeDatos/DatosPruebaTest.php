<?php
// Verifica los datos de prueba (database/06_datos_prueba.sql) como los
// carga "instalar.php --datos-prueba": que haya al menos 50 registros de
// cada componente (lo pide la letra para la entrega con Docker) y que
// sean coherentes con las reglas y con el día de la carga.

use PHPUnit\Framework\TestCase;

final class DatosPruebaTest extends TestCase
{
    private const BASE = 'nextleague_datos_prueba_test';
    private static PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = BaseDePruebas::admin();
        (new Instalador(self::$pdo))->instalarNueva(self::BASE, true, true);
        self::$pdo->exec('USE `' . self::BASE . '`');
    }

    public static function tearDownAfterClass(): void
    {
        self::$pdo->exec('DROP DATABASE IF EXISTS `' . self::BASE . '`');
    }

    private function valor(string $sql): mixed
    {
        return self::$pdo->query($sql)->fetchColumn();
    }

    public function testCadaComponenteTieneAlMenos50Registros(): void
    {
        foreach (['usuarios', 'juegos', 'torneos', 'configuracion_torneo', 'equipos', 'equipo_miembros',
                  'equipo_solicitudes', 'participantes', 'rondas', 'enfrentamientos', 'resultados',
                  'intentos_login', 'auditoria'] as $tabla) {
            $this->assertGreaterThanOrEqual(50, (int)$this->valor("SELECT COUNT(*) FROM $tabla"), $tabla);
        }
    }

    public function testHayTorneosDeLosTresFormatosYLosCuatroEstados(): void
    {
        $this->assertSame(3, (int)$this->valor('SELECT COUNT(DISTINCT id_modulo) FROM torneos'));
        $this->assertSame(4, (int)$this->valor('SELECT COUNT(DISTINCT estado) FROM torneos'));
        $this->assertSame(2, (int)$this->valor('SELECT COUNT(DISTINCT modalidad) FROM torneos'));
    }

    public function testLasFechasQuedanRelativasAlDiaDeLaCarga(): void
    {
        // Con inscripción abierta: empiezan después de hoy. Terminados: ya terminaron.
        $this->assertSame(0, (int)$this->valor("SELECT COUNT(*) FROM torneos WHERE estado = 'pendiente' AND fecha_inicio <= CURDATE()"));
        $this->assertSame(0, (int)$this->valor("SELECT COUNT(*) FROM torneos WHERE estado = 'finalizado' AND fecha_fin >= CURDATE()"));
        $this->assertSame(0, (int)$this->valor("SELECT COUNT(*) FROM torneos WHERE estado = 'en_curso'
                                                 AND NOT (fecha_inicio <= CURDATE() AND fecha_fin >= CURDATE())"));
        // Nada se registró ni se cargó en el futuro
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM usuarios WHERE fecha_registro > NOW()'));
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM resultados WHERE fecha_carga > NOW()'));
        $this->assertSame(0, (int)$this->valor('SELECT COUNT(*) FROM participantes p JOIN torneos t ON t.id_torneo = p.id_torneo
                                                 WHERE p.fecha_inscripcion > LEAST(NOW(), TIMESTAMP(t.fecha_inicio, "23:59:59"))'));
    }

    public function testLosTorneosTerminadosTienenTodosSusPartidosJugados(): void
    {
        $this->assertSame(0, (int)$this->valor(
            "SELECT COUNT(*) FROM enfrentamientos e JOIN rondas r ON r.id_ronda = e.id_ronda JOIN torneos t ON t.id_torneo = r.id_torneo
              WHERE t.estado = 'finalizado' AND e.es_pase_libre = 0 AND e.estado = 'pendiente'"));
        // Cada partido jugado tiene exactamente un resultado vigente
        $this->assertSame(0, (int)$this->valor(
            "SELECT COUNT(*) FROM enfrentamientos e
              WHERE e.estado = 'finalizado' AND e.es_pase_libre = 0
                AND (SELECT COUNT(*) FROM resultados r WHERE r.id_enfrentamiento = e.id_enfrentamiento AND r.vigente = 1) <> 1"));
        // Ningún resultado es de un partido que todavía no se jugó
        $this->assertSame(0, (int)$this->valor(
            'SELECT COUNT(*) FROM resultados r JOIN enfrentamientos e ON e.id_enfrentamiento = r.id_enfrentamiento WHERE e.fecha_hora > NOW()'));
        // Hay correcciones (resultado anterior que quedó como no vigente)
        $this->assertGreaterThan(0, (int)$this->valor('SELECT COUNT(*) FROM resultados WHERE vigente = 0'));
    }

    public function testLasCuentasDePruebaIngresanConLaClaveDelReadme(): void
    {
        foreach (['orga_demo', 'jugador_demo1'] as $nombre) {
            $hash = $this->valor("SELECT contrasena_hash FROM usuarios WHERE nombre_usuario = '$nombre'");
            $this->assertTrue(password_verify('Demo#2026', (string)$hash), $nombre);
        }
        // El administrador inicial sigue obligado a cambiar su contraseña
        $this->assertSame(1, (int)$this->valor('SELECT debe_cambiar_contrasena FROM usuarios WHERE id_usuario = 1'));
    }

    public function testLaAplicacionPuedeLeerlosConSuCuentaLimitada(): void
    {
        // Las vistas que usa la página devuelven los datos cargados
        $this->assertGreaterThanOrEqual(50, (int)$this->valor('SELECT COUNT(*) FROM vista_torneos'));
        $this->assertGreaterThan(0, (int)$this->valor('SELECT COUNT(*) FROM vista_tabla_posiciones'));
    }
}
