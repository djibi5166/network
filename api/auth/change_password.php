<?php
// --- CONFIGURATION ---
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Clé secrète (STRICTEMENT la même que dans login.php)
$secret = "TON_CODE_SECRET_TRES_LONG"; 

// Ajustement du chemin : On remonte d'api/auth/ vers la racine pour trouver /db/
$dbFile = __DIR__ . '/../db/users.json';

// --- 1. VÉRIFICATION DU TOKEN JWT ---
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$jwt = str_replace('Bearer ', '', $authHeader);

$parts = explode('.', $jwt);
if (count($parts) !== 3) {
    http_response_code(401);
    exit(json_encode(["error" => "Authentification requise"]));
}

list($header64, $payload64, $signature) = $parts;

// Recalcul de la signature pour valider l'intégrité
$expectedSig = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(hash_hmac('sha256', "$header64.$payload64", $secret, true)));

if ($signature !== $expectedSig) {
    http_response_code(401);
    exit(json_encode(["error" => "Session invalide (Signature incorrecte)"]));
}

// Décodage des infos utilisateur
$payloadJson = base64_decode(str_replace(['-', '_'], ['+', '/'], $payload64));
$userData = json_decode($payloadJson, true);

// --- 2. TRAITEMENT DU MOT DE PASSE ---
$input = json_decode(file_get_contents("php://input"), true);
$oldPass = $input['old'] ?? '';
$newPass = $input['new'] ?? '';

if (empty($oldPass) || empty($newPass)) {
    http_response_code(400);
    exit(json_encode(["error" => "Veuillez remplir tous les champs"]));
}

// Lecture DB
if (!file_exists($dbFile)) {
    http_response_code(500);
    exit(json_encode(["error" => "Base de données introuvable. Vérifiez le chemin : $dbFile"]));
}

$users = json_decode(file_get_contents($dbFile), true);
$updated = false;

foreach ($users as &$u) {
    // Comparaison ID (Cast en int pour éviter les erreurs de type)
    if ((int)$u['id'] === (int)$userData['id']) {
        
        // VÉRIFICATION DU DÉCRYPTAGE
        // password_verify compare le texte clair ($oldPass) au hash stocké ($u['password'])
        if (password_verify($oldPass, $u['password'])) {
            $u['password'] = password_hash($newPass, PASSWORD_DEFAULT);
            $updated = true;
           // exit(json_encode(['success'=>'huoooo!']));
        } else {
            http_response_code(401);
            exit(json_encode(["error" => "L'ancien mot de passe est incorrect"]));
            
        }
        break;
    }
}

// --- 3. SAUVEGARDE ---
if ($updated) {
    if (file_put_contents($dbFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        echo json_encode(["status" => "success", "message" => "Mot de passe modifié avec succès"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Erreur lors de l'écriture dans le fichier JSON"]);
    }
} else {
    http_response_code(404);
    echo json_encode(["error" => "Utilisateur introuvable dans la base"]);
}
