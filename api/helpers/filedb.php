<?php
/*function readDB($file){
    if(!file_exists($file)) file_put_contents($file,"[]");
    $fp=fopen($file,"r");
    flock($fp,LOCK_SH);
    $data=json_decode(stream_get_contents($fp),true);
    flock($fp,LOCK_UN); fclose($fp);
    return is_array($data)?$data:[];
}
function writeDB($file,$data){
    $fp=fopen($file,"c+");
    flock($fp,LOCK_EX);
    ftruncate($fp,0);
    fwrite($fp,json_encode($data,JSON_PRETTY_PRINT));
    flock($fp,LOCK_UN); fclose($fp);
}
function generateId($data){
    return empty($data)?1:max(array_column($data,'id'))+1;
}
*/


// Le nouveau filedb.php est beaucoup plus tolérant.


function readDB($file) {
    // 1. TEST D'EXISTENCE
    if (!file_exists($file)) {
        // On arrête tout et on affiche le chemin complet testé
        // realpath() permet de voir où le serveur cherche VRAIMENT sur le téléphone
        $cheminAbsolu = realpath(dirname($file)) . '/' . basename($file);
        
        http_response_code(500); // Erreur serveur pour forcer l'affichage
        echo json_encode([
            "error" => "DEBUG: Fichier introuvable ! Le serveur cherche ici : " . $file . " (Résolu en : " . $cheminAbsolu . ")"
        ]);
        exit; // On coupe le script net
    }

    // 2. TEST DE LECTURE
    $content = file_get_contents($file);
    if ($content === false) {
        http_response_code(500);
        echo json_encode(["error" => "DEBUG: Fichier trouvé mais impossible à lire (Permissions ?)"]);
        exit;
    }

    // 3. TEST JSON
    $data = json_decode($content, true);
    if ($data === null) {
        http_response_code(500);
        echo json_encode(["error" => "DEBUG: Le fichier users.json est corrompu ou mal formaté (Erreur JSON: " . json_last_error_msg() . ")"]);
        exit;
    }

    return $data;
}

function writeDB($file, $data) {
    $success = file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    if ($success === false) {
        http_response_code(500);
        echo json_encode(["error" => "DEBUG: Impossible d'écrire dans le fichier (Permissions ?)"]);
        exit;
    }
}

