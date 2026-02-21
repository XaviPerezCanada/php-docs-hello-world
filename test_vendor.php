<?php
if (file_exists('vendor/autoload.php')) {
    echo "Carpeta VENDOR encontrada ✅";
} else {
    echo "Carpeta VENDOR NO ENCONTRADA ❌. El despliegue de GitHub falló y no instaló las librerías.";
}