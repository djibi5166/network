<?php
// --- CONFIGURATION ---
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// 1. Chemins Absolus
$blogFile = __DIR__ . '/../db/blogs.json';
$userFile = __DIR__ . '/../db/users.json';

// --- FONCTIONS INTERNES ---

// Lecture sécurisée JSON
function readJson($path) {
    if (!file_exists($path)) return [];
    $content = file_get_contents($path);
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

// --- LOGIQUE PRINCIPALE ---

$blogs = readJson($blogFile);
$users = readJson($userFile);

// 2. Création d'une "Carte" des utilisateurs (Optimisation)
// Cela permet de trouver le nom d'un auteur instantanément sans refaire une boucle
$userMap = [];
foreach ($users as $u) {
    if (isset($u['id'])) {
        $userMap[$u['id']] = $u['username'] ?? "Utilisateur supprimé";
    }
}

// 3. Tri par date (Du plus récent au plus vieux)
usort($blogs, fn($a, $b) => 
    strtotime($b['date'] ?? 0) <=> strtotime($a['date'] ?? 0)
);

$clean = [];

foreach ($blogs as $b) {
    
    // Calcul sécurisé des likes
    $likeCount = 0;
    if (isset($b['liked_by']) && is_array($b['liked_by'])) {
        $likeCount = count($b['liked_by']);
    } elseif (isset($b['likes'])) {
        $likeCount = (int)$b['likes'];
    }

    // Sécurisation des commentaires (Doit être un tableau)
    $comments = isset($b['comments']) && is_array($b['comments']) ? $b['comments'] : [];

    // Récupération du nom de l'auteur
    $authorId = (int)($b['user_id'] ?? 0);
    $authorName = $userMap[$authorId] ?? "Inconnu";

    $clean[] = [
        "id" => (int)($b['id'] ?? 0),
        "user_id" => $authorId,
        "author" => $authorName, // <-- AJOUT CRUCIAL POUR LE FRONTEND
        "title" => $b['title'] ?? "Sans titre",
        "description" => $b['description'] ?? "",
        "image" => $b['image'] ?? null,
        "audio" => $b['audio'] ?? null,
        "video" => $b['video'] ?? null,
        "date" => $b['date'] ?? date("Y-m-d H:i:s"),
        "likes" => $likeCount,
        "comments" => $comments
    ];
}

// JSON_UNESCAPED_UNICODE permet d'afficher les accents correctement
echo json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
