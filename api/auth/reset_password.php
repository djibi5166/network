<?php
// --- CONFIGURATION ---
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 1. Définition du chemin
$file = __DIR__ . '/../db/users.json'; // Vérifie bien le nombre de ../ selon ton dossier

// 2. Récupération des données
$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (empty($data['token']) || empty($data['password'])) {
    http_response_code(400);
    exit(json_encode(["error" => "Token ou mot de passe manquant"]));
}

$token = trim($data['token']);
$newPass = $data['password'];

// 3. Lecture de la base
if (!file_exists($file)) {
    http_response_code(500);
    exit(json_encode(["error" => "Base de données introuvable"]));
}

$users = json_decode(file_get_contents($file), true);
if (!is_array($users)) {
    http_response_code(500);
    exit(json_encode(["error" => "Erreur JSON DB"]));
}

// 4. Recherche et Mise à jour
$found = false;

foreach ($users as $key => $user) {
    // CORRECTION : On utilise $user['reset_exp'] (l'utilisateur actuel dans la boucle)
    if (isset($user['reset_token']) && $user['reset_token'] === $token) {
        
        // Vérification de l'expiration
        if (isset($user['reset_exp']) && time() > $user['reset_exp']) {
            http_response_code(400);
            exit(json_encode(["error" => "Le code a expiré. Veuillez recommencer."]));
        }

        // A. Mise à jour du mot de passe
        $users[$key]['password'] = password_hash($newPass, PASSWORD_DEFAULT);
        
        // B. Nettoyage (Sécurité : le token ne doit servir qu'une fois)
        unset($users[$key]['reset_token']);
        unset($users[$key]['reset_exp']);
        
        $found = true;
        break;
    }
}

// 5. Sauvegarde
if ($found) {
    // Utilisation de JSON_UNESCAPED_UNICODE pour garder le fichier propre
    $written = file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    if($written) {
        echo json_encode(["status" => "password_updated", "message" => "Mot de passe modifié avec succès !"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Erreur d'écriture sur le serveur"]);
    }
} else {
    http_response_code(400);
    echo json_encode(["error" => "Code OTP incorrect"]);
}
