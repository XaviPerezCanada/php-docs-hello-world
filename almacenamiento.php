<?php

require 'vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceExceptions;

// Configuración de seguridad para producción
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Configuración
$connectionString = getenv("AZURE_STORAGE_CONNECTION_STRING");
$containerName = "comprimits";
$maxFileSize = 100 * 1024 * 1024; // 100 MB máximo

if (!$connectionString) {
    http_response_code(500);
    die("Error de configuración: La variable AZURE_STORAGE_CONNECTION_STRING no está configurada.");
}

try {
    $blobClient = BlobRestProxy::createBlobService($connectionString);
    
    // Verificar si el contenedor existe, si no, crearlo
    try {
        $blobClient->getContainerProperties($containerName);
    } catch (Exception $e) {
        // Si el contenedor no existe, crearlo
        $blobClient->createContainer($containerName);
    }
} catch (Exception $e) {
    http_response_code(500);
    die("Error al conectar con Azure Storage: " . htmlspecialchars($e->getMessage()));
}

// Función para validar nombre de archivo
function sanitizeFileName($filename) {
    $filename = basename($filename);
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    return $filename;
}

// Función para validar tipo MIME (alternativa si mime_content_type no está disponible)
function validateZipFile($filePath) {
    if (function_exists('mime_content_type')) {
        $mime = mime_content_type($filePath);
        if ($mime === 'application/zip' || $mime === 'application/x-zip-compressed') {
            return true;
        }
    }
    
    // Validación alternativa por extensión y contenido
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mime = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        if ($mime === 'application/zip' || $mime === 'application/x-zip-compressed') {
            return true;
        }
    }
    
    // Validación por extensión como último recurso
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    return $extension === 'zip';
}

// Descargar archivo si se solicita
if (isset($_GET['download_blob'])) {
    $blobName = sanitizeFileName($_GET['download_blob']);
    if (empty($blobName)) {
        http_response_code(400);
        die("Nombre de archivo inválido.");
    }
    
    try {
        $blob = $blobClient->getBlob($containerName, $blobName);
        $content = stream_get_contents($blob->getContentStream());

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . htmlspecialchars(basename($blobName)) . '"');
        header('Content-Length: ' . strlen($content));

        echo $content;
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo "Error al descargar el archivo.";
        exit;
    }
}

$uploadMessage = '';
$uploadError = '';

// Eliminar archivo si se envió solicitud
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_blob'])) {
    $blobName = sanitizeFileName($_POST['delete_blob']);
    if (!empty($blobName)) {
        try {
            $blobClient->deleteBlob($containerName, $blobName);
            $uploadMessage = "Archivo eliminado correctamente.";
        } catch (Exception $e) {
            $uploadError = "Error al eliminar el archivo.";
        }
    } else {
        $uploadError = "Nombre de archivo inválido.";
    }
}

// Subir archivo nuevo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['zipfile'])) {
    $file = $_FILES['zipfile'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadError = "Error al subir el archivo.";
    } elseif ($file['size'] > $maxFileSize) {
        $uploadError = "El archivo es demasiado grande. Tamaño máximo: " . ($maxFileSize / 1024 / 1024) . " MB.";
    } elseif (!validateZipFile($file['tmp_name'])) {
        $uploadError = "Solo se permiten archivos .zip válidos.";
    } else {
        $blobName = sanitizeFileName($file['name']);
        if (empty($blobName)) {
            $uploadError = "Nombre de archivo inválido.";
        } else {
            try {
                $content = fopen($file['tmp_name'], 'r');
                $blobClient->createBlockBlob($containerName, $blobName, $content);
                fclose($content);
                $uploadMessage = "Archivo subido correctamente: " . htmlspecialchars($blobName);
            } catch (Exception $e) {
                $uploadError = "Error al subir el archivo.";
            }
        }
    }
}

// Listar blobs
try {
    $blobList = $blobClient->listBlobs($containerName, new ListBlobsOptions());
    $blobs = $blobList->getBlobs();
} catch (Exception $e) {
    http_response_code(500);
    die("Error al listar archivos.");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestor de archivos ZIP en Azure Blob</title>
</head>
<body>
    <h1>Archivos ZIP en '<?= htmlspecialchars($containerName) ?>'</h1>

    <?php if ($uploadMessage): ?>
        <p style='color:green;'><?= $uploadMessage ?></p>
    <?php endif; ?>
    
    <?php if ($uploadError): ?>
        <p style='color:red;'><?= $uploadError ?></p>
    <?php endif; ?>

    <ul>
    <?php if (empty($blobs)): ?>
        <li>No hay archivos ZIP.</li>
    <?php else: ?>
        <?php foreach ($blobs as $blob): ?>
            <li>
                <a href="?download_blob=<?= urlencode($blob->getName()) ?>" target="_blank">
                    <?= htmlspecialchars($blob->getName()) ?>
                </a>
                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar <?= htmlspecialchars($blob->getName()) ?>?')">
                    <input type="hidden" name="delete_blob" value="<?= htmlspecialchars($blob->getName()) ?>">
                    <button type="submit" style="color:red;">Eliminar</button>
                </form>
            </li>
        <?php endforeach; ?>
    <?php endif; ?>
    </ul>

    <h2>Subir nuevo archivo ZIP</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="zipfile" accept=".zip" required>
        <button type="submit">Subir</button>
    </form>
</body>
</html>