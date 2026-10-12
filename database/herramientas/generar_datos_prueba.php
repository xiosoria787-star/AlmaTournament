<?php
// =====================================================================
// GENERADOR DE LOS DATOS DE PRUEBA → database/06_datos_prueba.sql
//
//   php database/herramientas/generar_datos_prueba.php
//   (conexión como en instalar.php: --host --puerto --usuario --clave;
//    por defecto, root sin contraseña de XAMPP)
//
// La letra pide la instalación con Docker "con datos de prueba (mínimo
// 50 registros cada componente)". Este script los arma en una base
// temporal (nextleague_datos_test) con la MISMA API que usa la página,
// igual que los tests de integración: cada inscripción, equipo,
// calendario, resultado y tabla de posiciones pasa por las reglas del
// sistema. Después los exporta a 06_datos_prueba.sql, que cargan
// "instalar.php --datos-prueba" y Docker con NL_DATOS_PRUEBA=1.
//
// Las fechas del archivo son relativas al día en que se carga
// (@hoy + INTERVAL n DAY): los torneos con la inscripción abierta siguen
// en el futuro y los terminados en el pasado, se cargue cuando se cargue.
// Todas las cuentas usan la contraseña de "Cuentas para probar" del
// README. El sorteo usa una semilla fija: con el mismo código, sale el
// mismo archivo (cambian solo las fechas de registro, que dependen del día).
// =====================================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script se ejecuta desde la consola.\n");
}

date_default_timezone_set('America/Montevideo');
require __DIR__ . '/../../app/Aplicacion.php';
require_once __DIR__ . '/Instalador.php';
require_once __DIR__ . '/../../tests/soporte/ClienteApi.php';

const BASE = 'nextleague_datos_test';
const CLAVE = 'Demo#2026';
const SALIDA = __DIR__ . '/../06_datos_prueba.sql';

$opciones = getopt('', ['host::', 'puerto::', 'usuario::', 'clave::', 'conservar']);
$pdo = ConexionAdmin::desdeArgumentos($opciones);
echo "Base temporal `" . BASE . "`:\n";
(new Instalador($pdo, fn($m) => print("  $m\n")))->instalarNueva(BASE, true);
$pdo->exec('USE `' . BASE . '`');
BaseDatos::usar($pdo);
Config::establecer(['entorno' => 'desarrollo', 'carpeta_subidas' => sys_get_temp_dir() . '/nextleague_datos_subidas']);
Correo::$modoPrueba = true;
mt_srand(2026);
// Todo en una transacción, como los tests: MariaDB no escribe en disco
// sentencia por sentencia y el armado tarda segundos en vez de minutos.
$pdo->beginTransaction();

// ---------------------------------------------------------------------
// Ayudas
// ---------------------------------------------------------------------

// Un pedido a la API que tiene que salir bien (si no, se corta todo y se ve por qué).
function ok(Respuesta $respuesta, string $que): array
{
    if ($respuesta->estado >= 300) {
        throw new RuntimeException("$que → HTTP {$respuesta->estado}: " . json_encode($respuesta->cuerpo, JSON_UNESCAPED_UNICODE));
    }
    return $respuesta->cuerpo;
}

function elegir(array $lista): mixed
{
    return $lista[mt_rand(0, count($lista) - 1)];
}

function dia(int $desdeHoy): string
{
    return date('Y-m-d', strtotime("$desdeHoy days", strtotime('today')));
}

// Un momento al azar entre dos marcas de tiempo (redondeado al minuto).
function momento(int $desde, int $hasta): string
{
    $hasta = max($desde, $hasta);
    return date('Y-m-d H:i:00', mt_rand($desde, $hasta));
}

const LUGARES = [
    'deportivo'   => ['Cancha 1, Parque Batlle', 'Gimnasio del ITS Arias-Balparda', 'Polideportivo Cordón',
                      'Club Atlético Prado', 'Cancha sintética de Malvín', 'Complejo deportivo del Cerro'],
    'mental'      => ['Sala de ajedrez del ITS', 'Biblioteca Municipal de Pocitos', 'Club Uruguay',
                      'Aula 12, ITS Arias-Balparda', 'Centro Cultural de la Unión'],
    'electronico' => ['Laboratorio de informática del ITS', 'En línea (servidor del torneo)', 'Sala gamer de Pocitos'],
];

// Marcador verosímil según la disciplina. Sin empate si el torneo no lo permite.
function marcador(string $juego, bool $empateValido): array
{
    $tipos = [
        'goles'    => ['Fútbol', 'Futbol 5', 'Futsal', 'Hockey sobre césped', 'Fútbol playa', 'Rocket League', 'FIFA / EA Sports FC'],
        'basquet'  => ['Básquetbol'],
        'handball' => ['Handball'],
        'rugby'    => ['Rugby'],
        'sets5'    => ['Vóleibol', 'Tenis de mesa'],
        'sets3'    => ['Tenis', 'Bádminton', 'Squash', 'Street Fighter 6', 'League of Legends', 'Dota 2', 'Overwatch 2', 'Backgammon'],
        'rondas13' => ['Valorant', 'Counter-Strike 2'],
        'truco'    => ['Truco'],
        'domino'   => ['Dominó'],
        'scrabble' => ['Scrabble'],
        'bridge'   => ['Bridge'],
    ];
    $tipo = 'uno';   // ajedrez, damas, go, póker, judo, esgrima, boxeo: 1-0, 0-1 o tablas (1-1)
    foreach ($tipos as $nombre => $juegos) {
        if (in_array($juego, $juegos, true)) {
            $tipo = $nombre;
        }
    }
    $conEmpate = $empateValido && in_array($tipo, ['goles', 'uno', 'handball'], true) && mt_rand(1, 100) <= 22;
    [$ganador, $perdedor] = match ($tipo) {
        'goles'    => $conEmpate ? array_fill(0, 2, mt_rand(0, 3)) : [mt_rand(1, 5), 0],
        'basquet'  => [mt_rand(68, 104), 0],
        'handball' => $conEmpate ? array_fill(0, 2, mt_rand(22, 30)) : [mt_rand(24, 36), 0],
        'rugby'    => [mt_rand(15, 45), 0],
        'sets5'    => [3, mt_rand(0, 2)],
        'sets3'    => [2, mt_rand(0, 1)],
        'rondas13' => [13, mt_rand(3, 11)],
        'truco'    => [30, mt_rand(12, 28)],
        'domino'   => [100, mt_rand(40, 95)],
        'scrabble' => [mt_rand(380, 480), 0],
        'bridge'   => [mt_rand(30, 70), 0],
        'uno'      => $conEmpate ? [1, 1] : [1, 0],
    };
    if (!$conEmpate && in_array($tipo, ['goles', 'basquet', 'handball', 'rugby', 'scrabble', 'bridge'], true)) {
        $perdedor = mt_rand(match ($tipo) { 'goles' => 0, 'basquet' => 55, 'handball' => 18, 'rugby' => 0, 'scrabble' => 280, 'bridge' => 10 }, $ganador - 1);
    }
    return mt_rand(0, 1) ? [$ganador, $perdedor] : [$perdedor, $ganador];
}

// ---------------------------------------------------------------------
// 1. Usuarios
// ---------------------------------------------------------------------
echo "Usuarios, juegos y torneos (con la API)...\n";
$ultimoUsuarioBase = (int)BaseDatos::valor('SELECT MAX(id_usuario) FROM usuarios');
$ultimoJuegoBase = (int)BaseDatos::valor('SELECT MAX(id_juego) FROM juegos');
BaseDatos::ejecutar('UPDATE usuarios SET debe_cambiar_contrasena = 0 WHERE id_usuario = 1'); // solo en la base temporal
$admin = ClienteApi::como(1);

$organizadores = [];
foreach (['orga_demo', 'club_atlantico', 'ajedrez_its', 'esports_uy', 'liga_estudiantil', 'deportes_cerro', 'mente_activa', 'eventos_prado'] as $nombre) {
    $organizadores[$nombre] = Usuario::crear($nombre, "$nombre@demo.uy", CLAVE, Rol::ORGANIZADOR);
}

$NOMBRES = ['martina', 'santiago', 'valentina', 'mateo', 'sofia', 'joaquin', 'camila', 'facundo', 'lucia', 'agustin',
            'florencia', 'nicolas', 'micaela', 'bruno', 'agustina', 'franco', 'julieta', 'tomas', 'carolina', 'diego',
            'paula', 'federico', 'rocio', 'emiliano', 'victoria', 'gonzalo', 'natalia', 'matias', 'sabrina', 'ignacio',
            'daniela', 'sebastian', 'melina', 'rodrigo', 'antonella', 'maximiliano', 'belen', 'leandro', 'romina', 'alejandro'];
$APELLIDOS = ['rodriguez', 'gonzalez', 'fernandez', 'silva', 'pereira', 'martinez', 'sosa', 'alvarez', 'suarez', 'castro',
              'romero', 'olivera', 'ramos', 'nunez', 'acosta', 'mendez', 'vazquez', 'cabrera', 'ferreira', 'correa',
              'machado', 'perez', 'medina', 'rossi'];
$BIOS = ['Juego al fútbol 5 los martes con los amigos del barrio.', 'Ajedrecista aficionado, siempre con la Siciliana.',
         'Estudiante del ITS. Me anoto a todo lo que sea por equipos.', 'Main support en Valorant y fan del truco de los domingos.',
         'Tenis los fines de semana; cuando llueve, tenis de mesa.', 'Entreno básquet en el club y juego dominó con mi abuelo.',
         'Me gustan los torneos relámpago: poco tiempo y mucha adrenalina.', 'Capitana del equipo de vóley de la generación 2026.'];

$jugadores = [];
for ($i = 1; $i <= 6; $i++) {
    $jugadores[] = Usuario::crear("jugador_demo$i", "jugador_demo$i@demo.uy", CLAVE, Rol::PARTICIPANTE);
}
$usados = [];
while (count($jugadores) < 66) {
    $n = elegir($NOMBRES);
    $a = elegir($APELLIDOS);
    $nombre = elegir(["{$n}_{$a}", $n . substr($a, 0, 3), $n . mt_rand(10, 99), substr($n, 0, 4) . "_$a"]);
    if (isset($usados[$nombre])) {
        continue;
    }
    $usados[$nombre] = true;
    $id = Usuario::crear($nombre, "$nombre@demo.uy", CLAVE, Rol::PARTICIPANTE);
    if (mt_rand(1, 100) <= 45) {
        Usuario::actualizar($id, ['biografia' => elegir($BIOS)]);
    }
    $jugadores[] = $id;
}

// ---------------------------------------------------------------------
// 2. Juegos: se completa el catálogo hasta 50 con disciplinas reales
// ---------------------------------------------------------------------
foreach ([['Fútbol playa', 'deportivo', 'equipos'], ['Squash', 'deportivo', 'individual'], ['Esgrima', 'deportivo', 'individual'],
          ['Remo', 'deportivo', 'individual'], ['Surf', 'deportivo', 'individual'], ['Go', 'mental', 'individual'],
          ['Backgammon', 'mental', 'individual'], ['Scrabble', 'mental', 'individual'], ['Bridge', 'mental', 'equipos'],
          ['Dota 2', 'electronico', 'equipos'], ['Overwatch 2', 'electronico', 'equipos'], ['Street Fighter 6', 'electronico', 'individual']]
         as [$nombre, $categoria, $modalidad]) {
    BaseDatos::insertar('INSERT INTO juegos (nombre, categoria, modalidad_sugerida) VALUES (?, ?, ?)', [$nombre, $categoria, $modalidad]);
}

// ---------------------------------------------------------------------
// 3. Torneos: 54, en los tres formatos y los cuatro estados
// ---------------------------------------------------------------------
// [juego, integrantes por equipo (0 = individual)]
$DISCIPLINAS = [
    ['Fútbol', 7], ['Futbol 5', 5], ['Básquetbol', 5], ['Vóleibol', 6], ['Handball', 7], ['Futsal', 5], ['Rugby', 7],
    ['Fútbol playa', 5], ['Hockey sobre césped', 7], ['Tenis', 0], ['Tenis de mesa', 0], ['Bádminton', 0], ['Squash', 0],
    ['Judo', 0], ['Esgrima', 0], ['Boxeo', 0], ['Ajedrez', 0], ['Damas', 0], ['Póker', 0], ['Dominó', 0], ['Go', 0],
    ['Backgammon', 0], ['Scrabble', 0], ['Truco', 2], ['Bridge', 2], ['Valorant', 5], ['League of Legends', 5],
    ['Counter-Strike 2', 5], ['Rocket League', 3], ['Dota 2', 5], ['Overwatch 2', 5], ['FIFA / EA Sports FC', 0],
    ['Street Fighter 6', 0],
];
$TEMPORADAS = ['Primavera 2026', 'Invierno 2026', 'Otoño 2026', 'Apertura 2026', 'Clausura 2026', 'Verano 2027'];
$BARRIOS = ['Cordón', 'Pocitos', 'Malvín', 'Cerro', 'Prado', 'Buceo', 'Parque Rodó', 'Ciudad Vieja', 'Carrasco', 'La Unión', 'Sayago'];
$NOMBRES_TORNEO = [
    'liga'                => ['Liga {j} {t}', 'Liga Interclubes de {j}', 'Liga de {j} en {b}'],
    'eliminacion_directa' => ['Copa {j} {t}', 'Copa Relámpago de {j}', 'Eliminatoria de {j} en {b}'],
    'sistema_suizo'       => ['Abierto Suizo de {j}', 'Open de {j} en {b}', 'Suizo de {j} {t}'],
];
$EQUIPOS = ['Halcones', 'Tigres', 'Toros', 'Leones', 'Pumas', 'Cóndores', 'Titanes', 'Gladiadores', 'Centauros', 'Dragones',
            'Lobos', 'Tiburones', 'Panteras', 'Águilas', 'Rayos', 'Cometas', 'Charrúas', 'Teros', 'Yaguaretés', 'Carpinchos'];
$PREFIJOS = ['Los', 'Club', 'Atlético', 'Deportivo', 'Unión', 'Team', 'Real', 'Escuadra'];

$estados = array_merge(array_fill(0, 16, 'pendiente'), array_fill(0, 16, 'en_curso'), array_fill(0, 17, 'finalizado'),
                       ['cancelado_antes', 'cancelado_antes', 'cancelado_durante', 'cancelado_durante', 'cancelado_durante']);
shuffle($estados);
$modulos = ['liga', 'eliminacion_directa', 'sistema_suizo'];
$nombresTorneo = [];
$listaOrganizadores = array_values($organizadores);
$conCorrecciones = 0;
$ahora = time();

foreach ($estados as $i => $estado) {
    [$juego, $integrantes] = $DISCIPLINAS[($i * 7) % count($DISCIPLINAS)];
    $infoJuego = BaseDatos::fila('SELECT id_juego, categoria FROM juegos WHERE nombre = ?', [$juego]);
    $modulo = $modulos[$i % 3];
    $porEquipos = $integrantes > 0;
    $empates = $modulo !== 'eliminacion_directa' && mt_rand(1, 100) <= 70;

    // Cuántos participan y cuántas rondas se juegan
    $n = match ($modulo) {
        'liga'                => mt_rand(4, $porEquipos ? 6 : 8),
        'eliminacion_directa' => mt_rand($estado === 'en_curso' ? 5 : 4, $porEquipos ? 8 : 12),
        'sistema_suizo'       => mt_rand(6, $porEquipos ? 8 : 10),
    };
    $idaYVuelta = $modulo === 'liga' && $n <= 4 && mt_rand(0, 1);
    $rondasSuizo = $modulo === 'sistema_suizo' ? min($n - 1, mt_rand(3, 4)) : null;
    $rondas = match ($modulo) {
        'liga'                => ($n % 2 ? $n : $n - 1) * ($idaYVuelta ? 2 : 1),
        'eliminacion_directa' => (int)ceil(log($n, 2)),
        'sistema_suizo'       => $rondasSuizo,
    };
    $paso = elegir([2, 3, 4, 7]);
    $duracion = max(1, ($rondas - 1) * $paso);
    $inicio = match (true) {
        $estado === 'pendiente', $estado === 'cancelado_antes' => mt_rand(4, 45),
        $estado === 'finalizado'                               => -mt_rand(3, 150) - $duracion,
        default /* en curso, o cancelado durante */            => -intdiv($rondas - 1, 2) * $paso - 1,
    };
    $cupo = $estado === 'pendiente' && $i % 7 === 0 ? $n : max($n, elegir([8, 12, 16]));

    // Nombre único
    do {
        $nombre = strtr(elegir($NOMBRES_TORNEO[$modulo]), ['{j}' => $juego, '{t}' => elegir($TEMPORADAS), '{b}' => elegir($BARRIOS)]);
    } while (isset($nombresTorneo[$nombre]));
    $nombresTorneo[$nombre] = true;

    // Quién lo organiza: casi siempre un organizador; algunos los crea el
    // administrador y se los asigna a un organizador, y dos son del propio admin.
    $idOrganizador = $listaOrganizadores[$i % count($listaOrganizadores)];
    $creador = ClienteApi::como($idOrganizador);
    $extra = [];
    if ($i % 11 === 5) {
        $creador = $admin;
        $extra['id_organizador'] = $idOrganizador;
    } elseif ($i === 10 || $i === 30) {
        $creador = $admin;
        $idOrganizador = 1;
    }
    $organizador = ClienteApi::como($idOrganizador);

    $pendiente = in_array($estado, ['pendiente', 'cancelado_antes'], true);
    $datos = $extra + [
        'nombre'       => $nombre,
        'id_juego'     => $infoJuego['id_juego'],
        'modulo'       => $modulo,
        'modalidad'    => $porEquipos ? 'equipos' : 'individual',
        'cupo'         => $cupo,
        'premio'       => elegir([0, 0, 1500, 3000, 5000, 10000, 200]),
        'moneda'       => elegir(['UYU', 'UYU', 'UYU', 'USD']),
        // Los que ya empezaron se crean con fechas futuras (como cualquier torneo
        // nuevo) y después se corren al pasado, igual que en las pruebas.
        'fecha_inicio' => $pendiente ? dia($inicio) : dia(1),
        'fecha_fin'    => $pendiente ? dia($inicio + $duracion) : dia(1 + $duracion),
        'descripcion'  => "Torneo de $juego en formato " . ['liga' => 'liga, todos contra todos',
                          'eliminacion_directa' => 'eliminación directa', 'sistema_suizo' => 'sistema suizo'][$modulo]
                          . ". Organiza la comunidad de NextLeague; las consultas, por el perfil del organizador.",
        'permitir_empates'       => $empates ? 1 : 0,
        'ida_y_vuelta'           => $idaYVuelta ? 1 : 0,
        'rondas_suizo'           => $rondasSuizo,
        'max_integrantes_equipo' => $porEquipos ? $integrantes : null,
    ];
    $idTorneo = (int)ok($creador->post('/torneos', array_filter($datos, fn($v) => $v !== null)), "Crear $nombre")['id_torneo'];

    // El administrador autoriza las correcciones en algunas ligas (letra, 5.2)
    $corrige = $modulo === 'liga' && in_array($estado, ['finalizado', 'en_curso'], true) && $conCorrecciones < 5;
    if ($corrige) {
        ok($admin->post("/torneos/$idTorneo", ['permitir_correcciones' => 1]), "Autorizar correcciones en $nombre");
        $conCorrecciones++;
    }

    // Inscripciones (mientras el torneo está abierto)
    $lleno = $estado !== 'pendiente' || $i % 7 === 0;
    $cantidad = $lleno ? $n : mt_rand($estado === 'pendiente' && $i % 5 === 0 ? 0 : 1, $n - 1);
    $pool = $jugadores;
    shuffle($pool);
    if ($i % 2 === 0) { // jugador_demo1 participa en la mitad de los torneos
        $pool = array_values(array_unique(array_merge([$jugadores[0]], $pool)));
    }
    if (!$porEquipos) {
        foreach (array_slice($pool, 0, $cantidad) as $idJugador) {
            ok(ClienteApi::como($idJugador)->post("/torneos/$idTorneo/inscripcion"), "Inscribir en $nombre");
        }
    } else {
        $nombresEquipo = [];
        for ($k = 0; $k < $cantidad; $k++) {
            $capitan = array_shift($pool);
            do {
                $nombreEquipo = $infoJuego['categoria'] === 'electronico'
                    ? elegir(['Nova', 'Rift', 'Sur', 'Pixel', 'Cyber', 'Night', 'Storm', 'Neon']) . ' ' . elegir($EQUIPOS)
                    : elegir($PREFIJOS) . ' ' . elegir($EQUIPOS);
            } while (isset($nombresEquipo[$nombreEquipo]));
            $nombresEquipo[$nombreEquipo] = true;
            ok(ClienteApi::como($capitan)->post("/torneos/$idTorneo/equipos", ['nombre' => $nombreEquipo]), "Equipo $nombreEquipo");
            $idEquipo = (int)BaseDatos::valor('SELECT id_equipo FROM equipos WHERE id_torneo = ? AND nombre = ?', [$idTorneo, $nombreEquipo]);
            $faltan = $lleno ? $integrantes - 1 : mt_rand(1, $integrantes - 1);
            while ($faltan > 0 && $pool) {
                $idJugador = array_shift($pool);
                $idSolicitud = (int)ok(ClienteApi::como($idJugador)->post("/equipos/$idEquipo/solicitudes"), 'Solicitud')['id_solicitud'];
                $suerte = mt_rand(1, 100);
                if ($estado === 'pendiente' && $suerte <= 15) {
                    $faltan--;                                    // queda pendiente: el capitán todavía no respondió
                } elseif ($suerte <= 25) {
                    ok(ClienteApi::como($capitan)->post("/solicitudes/$idSolicitud/respuesta", ['accion' => 'rechazar']), 'Rechazar');
                    $pool[] = $idJugador;                         // puede probar con otro equipo
                } else {
                    ok(ClienteApi::como($capitan)->post("/solicitudes/$idSolicitud/respuesta", ['accion' => 'aceptar']), 'Aceptar');
                    $faltan--;
                }
            }
        }
    }

    if ($estado === 'pendiente') {
        if ($i % 8 === 3) { // un par de borradores que el organizador todavía no publicó
            ok($organizador->put("/torneos/$idTorneo/publicacion", ['publicado' => 0]), "Ocultar $nombre");
        }
        continue;
    }
    if ($estado === 'cancelado_antes') {
        ok($organizador->post("/torneos/$idTorneo/cancelar"), "Cancelar $nombre");
        continue;
    }

    // Calendario, y el torneo pasa a sus fechas reales
    ok($organizador->post("/torneos/$idTorneo/calendario"), "Calendario de $nombre");
    ok($organizador->post("/torneos/$idTorneo/rondas/publicar"), "Publicar rondas de $nombre");
    BaseDatos::ejecutar('UPDATE torneos SET fecha_inicio = ?, fecha_fin = ? WHERE id_torneo = ?',
        [dia($inicio), dia($inicio + $duracion), $idTorneo]);

    // Se juega ronda por ronda: se programa cada partido y, si su fecha ya pasó, se carga el resultado.
    $fechaDeRonda = fn(int $ronda, int $posicion) => strtotime(dia($inicio + ($ronda - 1) * $paso) . ' ' . (18 + $posicion % 4) . ':00');
    $lugares = LUGARES[$infoJuego['categoria']];
    $programar = function (array $partido) use ($organizador, $fechaDeRonda, $lugares) {
        if ($partido['fecha_hora'] === null) {
            ok($organizador->put("/enfrentamientos/{$partido['id_enfrentamiento']}", [
                'fecha_hora' => date('Y-m-d\TH:i', $fechaDeRonda((int)$partido['numero'], (int)$partido['posicion'])),
                'lugar'      => elegir($lugares),
            ]), 'Programar partido');
        }
    };
    $pendientesDe = fn() => BaseDatos::filas(
        "SELECT e.id_enfrentamiento, e.posicion, e.fecha_hora, r.numero
           FROM enfrentamientos e JOIN rondas r ON r.id_ronda = e.id_ronda
          WHERE r.id_torneo = ? AND e.estado = 'pendiente' AND e.es_pase_libre = 0
            AND e.id_local IS NOT NULL AND e.id_visitante IS NOT NULL
          ORDER BY r.numero, e.posicion", [$idTorneo]);

    while (true) {
        $pendientes = $pendientesDe();
        if (!$pendientes) {
            $numero = (int)BaseDatos::valor('SELECT MAX(numero) FROM rondas WHERE id_torneo = ?', [$idTorneo]) + 1;
            $sigue = BaseDatos::valor('SELECT estado FROM torneos WHERE id_torneo = ?', [$idTorneo]) === 'en_curso';
            if ($modulo === 'sistema_suizo' && $sigue && $fechaDeRonda($numero, 0) < $ahora) {
                ok($organizador->post("/torneos/$idTorneo/rondas"), "Ronda $numero de $nombre");
                ok($organizador->post("/torneos/$idTorneo/rondas/publicar"), 'Publicar ronda');
                continue;
            }
            break;
        }
        $ronda = (int)$pendientes[0]['numero'];
        $jugados = 0;
        foreach ($pendientes as $partido) {
            if ((int)$partido['numero'] !== $ronda) {
                break;
            }
            $programar($partido);
            if ($fechaDeRonda($ronda, (int)$partido['posicion']) < $ahora) {
                [$local, $visitante] = marcador($juego, $empates);
                ok($organizador->post("/enfrentamientos/{$partido['id_enfrentamiento']}/resultado",
                    ['puntaje_local' => $local, 'puntaje_visitante' => $visitante]), "Resultado en $nombre");
                $jugados++;
            }
        }
        if ($jugados === 0) {
            break; // lo que sigue se juega más adelante
        }
        // Corrección: el organizador autorizado corrige un resultado mal cargado
        // mientras la ronda sigue abierta (una ronda cerrada ya es definitiva).
        if ($corrige) {
            $corregir = BaseDatos::fila(
                "SELECT e.id_enfrentamiento FROM enfrentamientos e JOIN rondas r ON r.id_ronda = e.id_ronda
                  WHERE r.id_torneo = ? AND r.numero = ? AND r.estado = 'publicada' AND e.estado = 'finalizado'
                    AND e.es_pase_libre = 0 ORDER BY e.posicion LIMIT 1", [$idTorneo, $ronda]);
            if ($corregir) {
                [$local, $visitante] = marcador($juego, $empates);
                ok($organizador->post("/enfrentamientos/{$corregir['id_enfrentamiento']}/resultado", ['puntaje_local' => $local,
                    'puntaje_visitante' => $visitante, 'observacion' => 'Corrección: se había cargado con los equipos invertidos.']),
                    "Corregir en $nombre");
                $corrige = false;
            }
        }
    }
    foreach ($pendientesDe() as $partido) { // los partidos que faltan quedan con fecha y lugar
        $programar($partido);
    }

    if ($estado === 'cancelado_durante') {
        ok($organizador->post("/torneos/$idTorneo/cancelar"), "Cancelar $nombre");
    }
}

// ---------------------------------------------------------------------
// 4. Ingresos al sistema (intentos de login) y cuentas desactivadas
// ---------------------------------------------------------------------
echo "Ingresos y cuentas...\n";
$cuentas = BaseDatos::filas('SELECT id_usuario, correo FROM usuarios WHERE id_usuario > 1 ORDER BY id_usuario');
shuffle($cuentas);
foreach ($cuentas as $k => $cuenta) {
    $ip = '10.0.' . mt_rand(1, 40) . '.' . mt_rand(2, 250);
    if ($k < 14) { // algunos se equivocan antes de entrar
        (new ClienteApi($ip))->post('/auth/login', ['correo' => $cuenta['correo'], 'contrasena' => 'clave-equivocada']);
    }
    if ($k < 60) {
        ok((new ClienteApi($ip))->post('/auth/login', ['correo' => $cuenta['correo'], 'contrasena' => CLAVE]), 'Ingresar');
    }
}
foreach (['nadie@demo.uy', 'admin@demo.uy', 'test@test.com'] as $correo) {
    (new ClienteApi('10.0.99.' . mt_rand(2, 250)))->post('/auth/login', ['correo' => $correo, 'contrasena' => '123456']);
}
// Dos cuentas desactivadas por el administrador (conservan su historial)
foreach (array_slice($jugadores, -2) as $idJugador) {
    ok($admin->put("/admin/usuarios/$idJugador", ['activo' => 0]), 'Desactivar cuenta');
}

// ---------------------------------------------------------------------
// 5. Fechas verosímiles: todo se hizo "hoy"; se reparte en el tiempo
//    según las fechas de cada torneo (sin cambiar ninguna regla).
// ---------------------------------------------------------------------
echo "Fechas...\n";
foreach (BaseDatos::filas('SELECT id_torneo, fecha_inicio FROM torneos') as $torneo) {
    $id = (int)$torneo['id_torneo'];
    $inicio = strtotime($torneo['fecha_inicio']);
    $creado = strtotime(momento(min($ahora - 86400 * 2, $inicio - 86400 * 40), min($ahora - 3600, $inicio - 86400 * 12)));
    $limite = min($ahora - 600, $inicio - 3600 * 6);
    BaseDatos::ejecutar('UPDATE torneos SET fecha_creacion = ? WHERE id_torneo = ?', [date('Y-m-d H:i:s', $creado), $id]);

    foreach (BaseDatos::filas('SELECT id_equipo FROM equipos WHERE id_torneo = ?', [$id]) as $equipo) {
        $alta = momento($creado + 3600, $limite);
        BaseDatos::ejecutar('UPDATE equipos SET fecha_creacion = ? WHERE id_equipo = ?', [$alta, $equipo['id_equipo']]);
        BaseDatos::ejecutar('UPDATE participantes SET fecha_inscripcion = ? WHERE id_equipo = ?', [$alta, $equipo['id_equipo']]);
        BaseDatos::ejecutar('UPDATE equipo_miembros SET fecha_union = ? WHERE id_equipo = ?', [$alta, $equipo['id_equipo']]);
        foreach (BaseDatos::filas('SELECT id_solicitud, id_usuario, estado FROM equipo_solicitudes WHERE id_equipo = ?', [$equipo['id_equipo']]) as $s) {
            $pedido = strtotime(momento(strtotime($alta) + 600, $limite));
            $respuesta = $s['estado'] === 'pendiente' ? null : date('Y-m-d H:i:s', min($limite, $pedido + mt_rand(1800, 86400)));
            BaseDatos::ejecutar('UPDATE equipo_solicitudes SET fecha_solicitud = ?, fecha_respuesta = ? WHERE id_solicitud = ?',
                [date('Y-m-d H:i:s', $pedido), $respuesta, $s['id_solicitud']]);
            if ($s['estado'] === 'aceptada') {
                BaseDatos::ejecutar('UPDATE equipo_miembros SET fecha_union = ? WHERE id_equipo = ? AND id_usuario = ?',
                    [$respuesta, $equipo['id_equipo'], $s['id_usuario']]);
            }
        }
    }
    foreach (BaseDatos::filas('SELECT id_participante FROM participantes WHERE id_torneo = ? AND id_usuario IS NOT NULL', [$id]) as $p) {
        BaseDatos::ejecutar('UPDATE participantes SET fecha_inscripcion = ? WHERE id_participante = ?',
            [momento($creado + 3600, $limite), $p['id_participante']]);
    }
}
// Los resultados se cargan un rato después de cada partido; las correcciones, al día siguiente.
BaseDatos::ejecutar(
    "UPDATE resultados res JOIN enfrentamientos e ON e.id_enfrentamiento = res.id_enfrentamiento
        SET res.fecha_carga = LEAST(NOW() - INTERVAL 1 MINUTE, e.fecha_hora + INTERVAL (95 + res.id_resultado % 90) MINUTE
                                     + INTERVAL (res.observacion IS NOT NULL) DAY)");
BaseDatos::ejecutar(
    "UPDATE rondas r SET fecha = (SELECT DATE(MIN(e.fecha_hora)) FROM enfrentamientos e WHERE e.id_ronda = r.id_ronda)");
// Cada cuenta se registró antes de lo primero que hizo en el sistema.
foreach (BaseDatos::filas(
    "SELECT u.id_usuario, LEAST(
            COALESCE((SELECT MIN(p.fecha_inscripcion) FROM participantes p WHERE p.id_usuario = u.id_usuario), '9999-01-01'),
            COALESCE((SELECT MIN(m.fecha_union) FROM equipo_miembros m WHERE m.id_usuario = u.id_usuario), '9999-01-01'),
            COALESCE((SELECT MIN(s.fecha_solicitud) FROM equipo_solicitudes s WHERE s.id_usuario = u.id_usuario), '9999-01-01'),
            COALESCE((SELECT MIN(t.fecha_creacion) FROM torneos t WHERE t.id_organizador = u.id_usuario), '9999-01-01')) AS primera
       FROM usuarios u WHERE u.id_usuario > ?", [$ultimoUsuarioBase]) as $u) {
    $primera = $u['primera'] < '9000' ? strtotime($u['primera']) : $ahora;
    $registro = strtotime(momento($primera - 86400 * 60, $primera - 3600));
    BaseDatos::ejecutar('UPDATE usuarios SET fecha_registro = ?,
                                ultimo_acceso = IF(ultimo_acceso IS NULL, NULL, ?) WHERE id_usuario = ?',
        [date('Y-m-d H:i:s', $registro), momento(max($registro, $ahora - 86400 * 10), $ahora - 300), $u['id_usuario']]);
}
$intentos = BaseDatos::filas('SELECT id_intento FROM intentos_login ORDER BY id_intento');
$marcas = array_map(fn() => mt_rand($ahora - 86400 * 30, $ahora - 300), $intentos);
sort($marcas);
foreach ($intentos as $k => $intento) {
    BaseDatos::ejecutar('UPDATE intentos_login SET fecha = ? WHERE id_intento = ?', [date('Y-m-d H:i:00', $marcas[$k]), $intento['id_intento']]);
}

// ---------------------------------------------------------------------
// 6. Exportar a 06_datos_prueba.sql
// ---------------------------------------------------------------------
$pdo->commit();
echo "Exportando...\n";
$tablas = [
    'juegos'             => "id_juego > $ultimoJuegoBase",
    'usuarios'           => "id_usuario > $ultimoUsuarioBase",
    'torneos'            => '1',
    'configuracion_torneo' => '1',
    'equipos'            => '1',
    'equipo_miembros'    => '1',
    'equipo_solicitudes' => '1',
    'participantes'      => '1',
    'rondas'             => '1',
    'enfrentamientos'    => '1',
    'resultados'         => '1',
    'intentos_login'     => '1',
];
$hoy = strtotime(date('Y-m-d') . ' 12:00');
$relativa = function (string $fecha) use ($hoy): string {
    $dias = (int)round((strtotime(substr($fecha, 0, 10) . ' 12:00') - $hoy) / 86400);
    $dia = "@hoy + INTERVAL $dias DAY";
    return strlen($fecha) > 10 ? "TIMESTAMP($dia, '" . substr($fecha, 11, 8) . "')" : "($dia)";
};

$cuerpo = '';
$resumen = [];
foreach ($tablas as $tabla => $filtro) {
    $columnas = BaseDatos::filas('SELECT COLUMN_NAME AS nombre, DATA_TYPE AS tipo FROM information_schema.COLUMNS
                                   WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION', [BASE, $tabla]);
    $filas = BaseDatos::filas("SELECT * FROM $tabla WHERE $filtro ORDER BY 1, 2");
    $resumen[$tabla] = count($filas);
    if (!$filas) {
        continue;
    }
    $cuerpo .= "\n-- $tabla: " . count($filas) . " filas\n";
    foreach (array_chunk($filas, 40) as $tanda) {
        $valores = [];
        foreach ($tanda as $fila) {
            $celdas = [];
            foreach ($columnas as $columna) {
                $valor = $fila[$columna['nombre']];
                $celdas[] = match (true) {
                    $valor === null                                     => 'NULL',
                    in_array($columna['tipo'], ['date', 'datetime'], true) => $relativa($valor),
                    in_array($columna['tipo'], ['tinyint', 'smallint', 'int', 'bigint', 'decimal'], true) => $valor,
                    default                                             => $pdo->quote($valor),
                };
            }
            $valores[] = '(' . implode(', ', $celdas) . ')';
        }
        $cuerpo .= "INSERT INTO $tabla (" . implode(', ', array_column($columnas, 'nombre')) . ") VALUES\n"
                 . implode(",\n", $valores) . ";\n";
    }
}

$lineasResumen = '';
foreach ($resumen as $tabla => $cantidad) {
    $lineasResumen .= sprintf("--   %-20s %5d\n", $tabla, $cantidad);
}
file_put_contents(SALIDA, <<<SQL
-- =====================================================================
-- NEXTLEAGUE v2 - DATOS DE PRUEBA
-- ARCHIVO GENERADO por database/herramientas/generar_datos_prueba.php:
-- no editar a mano. Los datos se armaron con la API del sistema, así
-- que cumplen sus reglas (inscripciones, equipos, calendarios,
-- resultados y posiciones).
--
-- Se carga sobre una base recién instalada (01 → 05):
--   php database/instalar.php --bd=NOMBRE --datos-prueba
--   Docker: NL_DATOS_PRUEBA=1 en el .env antes de instalar la base.
--
-- Las fechas son relativas al día de la carga (@hoy): los torneos con
-- la inscripción abierta quedan en el futuro y los terminados en el
-- pasado. Las cuentas nuevas usan la contraseña de "Cuentas para
-- probar" del README. No cargar en un servidor con datos reales.
--
-- Filas por tabla (además de lo que trae 02_datos_base.sql; la
-- auditoría la completan los triggers al cargar):
$lineasResumen-- =====================================================================

SET NAMES utf8mb4;
SET @hoy = CURDATE();
-- En una sola transacción: la carga tarda segundos y no queda a medias.
START TRANSACTION;
$cuerpo
COMMIT;
SQL);

echo "\n06_datos_prueba.sql:\n" . str_replace('--   ', '  ', $lineasResumen);

if (!isset($opciones['conservar'])) {
    $pdo->exec('DROP DATABASE `' . BASE . '`');
}
echo "Listo.\n";
