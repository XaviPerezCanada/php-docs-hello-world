<?php
// 1. Errores visibles para debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';

// IMPORTANTE: Se necesitan ambas clases
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;

// 2. Conexión robusta
$connectionString = $_SERVER['AZURE_STORAGE_CONNECTION_STRING'] ?? getenv("AZURE_STORAGE_CONNECTION_STRING");
$containerName = "comprimits";

if (!$connectionString) {
    die("Error: La variable AZURE_STORAGE_CONNECTION_STRING no está configurada en Azure.");
}

try {
    $blobClient = BlobRestProxy::createBlobService($connectionString);
} catch (Exception $e) {
    die("Error al conectar con Azure: " . $e->getMessage());
}

// --- LÓGICA DE DESCARGA ---
if (isset($_GET['download_blob'])) {
    $blobName = $_GET['download_blob'];
    try {
        $blob = $blobClient->getBlob($containerName, $blobName);
        $content = stream_get_contents($blob->getContentStream());

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($blobName) . '"');
        echo $content;
        exit;
    } catch (Exception $e) {
        die("Error al descargar: " . $e->getMessage());
    }
}

// --- LÓGICA DE ELIMINACIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_blob'])) {
    try {
        $blobClient->deleteBlob($containerName, $_POST['delete_blob']);
        header("Location: " . $_SERVER['PHP_SELF'] . "?msg=eliminado");
        exit;
    } catch (Exception $e) {
        $error = "Error al eliminar: " . $e->getMessage();
    }
}

// --- LÓGICA DE SUBIDA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['zipfile'])) {
    $file = $_FILES['zipfile'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $blobName = basename($file['name']);
        try {
            $content = fopen($file['tmp_name'], 'r');
            $blobClient->createBlockBlob($containerName, $blobName, $content);
            header("Location: " . $_SERVER['PHP_SELF'] . "?msg=subido");
            exit;
        } catch (Exception $e) {
            $error = "Error al subir: " . $e->getMessage();
        }
    }
}

// --- LISTAR BLOBS ---
try {
    // Aquí es donde fallaba por falta del 'use' arriba
    $blobList = $blobClient->listBlobs($containerName, new ListBlobsOptions());
    $blobs = $blobList->getBlobs();
} catch (Exception $e) {
    die("Error al listar los archivos del contenedor: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestor Azure Blob</title>
    <style>
        body { font-family: sans-serif; padding: 20px; line-height: 1.6; }
        .item { margin-bottom: 10px; padding: 10px; border-bottom: 1px solid #eee; }
        .msg { color: green; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Archivos en '<?= htmlspecialchars($containerName) ?>'</h1>

    <?php if(isset($_GET['msg'])): ?>
        <p class="msg">Acción realizada con éxito.</p>
    <?php endif; ?>

    <?php if(isset($error)): ?>
        <p style="color:red;"><?= $error ?></p>
    <?php endif; ?>

    <div>
        <?php if (empty($blobs)): ?>
            <p>No hay archivos en el contenedor.</p>
        <?php else: ?>
            <?php foreach ($blobs as $blob): ?>
                <div class="item">
                    <strong><?= htmlspecialchars($blob->getName()) ?></strong> 
                    <a href="?download_blob=<?= urlencode($blob->getName()) ?>">[Descargar]</a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Borrar?')">
                        <input type="hidden" name="delete_blob" value="<?= htmlspecialchars($blob->getName()) ?>">
                        <button type="submit" style="color:red; cursor:pointer;">Eliminar</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <hr>
    <h2>Subir nuevo archivo</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="zipfile" required>
        <button type="submit">Subir a Azure</button>
    </form>
</body>
</html>