<?php
// =====================================================================
// BASE PARA LAS CAPTURAS DE PANTALLA (npm run capturas)
// Vuelve a crear nextleague_e2e_test con los datos de prueba
// (database/06_datos_prueba.sql) y devuelve en JSON los torneos, el
// jugador y el equipo que se muestran en las capturas.
// Solo en esta base de pruebas: el administrador inicial queda con la
// contraseña de las cuentas de prueba y sin la obligación de cambiarla,
// para poder capturar su panel.
// =====================================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../../database/herramientas/Instalador.php';

const BASE = 'nextleague_e2e_test';
const CLAVE = 'Demo#2026'; // README, "Cuentas para probar"

$pdo = ConexionAdmin::crear(getenv('NL_TEST_HOST') ?: 'localhost', (int)(getenv('NL_TEST_PUERTO') ?: 3306),
    getenv('NL_TEST_ADMIN_USUARIO') ?: 'root', getenv('NL_TEST_ADMIN_CLAVE') ?: '');
(new Instalador($pdo))->instalarNueva(BASE, true, true);
$pdo->exec('USE `' . BASE . '`');
$pdo->prepare('UPDATE usuarios SET contrasena_hash = ?, debe_cambiar_contrasena = 0 WHERE id_usuario = 1')
    ->execute([password_hash(CLAVE, PASSWORD_DEFAULT)]);

// El primer torneo que cumple la condición: su id y el correo de quien lo organiza.
// Para gestionar se prefieren los de orga_demo.
$torneo = function (string $condicion, string $orden = 't.id_torneo') use ($pdo): ?array {
    $fila = $pdo->query(
        "SELECT t.id_torneo AS id, u.correo FROM torneos t
           JOIN modulos_competencia m ON m.id_modulo = t.id_modulo
           JOIN usuarios u ON u.id_usuario = t.id_organizador
          WHERE $condicion ORDER BY $orden LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    return $fila ? ['id' => (int)$fila['id'], 'correo' => $fila['correo']] : null;
};
$inscriptos = '(SELECT COUNT(*) FROM participantes p WHERE p.id_torneo = t.id_torneo)';
$deOrgaDemo = "u.nombre_usuario = 'orga_demo' DESC, t.id_torneo";

echo json_encode([
    'liga'      => $torneo("m.codigo = 'liga' AND t.estado = 'en_curso'", "$inscriptos DESC, t.id_torneo"),
    'llaves'    => $torneo("m.codigo = 'eliminacion_directa' AND t.estado = 'finalizado'", "$inscriptos DESC, t.id_torneo"),
    'suizo'     => $torneo("m.codigo = 'sistema_suizo' AND t.estado IN ('en_curso', 'finalizado') AND t.modalidad = 'individual'"),
    'equipos'   => $torneo("t.modalidad = 'equipos' AND t.estado = 'en_curso'", "$inscriptos DESC, t.id_torneo"),
    'abierto'   => $torneo("t.estado = 'pendiente' AND t.publicado = 1 AND t.modalidad = 'individual' AND $inscriptos < t.cupo"),
    'en_curso'  => $torneo("t.estado = 'en_curso' AND m.codigo = 'liga'", $deOrgaDemo),
    'pendiente' => $torneo("t.estado = 'pendiente' AND t.publicado = 1 AND $inscriptos > 0", $deOrgaDemo),
    'jugador'   => (int)$pdo->query("SELECT id_usuario FROM usuarios WHERE nombre_usuario = 'jugador_demo1'")->fetchColumn(),
    'equipo'    => (int)$pdo->query(
        "SELECT e.id_equipo FROM equipos e JOIN torneos t ON t.id_torneo = e.id_torneo WHERE t.estado = 'en_curso'
          ORDER BY (SELECT COUNT(*) FROM equipo_miembros m WHERE m.id_equipo = e.id_equipo) DESC, e.id_equipo LIMIT 1")->fetchColumn(),
]);
