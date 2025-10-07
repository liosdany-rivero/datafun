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

