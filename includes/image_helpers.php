<?php
function optimizarImagen($rutaOriginal) {
    $info = getimagesize($rutaOriginal);
    if (!$info) return false;
    
    $mime = $info['mime'];
    
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($rutaOriginal);
            break;
        case 'image/png':
            $image = imagecreatefrompng($rutaOriginal);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($rutaOriginal);
            break;
        default:
            return false;
    }
    
    if (!$image) return false;
    
    // Redimensionar si es muy grande
    $maxWidth = 800; // Reducido para mejor rendimiento
    $maxHeight = 800; // Reducido para mejor rendimiento
    
    $width = imagesx($image);
    $height = imagesy($image);
    
    if ($width > $maxWidth || $height > $maxHeight) {
        $ratio = min($maxWidth/$width, $maxHeight/$height);
        $new_width = round($width * $ratio);
        $new_height = round($height * $ratio);
        
        $new_image = imagecreatetruecolor($new_width, $new_height);
        
        // Mantener transparencia para todas las imágenes al convertir a WebP
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        
        imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        imagedestroy($image);
        $image = $new_image;
    }
    
    // Generar la nueva ruta para el archivo WebP
    $rutaWebp = pathinfo($rutaOriginal, PATHINFO_DIRNAME) . '/' . 
                pathinfo($rutaOriginal, PATHINFO_FILENAME) . '.webp';
    
    // Guardar siempre como WebP optimizado
    $webpGuardado = imagewebp($image, $rutaWebp, 80);
    
    // Liberar memoria
    imagedestroy($image);
    
    // Flag para saber si se convirtió de formato
    $convertido = false;
    
    // Si el archivo original no era WebP y la conversión fue exitosa, eliminamos el original
    if ($mime !== 'image/webp' && file_exists($rutaWebp) && filesize($rutaWebp) > 0) {
        if (file_exists($rutaOriginal)) {
            unlink($rutaOriginal);
            $convertido = true;
        }
    }
    
    // Devolvemos un array con toda la información
    return [
        'rutaOriginal' => $rutaOriginal,
        'rutaWebp' => $rutaWebp,
        'convertido' => $convertido,
        'exito' => $webpGuardado
    ];
} 