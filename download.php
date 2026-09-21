<?php
/**
 * Téléchargement sécurisé des livrables.
 * Le fichier n'est jamais accessible directement : on vérifie d'abord
 * que l'utilisateur connecté a le droit de voir le projet concerné.
 */
require_once __DIR__ . '/includes/functions.php';

if (!is_logged()) { header('Location: ' . u('connexion.php')); exit; }
$u  = user();
$id = (int)($_GET['id'] ?? 0);

$st = db()->prepare("SELECT f.*, p.entreprise_id, p.freelance_id
                     FROM fichiers f JOIN projets p ON p.id = f.projet_id
                     WHERE f.id = ?");
$st->execute([$id]);
$f = $st->fetch();

if (!$f) { http_response_code(404); exit('Fichier introuvable.'); }

// Contrôle d'accès : client du projet, expert affecté, ou administrateur
$autorise = ($u['role'] === 'admin')
         || ((int)$f['entreprise_id'] === (int)$u['id'])
         || ((int)$f['freelance_id']  === (int)$u['id']);
if (!$autorise) { http_response_code(403); exit('Accès refusé.'); }

// Empêche toute remontée de répertoire
$chemin = realpath(__DIR__ . '/uploads/' . $f['chemin']);
$racine = realpath(__DIR__ . '/uploads');
if (!$chemin || !$racine || strpos($chemin, $racine) !== 0 || !is_file($chemin)) {
    http_response_code(404); exit('Fichier absent du serveur.');
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $f['nom']) . '"');
header('Content-Length: ' . filesize($chemin));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($chemin);
exit;
