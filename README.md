# Laboratorio-vulnerable
Un laboratorio creado de manera independiente, agregando las vulnerabilidades como en un CTF para que pueda ser explotado. En este repositorio doy el paso a paso de como explotar este laboratorio con sus respectivas pruebas.

#Paso a paso

Vamos a arrancar tomando la dirección ip de nuestra maquina objetivo para poder empezar a recopilar información sobre nuestra maquina victima.

<img width="1317" height="607" alt="image" src="https://github.com/user-attachments/assets/70053dcc-c049-42a6-9653-2b977acd4dc8" />

vemos que la direccion ip de nuestra maquina objetivo es de 192.158.56.107. Ahora vamos a hacerle un escaneo con nmap para ver que puertos estan abiertos y qeu servicios se estan corriendo en la maquina.

Primero hacemos un escaneo basico y silencioso para ver que puertos se estan corriendo y luego desarrollamos mas los puertos que esten abiertos.

<img width="953" height="734" alt="image" src="https://github.com/user-attachments/assets/2c236fe1-c2eb-4504-a9e6-89766cf2ff30" />

Descubrimos que el puerto 22,80 y 3306 estan corriendo dentro de esta maquina, ahora hagamos un escaneo de servicios y scripts para saber que servicios y versiones estan corriendo.

<img width="636" height="53" alt="image" src="https://github.com/user-attachments/assets/d57f5a72-9d05-496a-b5d5-012a11ce2544" />

<img width="1599" height="734" alt="image" src="https://github.com/user-attachments/assets/ed7ccac5-d9c5-439c-b038-03376c108a58" />

Vemos que en el puerto 3306 esta corriendo mysql. Vamos a ir a la web a ver que nos encontramos.

<img width="1600" height="900" alt="image" src="https://github.com/user-attachments/assets/0479ec8b-42ef-4a91-b55c-6e7f45c9cc17" />


En la pagina encontramos este portal de noticias de la empresa Kalinasa que es como una web de noticias interna, si vemos el codigo fuente se lekea un user de test que se llama juan.

<img width="1599" height="517" alt="image" src="https://github.com/user-attachments/assets/9df96767-e1b6-4b7b-bb0d-99459eb85bb2" />

Vamos a hacer un escaneo de directorios para ver si hay otros directorios ocultos. Buscamos con algunas de las extensiones como php o html para ver que encuentra

<img width="857" height="474" alt="image" src="https://github.com/user-attachments/assets/229af54b-8e34-4588-a812-95654b8c59cb" />

Encontramos un directorio que se llama buscar.php.

<img width="1328" height="679" alt="image" src="https://github.com/user-attachments/assets/12ab9a02-bbae-408a-98fc-1dfb9bc26884" />



Por su nombre se puede deducir que algun comando de busqueda tiene, al parecer si buscamos sin ningun parametro nos tira que no encontro nada y al no tener ningun boton interactivo se supone que hay que mandar los parametros por url. Asi que vamos a verificar que comando mandar ffuf usando el usuario juan que nos dejaron en el codigo fuente de la otra pagina.


<img width="1599" height="541" alt="image" src="https://github.com/user-attachments/assets/05bf51b9-bda4-40f1-8b75-c15e67831b3e" />

Vemos que nos aparecen todos los parametros, por lo tanto necesitamos filtrar los que no queremos o no nos sirvan. Entonces vemos que se podria filtrar por tamano porque todos nos dan 454 bytes. Agregamos el flag -fs 454.

<img width="1599" height="437" alt="image" src="https://github.com/user-attachments/assets/e7dee2eb-f3b3-4e39-afa1-cbcf463903c3" />

Ahi pudimos encontrar el parametro nombre para poder buscar usuarios dentro de la pagina web.

<img width="1599" height="383" alt="image" src="https://github.com/user-attachments/assets/8449bba8-a2e7-4262-83e3-2e5659b9b15f" />

Ahi pudimos encontrar que el usuario juan esta cargado en la base de datos pasandolo por el parametro nombre. Vamos a ver si este parametro es vulnerable a inyeccion SQL mediante Blind SQL injection.
Primero probemos con un parametro booleano verdadero y luego otro falso.

http://192.168.56.107/buscar.php?nombre=juan%27%20and%201=1%20--%20- Esto nos devuelve lo mismo que en la otra imagen pero si le pasamos 1=0 no pasa nada, con esto podemos ver que este parametro si es vulneable a iyeccion SQL por lo tanto ahora vamos a ver si podemos sacar el resto de users. Por ahora podemos ver que la tabla tiene dos columnas user y email.

<img width="1599" height="459" alt="image" src="https://github.com/user-attachments/assets/5b280e51-059c-418c-8e7e-27dda7c09ca2" />

Aca poniendo un payload basico OR 1=1 -- - y comentando todo el resto podemos ver que se nos lekea toda la base de datos, vamos a ver si son solo estas columnas o hay mas.

Arranquemos por listar todas las tablas de la base de datos actual.

http://192.168.56.107/buscar.php?nombre=%27%20union%20select%20table_name,%20table_schema%20from%20information_schema.tables%20where%20table_schema=database()%20--%20-

Con este payload nos muestras el nombre de la tabla que es users y el nombre de la base de datos que se llama kaliansa_db

<img width="1599" height="458" alt="image" src="https://github.com/user-attachments/assets/304c2a0b-ab12-4135-8f39-221028e0ccff" />

Ahora vamos a correr este payload para saber cuales son todas las columnas que tenemos en esta base de datos

http://192.168.56.107/buscar.php?nombre=' UNION SELECT column_name, data_type FROM information_schema.columns WHERE table_name='users' AND table_schema=database()-- -

<img width="885" height="312" alt="image" src="https://github.com/user-attachments/assets/3645eb21-3e31-4b46-aac6-1acabbca4648" />

Esto nos muestra que hay una columna de contrasenas tmb entonces vamos a seleccionar las columnas que nos interesan que son las de usuario y contrasena entonces vamos a mandar:

http://192.168.56.107/buscar.php?nombre=' UNION SELECT user, password FROM users -- -

<img width="947" height="344" alt="image" src="https://github.com/user-attachments/assets/e7ec4bce-9b0f-4f1e-8bc1-d5706c1f4c56" />

Ahora si esto nos muetra todos los usuarios y contrasenas, ahora habria que ir al puerto 22 de ssh y entrar con alguno de estos usuarios. Al momento de entrar vamos a ver que algunos de los usuarios nos va a denegar el permiso hasta que nos topemos con el user agomez que va a ser el correcto. Por ejemplo en el user cruiz que no nos permite.


<img width="377" height="190" alt="image" src="https://github.com/user-attachments/assets/4eb08e1a-f5e8-4faf-a0b5-b40b0a0b2960" />

<img width="769" height="511" alt="image" src="https://github.com/user-attachments/assets/ec9ecca8-c9e5-4e79-8ef2-1640fc784784" />

Ahora que estamos dentro de ana podemos ir a su directorio de inicio y reclamar el flag de user.

<img width="386" height="87" alt="image" src="https://github.com/user-attachments/assets/06798692-b8c6-4a40-846c-5cf70c7ef6a9" />

Ahora vamos a buscar la manera de escalar privilegios, usamos sudo -l para ver si hay algun binario que corra como root.

<img width="566" height="64" alt="image" src="https://github.com/user-attachments/assets/a23747c4-85a5-4bd4-8bca-d4d0f19b6744" />

Vemos que puede correr el binario nano como root asi que vamos a probar escalar privilegio por ahi.

Mandamos sudo /usr/bin/nano. Ahi dentro vamos a usar el comando CTRL+R y luego CTRL+X para poder ejecutar comandos. Entonces mandamos el comando reset; sh 1>&0 2>&0. Apretamos enter y va a parecer como que no paso nada pero si seguimos apretando enter vamos a ver que el editor empieza a subir, ya tenemso shell en root, corremos whoami y veremos que somos root. Entonces vamos al dir de root y reclamamos la flag de root.

<img width="1599" height="59" alt="image" src="https://github.com/user-attachments/assets/b8a3c63a-495e-47e9-b6b1-5499302d7f74" />


<img width="1599" height="322" alt="image" src="https://github.com/user-attachments/assets/b2fbb908-856b-4553-963b-4427ef306919" />


Bueno este el fin de la maquina kalinasa, espero que les haya gustado este write-up. Saludos





























