## Funcionamiento interno de una computadora: las diferentes capas involucradas

1. Hardware
    * Motherboard 
    * Procesador
    * RAM
    * Controlafores de dispositivos 
        * IDE
        * SCSI
        * SATA
        * USB
        * IEEE 1394 (firewire)
2. Arranque: la BIOS o UEFI

Esta simbiosis entre hardware y software no ocurre por sí sola. Cuando el ordenador se enciende por primera vez, se requiere una configuración inicial. Esta función la asume la BIOS o UEFI, un software integrado en la placa base que se ejecuta automáticamente al encenderse. Su tarea principal es buscar software al que pueda ceder el control.

En el caso de la BIOS, esto implica buscar el primer disco duro con un sector de arranque (también conocido como registro de arranque maestro o MBR ), cargar ese sector de arranque y ejecutarlo. A partir de ese momento, la BIOS no suele intervenir (hasta el siguiente arranque). En el caso de la UEFI, el proceso implica escanear los discos para encontrar una partición EFI dedicada que contenga más aplicaciones EFI para ejecutar.

Setup: La BIOS/UEFI también contiene un programa llamado Setup, diseñado para configurar aspectos del ordenador. En particular, permite elegir el dispositivo de arranque preferido (por ejemplo, se puede seleccionar una memoria USB o una unidad de CD-ROM en lugar del disco duro predeterminado), configurar el reloj del sistema, etc.


El sector de arranque (o partición EFI), a su vez, contiene otro software, llamado gestor de arranque, cuya función es encontrar y ejecutar un sistema operativo. Dado que este gestor de arranque no está integrado en la placa base, sino que se carga desde el disco, puede ser más inteligente que la BIOS, lo que explica por qué la BIOS no carga el sistema operativo por sí sola. Por ejemplo, el gestor de arranque (a menudo GRUB en sistemas Linux) puede listar los sistemas operativos disponibles y pedir al usuario que elija uno. Normalmente, se proporciona un tiempo de espera y una opción predeterminada. En ocasiones, el usuario también puede añadir parámetros para pasar al kernel, y así sucesivamente. Finalmente, se encuentra un kernel, se carga en memoria y se ejecuta.

La BIOS/UEFI también se encarga de detectar e inicializar varios dispositivos. Obviamente, esto incluye los dispositivos IDE/SATA (normalmente discos duros y unidades de CD/DVD-ROM), pero también los dispositivos PCI. Los dispositivos detectados suelen aparecer en pantalla durante el arranque.

3. El núcleo

Tanto la BIOS/UEFI como el gestor de arranque solo se ejecutan durante unos segundos cada uno; ahora llegamos al primer componente de software que se ejecuta durante más tiempo: el kernel del sistema operativo. Este kernel asume el papel de director de orquesta y garantiza la coordinación entre el hardware y el software. Esta función implica varias tareas, entre ellas: controlar el hardware, gestionar procesos, usuarios y permisos, el sistema de archivos, etc. El kernel proporciona una base común para todos los demás programas del sistema.

4. El espacio del usuario

Aunque todo lo que ocurre fuera del núcleo puede agruparse bajo el "espacio de usuario", aún podemos separarlo en capas de software. Sin embargo, sus interacciones son más complejas que antes, y las clasificaciones pueden no ser tan sencillas. Una aplicación suele usar bibliotecas, que a su vez involucran al núcleo, pero las comunicaciones también pueden involucrar a otros programas, o incluso a muchas bibliotecas que se llaman entre sí.

## Tareas manajadas por el núcleo 

1. Control de hardware.
2. Sistemas de archivos.
3. Funciones compartidas.
4. Gestión de procesos.

Un proceso es una instancia en ejecución de un programa. Requiere memoria para almacenar tanto el programa como sus datos operativos. El núcleo se encarga de crearlos y registrarlos. Cuando un programa se ejecuta, el núcleo primero reserva memoria, luego carga el código ejecutable desde el sistema de archivos y, finalmente, inicia la ejecución del código. Guarda información sobre este proceso, la más visible de las cuales es un número de identificación conocido como PID ( identificador de proceso ).
Los núcleos tipo Unix (incluido Linux), al igual que la mayoría de los sistemas operativos modernos, son capaces de realizar múltiples tareas. En otras palabras, permiten ejecutar varios procesos simultáneamente. En realidad, solo hay un proceso en ejecución, pero el núcleo divide el tiempo en pequeños intervalos y ejecuta cada proceso uno por uno. Dado que estos intervalos son muy cortos (del orden de milisegundos), crean la ilusión de que los procesos se ejecutan en paralelo, aunque en realidad solo están activos durante ciertos intervalos e inactivos el resto del tiempo. La función del núcleo es ajustar sus mecanismos de programación para mantener esta ilusión, a la vez que maximiza el rendimiento global del sistema. Si los intervalos son demasiado largos, la aplicación puede no mostrar la capacidad de respuesta deseada. Si son demasiado cortos, el sistema pierde tiempo cambiando de tarea con demasiada frecuencia. Estas decisiones se pueden ajustar con las prioridades de los procesos. Los procesos de alta prioridad se ejecutarán durante más tiempo y con intervalos más frecuentes que los de baja prioridad.

5. Gestión de derechos

Los sistemas tipo Unix también son multiusuario. Ofrecen un sistema de gestión de derechos que admite usuarios y grupos separados; además, permite el control de acciones según los permisos. El núcleo gestiona los datos de cada proceso, lo que le permite controlar los permisos.

## El espacio del usuario

El «espacio de usuario» se refiere al entorno de ejecución de los procesos normales (a diferencia de los del núcleo). Esto no significa necesariamente que estos procesos sean iniciados por los usuarios, ya que un sistema estándar suele tener varios procesos «daemon» (o en segundo plano) ejecutándose incluso antes de que el usuario abra una sesión. Los procesos daemon también se consideran procesos del espacio de usuario.

1. Proceso


Cuando el núcleo supera su fase de inicialización, inicia el primer proceso. initEl proceso n.º 1 por sí solo rara vez es útil, y los sistemas tipo Unix se ejecutan con muchos procesos adicionales.
En primer lugar, un proceso puede clonarse a sí mismo (esto se conoce como bifurcación ). El núcleo asigna un nuevo espacio de memoria (pero idéntico) al proceso y otro proceso lo utiliza. En este caso, la única diferencia entre estos dos procesos es su pid . El nuevo proceso suele denominarse proceso hijo, y el proceso original, cuyo pid no cambia, se denomina proceso padre.
A veces, el proceso hijo continúa su propia vida independientemente de su padre, con sus propios datos copiados de este. Sin embargo, en muchos casos, este proceso hijo ejecuta otro programa. Con pocas excepciones, su memoria simplemente se reemplaza por la del nuevo programa, y ​​comienza su ejecución. Este es el mecanismo que utiliza el proceso init (con el número de proceso 1) para iniciar servicios adicionales y ejecutar toda la secuencia de inicio. En algún momento, un proceso de initlos hijos inicia una interfaz gráfica para que los usuarios inicien sesión (la secuencia real de eventos se describe con más detalle en la Sección 9.1, “Arranque del sistema” ).
Cuando un proceso finaliza la tarea para la que fue iniciado, finaliza. El núcleo recupera la memoria asignada a este proceso y deja de otorgarle porciones de tiempo de ejecución. Se informa al proceso padre sobre la finalización de su proceso hijo, lo que permite que un proceso espere la finalización de una tarea que le delegó. Este comportamiento es claramente visible en los intérpretes de línea de comandos (conocidos como shells ). Cuando se escribe un comando en un shell, el indicador solo regresa cuando finaliza la ejecución del comando. La mayoría de los shells permiten ejecutar el comando en segundo plano; es simplemente cuestión de agregar un &al final del comando. El indicador se muestra de nuevo inmediatamente, lo que puede causar problemas si el comando necesita mostrar sus propios datos.


2. Demonios

Un "daemon" es un proceso que se inicia automáticamente con la secuencia de arranque. Se ejecuta continuamente (en segundo plano) para realizar tareas de mantenimiento o prestar servicios a otros procesos. Esta "tarea en segundo plano" es arbitraria y no se corresponde con nada específico desde la perspectiva del sistema. Son simplemente procesos, muy similares a otros procesos, que se ejecutan por turnos cuando llega su intervalo de tiempo. La distinción es solo en lenguaje humano: un proceso que se ejecuta sin interacción con el usuario (en particular, sin interfaz gráfica) se dice que se ejecuta "en segundo plano" o "como un daemon".


3. Comunicaciones entre procesos

Un proceso aislado, ya sea un demonio o una aplicación interactiva, rara vez es útil por sí solo. Por ello, existen varios métodos que permiten que procesos separados se comuniquen entre sí, ya sea para intercambiar datos o para controlarse mutuamente. El término genérico que se refiere a esto es comunicación entre procesos , o IPC.
El sistema IPC más sencillo consiste en usar archivos. El proceso que desea enviar datos los escribe en un archivo (con un nombre conocido de antemano), mientras que el destinatario solo tiene que abrirlo y leer su contenido.
Si no desea almacenar datos en disco, puede usar una tubería (pipe) , que es simplemente un objeto con dos extremos; los bytes escritos en un extremo se pueden leer en el otro. Si los extremos están controlados por procesos separados, se crea un canal de comunicación entre procesos simple y conveniente. Las tuberías se pueden clasificar en dos categorías: tuberías con nombre y tuberías anónimas. Una tubería con nombre se representa mediante una entrada en el sistema de archivos (aunque los datos transmitidos no se almacenan allí), por lo que ambos procesos pueden abrirla de forma independiente si se conoce de antemano su ubicación. En los casos en que los procesos que se comunican están relacionados (por ejemplo, un proceso padre y su proceso hijo), el proceso padre también puede crear una tubería anónima antes de bifurcarse, y el hijo la hereda. Ambos procesos podrán entonces intercambiar datos a través de la tubería sin necesidad del sistema de archivos.

Sin embargo, no todas las comunicaciones entre procesos se utilizan para transferir datos. En muchas situaciones, la única información que se necesita transmitir son mensajes de control como "pausar ejecución" o "reanudar ejecución". Unix (y Linux) proporciona un mecanismo conocido como señales , mediante el cual un proceso puede simplemente enviar una señal específica (seleccionada de una lista predefinida de señales) a otro proceso. El único requisito es conocer el PID del objetivo.
Para comunicaciones más complejas, también existen mecanismos que permiten a un proceso acceder o compartir parte de su memoria asignada con otros procesos. La memoria ahora compartida entre ellos puede utilizarse para transferir datos entre ellos.
Por último, las conexiones de red también pueden ayudar a los procesos a comunicarse; estos procesos pueden incluso ejecutarse en diferentes computadoras, posiblemente a miles de kilómetros de distancia.
Es bastante estándar para un sistema típico tipo Unix hacer uso de todos estos mecanismos en diversos grados.

4. Bibliotecas

Las bibliotecas de funciones desempeñan un papel crucial en un sistema operativo tipo Unix. No son programas propiamente dichos, ya que no pueden ejecutarse por sí solas, sino colecciones de fragmentos de código que pueden ser utilizados por programas estándar. Entre las bibliotecas comunes se encuentran:
la biblioteca C estándar ( glibc ), que contiene funciones básicas como las que permiten abrir archivos o conexiones de red, y otras que facilitan las interacciones con el núcleo;
kits de herramientas gráficas, como Gtk+ y Qt, que permiten a muchos programas reutilizar los objetos gráficos que proporcionan;
la biblioteca libpng , que permite cargar, interpretar y guardar imágenes en formato PNG.
Gracias a estas bibliotecas, las aplicaciones pueden reutilizar código existente. El desarrollo de aplicaciones se simplifica, ya que muchas aplicaciones pueden reutilizar las mismas funciones. Con bibliotecas a menudo desarrolladas por diferentes personas, el desarrollo global del sistema se acerca más a la filosofía histórica de Unix.