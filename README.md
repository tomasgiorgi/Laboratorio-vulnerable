# Laboratorio Vulnerable - Kalinasa

Un laboratorio de pentesting diseñado y construido de manera independiente, con vulnerabilidades intencionales inspiradas en el formato CTF (Capture The Flag). El objetivo fue diseñar una cadena de ataque completa y realista, desde el reconocimiento inicial hasta la escalación de privilegios a root.

Este repositorio documenta el proceso completo de explotación, incluyendo el razonamiento detrás de cada paso.

## Objetivo del laboratorio

Simular el compromiso completo de un servidor web corporativo ficticio ("Kalinasa"), demostrando una cadena de ataque real de principio a fin:

**Reconocimiento → Descubrimiento de directorios → SQL Injection → Extracción de credenciales → Password Cracking → Acceso SSH → Escalación de privilegios**

## Topología del entorno

- **Máquina atacante:** Kali Linux
- **Máquina víctima:** Ubuntu Server (Apache2 + MySQL + OpenSSH)
- Ambas máquinas corren en red interna/host-only dentro de VirtualBox, aisladas de la red externa.

## Vulnerabilidades incluidas

| # | Vulnerabilidad | Clasificación (OWASP / CWE) |
|---|---|---|
| 1 | Exposición de información sensible en comentarios HTML | CWE-540 |
| 2 | SQL Injection (UNION-based) | OWASP A03:2021 - Injection |
| 3 | Almacenamiento de contraseñas en texto plano | CWE-256 |
| 4 | Contraseñas débiles / reutilizadas | OWASP A07:2021 |
| 5 | Configuración insegura de `sudo` (binario SUID sin restricción) | CWE-250 |

---

## Paso a paso

### 1. Reconocimiento inicial

Arrancamos identificando la dirección IP de la máquina objetivo.

<img width="1317" height="607" alt="image" src="https://github.com/user-attachments/assets/70053dcc-c049-42a6-9653-2b977acd4dc8" />

La IP de la máquina objetivo es `192.168.56.107`. Ahora hacemos un escaneo con `nmap` para identificar puertos abiertos y servicios corriendo.

Primero un escaneo básico y silencioso para ver qué puertos están abiertos, y después profundizamos en esos puertos específicos.

<img width="953" height="734" alt="image" src="https://github.com/user-attachments/assets/2c236fe1-c2eb-4504-a9e6-89766cf2ff30" />

Descubrimos que los puertos **22, 80 y 3306** están abiertos. Ahora hacemos un escaneo de servicios y scripts para identificar versiones.

<img width="636" height="53" alt="image" src="https://github.com/user-attachments/assets/d57f5a72-9d05-496a-b5d5-012a11ce2544" />

<img width="1599" height="734" alt="image" src="https://github.com/user-attachments/assets/ed7ccac5-d9c5-439c-b038-03376c108a58" />

Confirmamos que en el puerto 3306 está corriendo MySQL. Con esto ya sabemos que probablemente exista una aplicación web conectada a una base de datos — vamos a revisar el sitio.

### 2. Exploración del sitio web

<img width="1600" height="900" alt="image" src="https://github.com/user-attachments/assets/0479ec8b-42ef-4a91-b55c-6e7f45c9cc17" />

Encontramos un portal de noticias internas de la empresa ficticia "Kalinasa". Al inspeccionar el código fuente de la página, se filtra un usuario de prueba llamado **juan** en un comentario HTML que debería haberse eliminado antes de pasar a producción.

<img width="1599" height="517" alt="image" src="https://github.com/user-attachments/assets/9df96767-e1b6-4b7b-bb0d-99459eb85bb2" />

Este tipo de descuido (dejar información sensible en comentarios del código fuente) es una vulnerabilidad real y común.

### 3. Descubrimiento de directorios y archivos ocultos

La página principal no tiene ningún enlace visible hacia otras funcionalidades, así que corremos un escaneo de directorios/archivos para buscar endpoints ocultos, probando extensiones como `.php` y `.html`.

<img width="857" height="474" alt="image" src="https://github.com/user-attachments/assets/229af54b-8e34-4588-a812-95654b8c59cb" />

Encontramos el archivo **`buscar.php`**.

<img width="1328" height="679" alt="image" src="https://github.com/user-attachments/assets/12ab9a02-bbae-408a-98fc-1dfb9bc26884" />

Por el nombre, se puede deducir que implementa algún tipo de búsqueda. Al acceder sin parámetros, la página indica que no encontró resultados. Como no hay ningún formulario o botón interactivo visible, asumimos que los parámetros deben enviarse directamente por la URL (método GET).

### 4. Fuzzing de parámetros

Para descubrir el nombre exacto del parámetro que acepta `buscar.php`, usamos `ffuf`, aprovechando el nombre de usuario **juan** que encontramos filtrado en el paso anterior como valor de prueba.

<img width="1599" height="541" alt="image" src="https://github.com/user-attachments/assets/05bf51b9-bda4-40f1-8b75-c15e67831b3e" />

El resultado inicial muestra que todos los parámetros de la wordlist devuelven una respuesta del mismo tamaño (454 bytes) — esto indica que ninguno de esos "coincide" realmente, y que el servidor responde igual sin importar el parámetro enviado (comportamiento por defecto de "sin resultados"). Filtramos ese tamaño con el flag `-fs 454` para quedarnos solo con las respuestas que se diferencien.

<img width="1599" height="437" alt="image" src="https://github.com/user-attachments/assets/e7dee2eb-f3b3-4e39-afa1-cbcf463903c3" />

Con el filtro aplicado, encontramos el parámetro **`nombre`** como el que efectivamente devuelve resultados distintos.

<img width="1599" height="383" alt="image" src="https://github.com/user-attachments/assets/8449bba8-a2e7-4262-83e3-2e5659b9b15f" />

Confirmamos que el usuario **juan** está cargado en la base de datos, accesible a través del parámetro `nombre`.

### 5. Detección de SQL Injection

Con el parámetro identificado, probamos si es vulnerable a inyección SQL usando una prueba de Blind SQL Injection con condiciones booleanas:

```
http://192.168.56.107/buscar.php?nombre=juan' and 1=1 -- -
```

Esto devuelve el mismo resultado que una búsqueda normal (la condición es verdadera). En cambio, al probar con `1=0`:

```
http://192.168.56.107/buscar.php?nombre=juan' and 1=0 -- -
```

No devuelve ningún resultado (la condición es falsa). Esta diferencia de comportamiento confirma que el parámetro `nombre` **es vulnerable a inyección SQL**. También identificamos en este punto que la consulta original devuelve dos columnas: usuario y email.

<img width="1599" height="459" alt="image" src="https://github.com/user-attachments/assets/5b280e51-059c-418c-8e7e-27dda7c09ca2" />

Con un payload básico `' OR 1=1 -- -`, logramos que la condición `WHERE` sea siempre verdadera, filtrando toda la tabla de usuarios en un solo request.

### 6. Enumeración de la base de datos vía `information_schema`

Para confirmar la estructura completa de la base de datos (sin depender de suposiciones), listamos primero todas las tablas de la base actual usando la base de metadatos interna de MySQL, `information_schema`:

```
http://192.168.56.107/buscar.php?nombre=' union select table_name, table_schema from information_schema.tables where table_schema=database() -- -
```

Esto revela que la tabla se llama **`users`**, dentro de la base de datos **`kalinasa_db`**.

<img width="1599" height="458" alt="image" src="https://github.com/user-attachments/assets/304c2a0b-ab12-4135-8f39-221028e0ccff" />

Ahora enumeramos las columnas de esa tabla específica:

```
http://192.168.56.107/buscar.php?nombre=' UNION SELECT column_name, data_type FROM information_schema.columns WHERE table_name='users' AND table_schema=database() -- -
```

<img width="885" height="312" alt="image" src="https://github.com/user-attachments/assets/3645eb21-3e31-4b46-aac6-1acabbca4648" />

Esto confirma la existencia de una columna de contraseñas. Con los nombres de columna reales en mano, extraemos usuario y contraseña directamente:

```
http://192.168.56.107/buscar.php?nombre=' UNION SELECT user, password FROM users -- -
```

<img width="947" height="344" alt="image" src="https://github.com/user-attachments/assets/e7ec4bce-9b0f-4f1e-8bc1-d5706c1f4c56" />

Con esto obtenemos todos los usuarios registrados junto con sus contraseñas en texto plano.

### 7. Credenciales en texto plano

A diferencia de lo que uno esperaría en una aplicación real, las contraseñas extraídas de la tabla `users` **no están hasheadas** — se almacenan directamente en texto plano. Esto es una falla de seguridad crítica por sí sola: cualquier acceso de lectura a la base de datos (por SQLi, por un backup filtrado, por un insider malicioso, etc.) expone las credenciales de forma inmediata, sin necesidad de ningún paso adicional de cracking.

Con las credenciales ya en texto plano, el siguiente paso es simplemente confirmar cuál de esos pares usuario/contraseña corresponde a una cuenta real en el sistema operativo.

### 8. Acceso por SSH

Con las credenciales obtenidas, intentamos acceder por SSH a cada usuario encontrado. Varios de ellos, si bien existen en la base de datos con hashes crackeables, no corresponden a cuentas reales del sistema operativo — por ejemplo, el usuario `cruiz` es rechazado:

<img width="377" height="190" alt="image" src="https://github.com/user-attachments/assets/4eb08e1a-f5e8-4faf-a0b5-b40b0a0b2960" />

Esto demuestra un punto metodológico importante: **no hay que asumir que una credencial válida en la base de datos implica acceso al sistema**; cada una debe confirmarse individualmente.

Al probar con el usuario **`agomez`**, el acceso es exitoso:

<img width="769" height="511" alt="image" src="https://github.com/user-attachments/assets/ec9ecca8-c9e5-4e79-8ef2-1640fc784784" />

Una vez dentro, accedemos al directorio de inicio del usuario y capturamos la flag de usuario.

<img width="386" height="87" alt="image" src="https://github.com/user-attachments/assets/06798692-b8c6-4a40-846c-5cf70c7ef6a9" />

### 9. Escalación de privilegios

Para buscar un camino de escalación, revisamos qué comandos puede ejecutar el usuario actual como root sin restricciones, usando `sudo -l`:

<img width="566" height="64" alt="image" src="https://github.com/user-attachments/assets/a23747c4-85a5-4bd4-8bca-d4d0f19b6744" />

Encontramos que el usuario tiene permiso para ejecutar el binario **`nano`** como root sin contraseña. `nano` es un editor de texto que, al correr con privilegios elevados, permite ejecutar comandos externos desde su propio menú — una técnica de escalación bien documentada en [GTFOBins](https://gtfobins.github.io/gtfobins/nano/).

El proceso es el siguiente:

```bash
sudo /usr/bin/nano
```

Dentro del editor, usamos `Ctrl+R` seguido de `Ctrl+X`, que abre el prompt de "ejecutar comando externo" de nano. Ahí introducimos:

```
reset; sh 1>&0 2>&0
```

Al presionar Enter, no parece pasar nada de inmediato, pero si seguimos presionando Enter, el editor se "cierra" y queda una shell activa con los privilegios heredados de `sudo` (root). Confirmamos con `whoami`.

<img width="1599" height="59" alt="image" src="https://github.com/user-attachments/assets/b8a3c63a-495e-47e9-b6b1-5499302d7f74" />

Con acceso root confirmado, navegamos al directorio de root y capturamos la flag final.

<img width="1599" height="322" alt="image" src="https://github.com/user-attachments/assets/b2fbb908-856b-4553-963b-4427ef306919" />

---

## Resumen de la cadena de ataque

1. Reconocimiento de puertos con `nmap` (22, 80, 3306)
2. Exposición de credencial de prueba en comentario HTML
3. Descubrimiento de `buscar.php` mediante fuzzing de directorios
4. Descubrimiento del parámetro `nombre` mediante fuzzing de parámetros con `ffuf`
5. Confirmación de SQL Injection (Blind + UNION-based)
6. Enumeración de esquema de base de datos vía `information_schema`
7. Extracción de credenciales (usuario + contraseña en texto plano)
8. Acceso por SSH con credenciales válidas
9. Escalación de privilegios explotando permiso `sudo` mal configurado sobre `nano`

## Mitigaciones recomendadas

| Vulnerabilidad | Recomendación |
|---|---|
| SQL Injection | Usar *prepared statements* (consultas parametrizadas) en vez de concatenar input del usuario directamente en la query |
| Información sensible en comentarios | Eliminar comentarios de desarrollo/testing antes de pasar a producción; usar revisión de código previa al deploy |
| Contraseñas en texto plano | Nunca almacenar contraseñas sin hashear. Usar algoritmos diseñados para contraseñas, como `bcrypt`, `scrypt` o `Argon2`, con salt único por usuario |
| Contraseñas débiles | Forzar políticas de complejidad y longitud mínima; evaluar autenticación multifactor |
| `sudo` mal configurado | Evitar otorgar privilegios de `sudo` sobre binarios que permitan escape a shell (ver referencia GTFOBins antes de autorizar binarios); aplicar el principio de mínimo privilegio |

---

Este fue el proceso completo de compromiso de la máquina "Kalinasa", diseñada y explotada de forma independiente como proyecto de práctica en pentesting web y Linux privilege escalation.
