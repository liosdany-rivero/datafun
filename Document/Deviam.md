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

