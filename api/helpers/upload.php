<?php
function uploadFile($file, $dir, $allowedMime, $maxSize) {
    if (!$file || $file['error'] !== 0) return null;
    if ($file['size'] > $maxSize) return null;

    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowedMime)) return null;

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = uniqid().".".$ext;

    $diskPath   = "../../uploads/$dir/".$name;
    $publicPath = "/uploads/$dir/".$name;

    move_uploaded_file($file['tmp_name'], $diskPath);

    return $publicPath;
}

function deleteFile($publicPath) {
    if (!$publicPath) return;

    // convert /uploads/images/x.jpg → ../../uploads/images/x.jpg
    $diskPath = "../../" . ltrim($publicPath, "/");

    if (file_exists($diskPath)) {
        unlink($diskPath);
    }
}