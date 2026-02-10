<?php
// --- CONFIGURATION ---
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 1. Chemin de la DB
$file = __DIR__ . '/../db/blogs.json';

// 2. Fonction pour récupérer l'ID utilisateur depuis le Token
function getUserIdFromToken() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    
    // On cherche "Bearer XXXXX..."
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
        $parts = explode('.', $token);
        if (count($parts) === 3) {
            // Décodage du payload JWT
            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
            return $payload['id'] ?? null;
        }
    }
    return null;
}

// 3. Vérification Authentification
$userId = getUserIdFromToken();
if (!$userId) {
    http_response_code(401);
    exit(json_encode(["error" => "Vous devez être connecté pour aimer un article."]));
}

// 4. Récupération des données envoyées
$input = file_get_contents("php://input");
$data = json_decode($input, true);
$blogId = isset($data['blog_id']) ? (int)$data['blog_id'] : 0;

if ($blogId <= 0) {
    http_response_code(400);
    exit(json_encode(["error" => "ID du blog invalide"]));
}

// 5. Lecture DB
if (!file_exists($file)) {
    http_response_code(404);
    exit(json_encode(["error" => "Base de données introuvable"]));
}

$blogs = json_decode(file_get_contents($file), true);
if (!is_array($blogs)) $blogs = [];

$found = false;
$action = "";
$newCount = 0;

// 6. Logique de Like / Unlike
foreach ($blogs as &$b) {
    if ((int)$b['id'] === $blogId) {
        
        // Initialisation si le tableau n'existe pas
        if (!isset($b['liked_by']) || !is_array($b['liked_by'])) {
            $b['liked_by'] = [];
        }

        // Vérifie si l'utilisateur a déjà liké
        if (in_array($userId, $b['liked_by'])) {
            // --- UNLIKE (On retire le like) ---
            // On filtre le tableau pour enlever cet ID
            $b['liked_by'] = array_values(array_diff($b['liked_by'], [$userId]));
            $action = "unliked";
        } else {
            // --- LIKE (On ajoute le like) ---
            $b['liked_by'][] = $userId;
            $action = "liked";
        }

        // Mise à jour du compteur
        $b['likes'] = count($b['liked_by']);
        $newCount = $b['likes'];
        
        $found = true;
        break;
    }
}

// 7. Sauvegarde et Réponse
if ($found) {
    if (file_put_contents($file, json_encode($blogs, JSON_PRETTY_PRINT))) {
        echo json_encode([
            "status" => $action, // "liked" ou "unliked"
            "likes" => $newCount
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Erreur d'écriture DB"]);
    }
} else {
    http_response_code(404);
    echo json_encode(["error" => "Article introuvable"]);
}
?>
