<?php
// --- CONFIGURATION ---
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 1. Chemins Absolus
$dbFile = __DIR__ . '/../db/blogs.json';
$uploadBaseDir = __DIR__ . '/../../'; // Racine du projet pour supprimer les vieux fichiers
$uploadTargetDir = __DIR__ . '/../../uploads/'; // Dossier où mettre les nouveaux

// Création du dossier si inexistant
if (!is_dir($uploadTargetDir)) mkdir($uploadTargetDir, 0777, true);

// --- FONCTIONS INTERNES ---

// A. Auth (Récupérer ID User via Token)
function getUserIdFromToken() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $parts = explode('.', $matches[1]);
        if (count($parts) === 3) {
            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
            return $payload['id'] ?? null;
        }
    }
    return null;
}

// B. Upload Sécurisé
function safeUpload($file, $destinationDir, $allowedTypes, $maxSize) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) return null;
    if (!in_array($file['type'], $allowedTypes)) return null;
    if ($file['size'] > $maxSize) return null;

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $ext;
    
    if (move_uploaded_file($file['tmp_name'], $destinationDir . $filename)) {
        return "uploads/" . $filename; // Chemin relatif pour le frontend
    }
    return null;
}

// --- LOGIQUE PRINCIPALE ---

// 2. Vérification Auth
$userId = getUserIdFromToken();
if (!$userId) {
    http_response_code(401);
    exit(json_encode(["error" => "Non autorisé"]));
}

// 3. Validation ID
if (!isset($_POST['id'])) {
    http_response_code(400);
    exit(json_encode(["error" => "ID manquant"]));
}
$blogId = (int)$_POST['id'];

// 4. Lecture DB
if (!file_exists($dbFile)) {
    http_response_code(500);
    exit(json_encode(["error" => "DB introuvable"]));
}
$blogs = json_decode(file_get_contents($dbFile), true);
if (!is_array($blogs)) $blogs = [];

$found = false;
$updated = false;

// 5. Boucle de modification
foreach ($blogs as &$b) {
    // On cherche l'article
    if ((int)$b['id'] !== $blogId) continue;

    $found = true;

    // VÉRIFICATION PROPRIÉTAIRE (Sécurité cruciale)
    if ((int)$b['user_id'] !== $userId) {
        http_response_code(403);
        exit(json_encode(["error" => "Ce n'est pas votre article"]));
    }

    // --- MISE À JOUR TEXTE ---
    if (isset($_POST['title'])) $b['title'] = trim($_POST['title']);
    if (isset($_POST['description'])) $b['description'] = trim($_POST['description']);

    // --- GESTION IMAGES ---
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        // 1. Supprimer l'ancienne image
        if (!empty($b['image']) && file_exists($uploadBaseDir . $b['image'])) {
            unlink($uploadBaseDir . $b['image']);
        }
        // 2. Upload la nouvelle
        $newPath = safeUpload($_FILES['image'], $uploadTargetDir, ["image/jpeg","image/png","image/webp"], 5*1024*1024);
        if ($newPath) $b['image'] = $newPath;
    }

    // --- GESTION AUDIO ---
    if (isset($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {
        if (!empty($b['audio']) && file_exists($uploadBaseDir . $b['audio'])) {
            unlink($uploadBaseDir . $b['audio']);
        }
        $newPath = safeUpload($_FILES['audio'], $uploadTargetDir, ["audio/mpeg","audio/ogg","audio/wav","audio/mp3"], 20*1024*1024);
        if ($newPath) $b['audio'] = $newPath;
    }

    // --- GESTION VIDÉO ---
    if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        if (!empty($b['video']) && file_exists($uploadBaseDir . $b['video'])) {
            unlink($uploadBaseDir . $b['video']);
        }
        $newPath = safeUpload($_FILES['video'], $uploadTargetDir, ["video/mp4","video/webm"], 100*1024*1024);
        if ($newPath) $b['video'] = $newPath;
    }

    $updated = true;
    break; // On a trouvé et modifié, on sort de la boucle
}

// 6. Sauvegarde
if (!$found) {
    http_response_code(404);
    exit(json_encode(["error" => "Blog introuvable"]));
}

if ($updated) {
    if (file_put_contents($dbFile, json_encode($blogs, JSON_PRETTY_PRINT))) {
        echo json_encode(["status" => "updated"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Erreur écriture DB"]);
    }
} else {
    // Si on a trouvé le blog mais rien modifié (cas rare)
    echo json_encode(["status" => "no_changes"]);
}
?>
