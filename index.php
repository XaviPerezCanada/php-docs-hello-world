<?php
// Prueba de ruta absoluta
$autoloadPath = __DIR__ . '/vendor/autoload.php';

if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
    echo "¡Autoload cargado con éxito! <br>";
} else {
    die("ERROR: No se encuentra el archivo en: " . $autoloadPath);
}

// Intentar usar una clase de Azure
if (class_exists('MicrosoftAzure\Storage\Blob\BlobRestProxy')) {
    echo "La clase BlobRestProxy está disponible.";
} else {
    echo "Error: La librería de Azure no se ha cargado correctamente.";
}
?>