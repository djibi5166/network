<?php
// On active l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Test du Logger</h2>";

// 1. Vérification du chemin
$file = __DIR__ . '/../db/history.json';
echo "Chemin cible : " . realpath($file) . "<br>";

if (!file_exists($file)) {
    die("<b style='color:red'>ERREUR : Le fichier history.json n'existe pas !</b>");
}

// 2. Vérification des permissions
if (!is_writable($file)) {
    die("<b style='color:red'>ERREUR : PHP n'a pas la permission d'écrire dans ce fichier. (Chmod nécessaire)</b>");
}

// 3. Test d'écriture
require 'helpers/logger.php';

echo "Tentative d'écriture...<br>";
try {
    logAction("TEST_ADMIN", "TEST_ACTION", "TEST_TARGET");
    echo "<b style='color:green'>SUCCÈS : L'écriture semble avoir fonctionné ! Vérifiez history.json.</b>";
} catch (Exception $e) {
    echo "<b style='color:red'>ERREUR : " . $e->getMessage() . "</b>";
}

// 4. Affichage du contenu actuel
echo "<h3>Contenu actuel :</h3>";
echo "<pre>" . file_get_contents($file) . "</pre>";
?>
