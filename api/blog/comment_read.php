<?php
// --- CONFIGURATION ---
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// 1. Chemin Absolu
$blogFile = __DIR__ . '/../db/blogs.json';

// 2. Récupération ID
$blogId = isset($_GET['blog_id']) ? (int)$_GET['blog_id'] : 0;

if ($blogId <= 0) {
    http_response_code(400);
    exit(json_encode(["error" => "ID manquant"]));
}

// 3. Lecture DB
if (!file_exists($blogFile)) {
    // Si la DB n'existe pas, on renvoie une liste vide plutôt qu'une erreur fatale
    echo json_encode([]); 
    exit;
}

$blogs = json_decode(file_get_contents($blogFile), true);
if (!is_array($blogs)) $blogs = [];

$found = false;
$comments = [];

// 4. Recherche des commentaires
foreach ($blogs as $b) {
    if ((int)$b['id'] === $blogId) {
        $found = true;
        
        // Si 'comments' existe, on le prend, sinon tableau vide
        if (isset($b['comments']) && is_array($b['comments'])) {
            $comments = $b['comments'];
        }
        
        // Optionnel : On peut trier les commentaires du plus récent au plus vieux ici
        // usort($comments, fn($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));
        
        break;
    }
}

if ($found) {
    // JSON_UNESCAPED_UNICODE est important pour les accents (é, à, ç...)
    echo json_encode($comments, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    http_response_code(404);
    echo json_encode(["error" => "Article introuvable"]);
}
?>
