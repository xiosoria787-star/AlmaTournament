# NextLeague

Plataforma web para organizar torneos deportivos, mentales y electrónicos (liga, eliminación directa y sistema suizo). Proyecto de egreso del BT Tecnologías de la Información, ITS Arias-Balparda, 2026.

Hecha en PHP sin frameworks (patrón MVC con POO), MySQL/MariaDB y HTML, CSS y JavaScript.

## Estructura

| Carpeta | Contenido |
|---|---|
| `app/core` | Router, base de datos, sesión, validación, errores |
| `app/modelos` | Modelos: todo el SQL está acá |
| `app/controladores` | Controladores de la API |
| `app/modulos` | Liga, eliminación directa, suizo |
| `app/servicios` | Reglas de la competencia |
| `public/` | Páginas (vistas) y la API en `public/api/` |
| `database/` | Scripts SQL, instalador, migración y datos de prueba |
| `docker/`, `scripts/` | Instalación con Docker y en el servidor AlmaLinux |
| `docs/` | Diagramas UML, casos de uso y capturas de las vistas |
| `tests/` | Tests con PHPUnit (`unit`, `integracion`) y Cypress (`e2e`) |

## Documentación

- [Diagramas UML](docs/uml/): [clases del dominio](docs/uml/01_clases_dominio.svg), [clases de la arquitectura](docs/uml/02_clases_arquitectura.svg), casos de uso ([visitante y participante](docs/uml/03a_casos_de_uso_visitante_participante.svg), [organizador](docs/uml/03b_casos_de_uso_organizador.svg), [administrador](docs/uml/03c_casos_de_uso_administrador.svg)) y [despliegue](docs/uml/04_despliegue.svg). Cada uno tiene su fuente en PlantUML (`.puml`) para editarlo.
- [Especificación de casos de uso](docs/casos_de_uso.md): 37 casos con sus flujos, las reglas de negocio y la matriz de actores. También en [PDF](docs/casos_de_uso.pdf).
- [Capturas de las vistas](docs/capturas/): cada pantalla con los datos de prueba, en PC y en celular. Se regeneran con `npm run capturas`.

## Instalación local (XAMPP)

Necesitás Apache y MySQL de XAMPP encendidos. En XAMPP, `php` es `C:\xampp\php\php.exe`.

1. Poner el proyecto en `C:\xampp\htdocs\nextleague`: clonarlo ahí o, si está en otra carpeta, crear un enlace desde `cmd`:
   ```
   mklink /J C:\xampp\htdocs\nextleague "C:\ruta\al\proyecto"
   ```
2. Crear la base de datos (con tablas, vistas, triggers de auditoría y usuarios de MySQL). Con `--datos-prueba` se cargan además los datos de prueba:
   ```
   php database/instalar.php --bd=nextleague_v2 --datos-prueba
   ```
3. (Opcional) Copiar `config.ejemplo.php` como `config.php` si necesitás otros datos de conexión, o si la carpeta en `htdocs` tiene otro nombre (en ese caso cambiá `url_publica`).
4. Abrir http://localhost/nextleague/

Las páginas se abren sin `.html` (por ejemplo `.../public/torneos/explorar` y la portada en `.../public/home/`). Lo resuelve `public/.htaccess` con mod_rewrite, que XAMPP trae encendido (la API también lo usa). Las direcciones viejas con `.html` llevan a las nuevas.

Sin Apache también funciona con el servidor de desarrollo de PHP (en `config.php`, `url_publica` tiene que ser `http://127.0.0.1:8080/public`):
```
php -S 127.0.0.1:8080 -t . tools/servidor_dev.php
```
y abrir http://127.0.0.1:8080

### Cuentas para probar

| Rol | Usuario | Correo | Contraseña |
|---|---|---|---|
| Administrador | `admin` | `admin@nextleague.com` | `Admin123!` |
| Organizador | `orga_demo` | `orga_demo@demo.uy` | `Demo#2026` |
| Participantes | `jugador_demo1` a `jugador_demo6` | `jugador_demo1@demo.uy` … `jugador_demo6@demo.uy` | `Demo#2026` |

- `admin` es el administrador inicial que crea `02_datos_base.sql`. En el primer ingreso obliga a cambiar la contraseña; en el servidor hay que cambiarla sí o sí.
- Las cuentas `_demo` y las otras 66 de los datos de prueba (organizadores como `club_atlantico` o `esports_uy`, y participantes) usan la misma contraseña, `Demo#2026`. Existen solo si la base se instaló con los datos de prueba.
- Las cuentas de las pruebas automáticas (`admin_e2e`, `orga_e2e`, `jugador_e2e1`…) existen solo en `nextleague_e2e_test`, que Cypress vuelve a crear en cada corrida. Su contraseña está en `cypress.config.js`.

### Datos de prueba

`database/06_datos_prueba.sql` carga más de 50 registros de cada parte del sistema, como pide la letra: 75 usuarios, 50 juegos, 54 torneos en los tres formatos y los cuatro estados, 163 equipos con 759 integrantes y 789 solicitudes, 338 participantes, 130 rondas, 410 partidos, 319 resultados (con correcciones), 77 intentos de ingreso y la auditoría que generan los triggers al cargarlos. Se carga con `instalar.php --datos-prueba`, o en Docker con `NL_DATOS_PRUEBA=1` antes de instalar la base.

- Las fechas son relativas al día de la carga: los torneos con la inscripción abierta quedan en el futuro y los terminados en el pasado, se carguen cuando se carguen.
- El archivo lo arma `php database/herramientas/generar_datos_prueba.php` con la misma API que usa la página (como los tests de integración), así que cumple todas las reglas. Tarda unos minutos.
- No cargarlos en un servidor con datos reales: todas sus cuentas comparten una contraseña conocida.

Para migrar una base de la versión anterior sin perder datos (hacé un respaldo antes):
```
php database/instalar.php --bd=nextleague --migrar
```
Los archivos subidos en la versión anterior estaban en `public/backend/uploads/`; ahora van en `public/uploads/`.

## Instalación con Docker

Para llevar el sistema a un servidor, o para probarlo como va a quedar ahí, sin XAMPP. Necesitás Docker (en Windows, Docker Desktop abierto).

1. Copiar `.env.example` como `.env` y poner claves propias en `NL_BD_CLAVE_ROOT`, `NL_BD_CLAVE` y `NL_BD_CLAVE_LECTURA` (con las de ejemplo no se instala). `.env` no se sube a git.
2. Levantar los contenedores. La primera vez se descargan las imágenes, se arma la de la aplicación y se instala la base:
   ```
   docker compose up -d --build
   ```
3. Abrir https://localhost:8443 (http://localhost:8090 redirige ahí). El certificado es autofirmado, así que el navegador avisa "no seguro" hasta que pongas el real en `docker/ssl/` (`certificado.crt` y `clave.key`) y reinicies con `docker compose restart web`.

| Contenedor | Qué es | Puerto en la máquina |
|---|---|---|
| `nextleague_web` | La aplicación: PHP 8.2 + Apache, con `public/` como raíz y HTTPS | 8443 (HTTPS) y 8090 (redirige) |
| `nextleague_bd` | MariaDB 10.11 | Ninguno: solo la ven los contenedores de la red |
| `nextleague_phpmyadmin` | phpMyAdmin (pide usuario y clave) | 8091, solo desde esa misma máquina |

- La red es `nextleague_red`. Los volúmenes son `nextleague_datos_bd` (la base), `nextleague_subidas` (fotos, logos y reglas) y `nextleague_almacen` (sesiones y registros).
- La primera vez, `docker/bd/instalar.sh` ejecuta los mismos scripts que `instalar.php` (01 → 05). En Docker, las cuentas `nl_app` y `nl_lectura` se conectan desde la red (`'%'`) con las claves del `.env`. El administrador inicial es el mismo de siempre y pide cambiar la contraseña al entrar.
- La aplicación toma su configuración de las variables del `.env` (`docker/web/config.php`). El `config.php` de tu PC no entra en la imagen.
- Dentro de Docker no hay servidor de correo. Con `NL_ENTORNO=desarrollo`, el link para restablecer la contraseña se muestra en pantalla.
- `docker compose down` apaga y conserva los datos. `docker compose down -v` borra también los volúmenes, y la próxima vez se instala de cero.

### En el servidor AlmaLinux (producción)

```
sudo dnf -y install git
sudo git clone -b nextleague https://github.com/xiosoria787-star/AlmaTournament.git /opt/nextleague
cd /opt/nextleague
sudo bash scripts/instalar_almalinux.sh
```

Para la demostración con datos de prueba, el último comando es `sudo bash scripts/instalar_almalinux.sh --datos-prueba`.

El script instala Docker si hace falta (desde el repositorio oficial), crea el `.env` con claves al azar, los puertos 80 y 443 y el modo producción, y pregunta la dirección del sitio. Después abre esos puertos en firewalld, levanta los contenedores y prueba que el sitio responda. Para actualizar, hacé `git pull` y volvé a ejecutarlo: no cambia el `.env` ni los datos. Ojo: si cambia la estructura de la base, el cambio no se aplica solo sobre una base ya instalada.

## Tests

### Unitarios y de integración (PHPUnit)

```
php tools/phpunit.phar
```

Usan una base aparte (`nextleague_test`) que se crea sola en cada corrida. PHPUnit se descarga una vez en `tools/` desde https://phar.phpunit.de/phpunit-11.phar.

### De punta a punta (Cypress)

Recorren el sitio en un navegador real (Chrome), como lo haría una persona: registro e ingreso, crear y gestionar torneos, inscripciones, resultados, administración, seguridad y cada pantalla en celular, tableta y PC. Necesitan Node.js, Chrome y MySQL de XAMPP encendido.

```
npm install        (una sola vez: descarga Cypress)
npm run e2e        (corre todas las pruebas)
npm run e2e:abrir  (abre Cypress para verlas paso a paso)
npm run capturas   (vuelve a sacar las capturas de docs/capturas)
```

Cypress levanta solo un servidor en el puerto 8081 contra la base `nextleague_e2e_test`, que se vuelve a crear con datos conocidos antes de cada archivo de pruebas (`tests/e2e/preparar_base.php`): no tocan los datos de desarrollo. Las capturas de cada pantalla quedan en `tests/e2e/screenshots/`. Si PHP no está en `C:\xampp\php\php.exe`, indicá la ruta en la variable `NL_PHP`.

Los archivos 09 a 13 recorren los **flujos completos**: del registro a jugar un torneo, los equipos, del alta del organizador al campeón (los tres formatos), el administrador y el público, y cómo se comporta el sistema **según el momento** (antes, durante y después de un torneo, y lo que vence: el enlace de recuperación, el bloqueo, la sesión y "Recordarme"). Para eso no se espera de verdad: `tests/e2e/simular_tiempo.php` atrasa las fechas guardadas en la base de pruebas. Cada prueba anota sus pasos (`cy.paso`) y lo que conviene revisar (`cy.observacion`), y al terminar la corrida todo queda, con el resultado de cada prueba, en `tests/e2e/registro/pasos.json`.
