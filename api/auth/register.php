<?php
session_start();

// --- CONFIGURATION ---
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// 1. Définition du chemin
$file = __DIR__ . '/../db/users.json';

// 2. Récupération des données
$input = file_get_contents("php://input");
$d = json_decode($input, true);

// Validation des champs vides
if (empty($d['username']) || empty($d['password']) || empty($d['email'])) {
    http_response_code(400);
    echo json_encode(["error" => "Pseudo, mot de passe et email requis"]);
    exit;
}

$username = trim($d['username']);
$password = $d['password'];
$email = trim($d['email']);

// Validation du format de l'email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit(json_encode(["error" => "Format d'email invalide"]));
}

// 3. Lecture de la DB
$users = [];
if (file_exists($file)) {
    $content = file_get_contents($file);
    $users = json_decode($content, true);
    if (!is_array($users)) $users = [];
}

// 4. VÉRIFICATION DOUBLONS (Pseudo ET Email)
foreach ($users as $u) {
    if (strcasecmp($u['username'], $username) === 0) {
        http_response_code(409);
        exit(json_encode(["error" => "Ce pseudo est déjà utilisé"]));
    }
    if (isset($u['email']) && strcasecmp($u['email'], $email) === 0) {
        http_response_code(409);
        exit(json_encode(["error" => "Cet email est déjà lié à un compte"]));
    }
}

// 5. Génération d'ID sécurisée
$newId = 1;
if (count($users) > 0) {
    $maxId = 0;
    foreach ($users as $u) {
        if (isset($u['id']) && (int)$u['id'] > $maxId) {
            $maxId = (int)$u['id'];
        }
    }
    $newId = $maxId + 1;
}

// 6. Logique Auto-Admin & Création
$role = (count($users) === 0) ? 'admin' : 'user';

$newUser = [
    "id" => $newId,
    "username" => $username,
    "email" => $email,
    "password" => password_hash($password, PASSWORD_DEFAULT),
    "role" => $role,
    "active" => true,
    "created_at" => date("Y-m-d H:i:s")
];

$users[] = $newUser;

// 7. Sauvegarde
if (file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    
    
    // to send to new users 
    
        $_SESSION['toNewUserName'] = $username;
        $_SESSION['toNewUserEmail'] = $email;

        // On inclut le fichier d'envoi d'email
        // Vérifie bien le chemin vers sendeMail.php
        require_once __DIR__ . '/../../libs/mailFunc/sendeMail.php';
        
       echo json_encode([
        "status" => "registered", 
        "role" => $role,
        "username" => $username
    ]); 
    
} else {
    http_response_code(500);
    echo json_encode(["error" => "Erreur d'écriture sur le serveur"]);
}

