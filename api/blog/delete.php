<?php
// --- CONFIGURATION ---
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 1. Chemins
$dbFile = __DIR__ . '/../db/blogs.json';
// Dossier racine du projet (pour trouver les uploads)
$projectRoot = __DIR__ . '/../../'; 

// 2. Fonction Auth (Récupération ID depuis Token)
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

// 3. Vérification des droits
$userId = getUserIdFromToken();
if (!$userId) {
    http_response_code(401);
    exit(json_encode(["error" => "Non autorisé"]));
}

// 4. Récupération ID du blog à supprimer
$input = json_decode(file_get_contents("php://input"), true);
$blogId = isset($input['id']) ? (int)$input['id'] : 0;

if ($blogId <= 0) {
    http_response_code(400);
    exit(json_encode(["error" => "ID invalide"]));
}

// 5. Lecture DB
if (!file_exists($dbFile)) {
    exit(json_encode(["error" => "DB introuvable"]));
}
$blogs = json_decode(file_get_contents($dbFile), true);
if (!is_array($blogs)) $blogs = [];

$found = false;
$newBlogs = [];

// 6. Boucle de suppression
foreach ($blogs as $b) {
    // Si c'est le blog qu'on cherche ET que l'utilisateur est le propriétaire
    if ((int)$b['id'] === $blogId) {
        
        // VÉRIFICATION PROPRIÉTAIRE
        // (On s'assure que user_id correspond bien à celui du token)
        if ((int)$b['user_id'] !== $userId) {
            http_response_code(403); // Forbidden
            exit(json_encode(["error" => "Vous ne pouvez supprimer que vos propres articles"]));
        }

        // SUPPRESSION DES FICHIERS MÉDIAS (Important !)
        if (!empty($b['image']) && file_exists($projectRoot . $b['image'])) unlink($projectRoot . $b['image']);
        if (!empty($b['audio']) && file_exists($projectRoot . $b['audio'])) unlink($projectRoot . $b['audio']);
        if (!empty($b['video']) && file_exists($projectRoot . $b['video'])) unlink($projectRoot . $b['video']);

        $found = true;
        // On ne l'ajoute pas à $newBlogs, donc il est supprimé
        continue; 
    }
    
    // On garde les autres blogs
    $newBlogs[] = $b;
}

// 7. Sauvegarde
if ($found) {
    if (file_put_contents($dbFile, json_encode($newBlogs, JSON_PRETTY_PRINT))) {
        echo json_encode(["status" => "deleted successfully !"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Erreur d'écriture DB"]);
    }
} else {
    http_response_code(404);
    echo json_encode(["error" => "Blog introuvable ou vous n'êtes pas l'auteur"]);
}
?>
