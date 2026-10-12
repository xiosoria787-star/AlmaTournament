// =====================================================================
// CAPTURAS DE LAS VISTAS → docs/capturas/
//   npm run capturas
// No es una prueba: arma la base de las pruebas con los datos de prueba
// (database/06_datos_prueba.sql) y saca una captura de página completa
// de cada vista en PC (1280 px), y de las principales también en celular
// (375 px). Cada cuenta entra con la contraseña de "Cuentas para probar"
// del README.
// =====================================================================

const CLAVE = 'Demo#2026';
const ADMIN = 'admin@nextleague.com';
const JUGADOR = 'jugador_demo1@demo.uy';

// Quién entra (sin usuario = visitante), la dirección, y qué esperar para
// saber que terminó de cargar todo lo que llega por separado.
const VISTAS = [
    { nombre: '01-inicio', movil: true, ruta: () => 'home/',
      listo: ['#listaTorneos .tarjeta-torneo', '#proximosPartidos a', '#ultimosResultados .partido'] },
    { nombre: '02-explorar-torneos', ruta: () => 'torneos/explorar', listo: ['#listaTorneos .tarjeta-torneo'] },
    { nombre: '03-torneo-resumen', movil: true, ruta: (d) => `torneos/torneo?id=${d.liga.id}`, listo: ['#panelResumen:visible'] },
    { nombre: '04-torneo-calendario', ruta: (d) => `torneos/torneo?id=${d.liga.id}#calendario`, listo: ['#panelCalendario:visible', '#listaRondas .partido'] },
    { nombre: '05-torneo-posiciones-liga', ruta: (d) => `torneos/torneo?id=${d.liga.id}#posiciones`, listo: ['#tablaPosiciones table'] },
    { nombre: '06-torneo-llaves', ruta: (d) => `torneos/torneo?id=${d.llaves.id}#llaves`, listo: ['.llaves'] },
    { nombre: '07-torneo-posiciones-suizo', ruta: (d) => `torneos/torneo?id=${d.suizo.id}#posiciones`, listo: ['#tablaPosiciones table'] },
    { nombre: '08-torneo-equipos', ruta: (d) => `torneos/torneo?id=${d.equipos.id}#participantes`, listo: ['.participante--equipo'] },
    { nombre: '09-calendario-de-partidos', ruta: () => 'torneos/partidos', listo: ['#listaProximos a'] },
    { nombre: '10-resultados', ruta: () => 'torneos/partidos?vista=resultados', listo: ['#listaResultados .partido'] },
    { nombre: '11-perfil-publico-jugador', ruta: (d) => `perfil/jugador?id=${d.jugador}`, listo: ['#contenidoJugador:visible', '#torneosJuega .tarjeta-torneo'] },
    { nombre: '12-perfil-publico-equipo', ruta: (d) => `perfil/equipo?id=${d.equipo}`, listo: ['#contenidoEquipo:visible', '#miembrosEquipo a'] },
    { nombre: '13-ingresar', movil: true, ruta: () => 'home/login', listo: ['#formIngresar'] },
    { nombre: '14-crear-cuenta', ruta: () => 'register/register', listo: ['#formRegistro'] },
    { nombre: '15-recuperar-contrasena', ruta: () => 'home/recuperar', listo: ['#formRecuperar'] },
    { nombre: '16-participante-inscribirse', usuario: () => 'jugador_demo2@demo.uy',
      ruta: (d) => `torneos/torneo?id=${d.abierto.id}`, listo: ['#botonInscribirme'] },
    { nombre: '17-participante-mis-torneos', movil: true, usuario: () => JUGADOR, ruta: () => 'perfil/perfil#mis-torneos',
      listo: ['#contenidoPerfil:visible', '#listaJuego .tarjeta-torneo'] },
    { nombre: '18-participante-mis-partidos', usuario: () => JUGADOR, ruta: () => 'perfil/perfil#mis-partidos', listo: ['#panelPartidos:visible'] },
    { nombre: '19-participante-cuenta', usuario: () => JUGADOR, ruta: () => 'perfil/perfil#cuenta', listo: ['#panelCuenta:visible', '#formDatos'] },
    { nombre: '20-organizador-crear-torneo', usuario: (d) => d.en_curso.correo, ruta: () => 'createtournament/createtournament',
      listo: ['#modulo .opcion--formato', '#vistaPrevia .tarjeta-torneo', '#botonGuardar:not([disabled])'] },
    { nombre: '21-organizador-gestionar-en-curso', movil: true, usuario: (d) => d.en_curso.correo,
      ruta: (d) => `torneos/gestionar?id=${d.en_curso.id}`, listo: ['#contenidoGestion:visible', '#editorRonda .editor-partido'] },
    { nombre: '22-organizador-gestionar-inscripciones', usuario: (d) => d.pendiente.correo,
      ruta: (d) => `torneos/gestionar?id=${d.pendiente.id}`, listo: ['#contenidoGestion:visible', '#listaInscriptos'] },
    { nombre: '23-organizador-editar-torneo', usuario: (d) => d.pendiente.correo,
      ruta: (d) => `createtournament/createtournament?id=${d.pendiente.id}`, listo: ['#botonGuardar:not([disabled])'] },
    { nombre: '24-admin-resumen', movil: true, usuario: () => ADMIN, ruta: () => 'admin/admin#resumen', listo: ['#actividadReciente table', '#barrasRoles li'] },
    { nombre: '25-admin-usuarios', usuario: () => ADMIN, ruta: () => 'admin/admin#usuarios', listo: ['#tablaUsuarios table'] },
    { nombre: '26-admin-torneos', usuario: () => ADMIN, ruta: () => 'admin/admin#torneos', listo: ['#tablaTorneos table'] },
    { nombre: '27-admin-modulos', usuario: () => ADMIN, ruta: () => 'admin/admin#modulos', listo: ['#listaModulos > *'] },
    { nombre: '28-admin-configuracion', usuario: () => ADMIN, ruta: () => 'admin/admin#configuracion', listo: ['#formConfiguracion input'] },
    { nombre: '29-admin-auditoria', usuario: () => ADMIN, ruta: () => 'admin/admin#auditoria', listo: ['#tablaAuditoria table'] },
];

function ingresar(correo) {
    cy.clearCookies();
    cy.request({ url: 'api/auth/sesion', log: false }).then(({ body }) => cy.request({
        method: 'POST', url: 'api/auth/login', headers: { 'X-CSRF-Token': body.csrf },
        body: { correo, contrasena: CLAVE }, log: false,
    })).its('status').should('eq', 200);
}

function capturar(vista, datos, carpeta) {
    if (vista.usuario) ingresar(vista.usuario(datos));
    else cy.clearCookies();
    cy.visit(vista.ruta(datos));
    vista.listo.forEach((selector) => cy.get(selector, { timeout: 20000 }).should('exist'));
    cy.get('#pie .sitio-pie__contenido').should('exist');
    cy.get('.cargando:visible').should('not.exist');
    // Para la captura de página completa (que se arma en tramos): sin
    // desplazamiento suave y sin elementos "pegajosos", que se repetirían.
    cy.document().then((doc) => {
        doc.documentElement.style.scrollBehavior = 'auto';
        doc.querySelectorAll('body *').forEach((el) => {
            if (doc.defaultView.getComputedStyle(el).position === 'sticky') el.style.position = 'static';
        });
    });
    cy.wait(300); // imágenes y transiciones
    cy.screenshot(`${carpeta}/${vista.nombre}`, { capture: 'fullPage', overwrite: true, timeout: 120000 });
}

describe('Capturas de las vistas', () => {
    let datos;
    before(() => cy.task('cargarDatosDePrueba', null, { timeout: 600000 }).then((d) => { datos = d; }));

    describe('PC (1280 px)', () => {
        VISTAS.forEach((vista) => it(vista.nombre, () => capturar(vista, datos, 'escritorio')));
    });

    describe('Celular (375 px)', () => {
        beforeEach(() => cy.viewport(375, 812));
        VISTAS.filter((v) => v.movil).forEach((vista) => it(vista.nombre, () => capturar(vista, datos, 'celular')));
    });
});
