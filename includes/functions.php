<?php
// WorkConnects - Fonctions utilitaires, sessions, sécurité, matching
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialise la base et les données de démonstration dès le premier accès au site
db();

/* ---------- Compatibilité : repli si l'extension mbstring est absente ---------- */
if (!function_exists('mb_substr')) {
    function mb_substr($s, $start, $length = null, $enc = null) {
        return $length === null ? substr((string)$s, $start) : substr((string)$s, $start, $length);
    }
}
if (!function_exists('mb_strlen'))   { function mb_strlen($s, $e = null)   { return strlen((string)$s); } }
if (!function_exists('mb_strtolower')){ function mb_strtolower($s, $e = null){ return strtolower((string)$s); } }

/* ---------- Sécurité / helpers ---------- */
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function csrf_token() {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
            die('Requête invalide (CSRF).');
        }
    }
}

/* ---------- Authentification ---------- */
function user() {
    if (empty($_SESSION['uid'])) return null;
    static $cache = null;
    if ($cache && $cache['id'] == $_SESSION['uid']) return $cache;
    $st = db()->prepare("SELECT * FROM users WHERE id = ?");
    $st->execute([$_SESSION['uid']]);
    $cache = $st->fetch() ?: null;
    return $cache;
}
function is_logged() { return user() !== null; }
function role() { $u = user(); return $u ? $u['role'] : null; }

function require_role($roles) {
    $roles = (array)$roles;
    if (!is_logged()) { header('Location: connexion.php'); exit; }
    if (!in_array(role(), $roles, true)) { header('Location: index.php'); exit; }
}

function login($email, $password) {
    $st = db()->prepare("SELECT * FROM users WHERE email = ? AND actif = 1");
    $st->execute([strtolower(trim($email))]);
    $u = $st->fetch();
    if ($u && password_verify($password, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = $u['id'];
        return $u;
    }
    return false;
}
function logout() { $_SESSION = []; session_destroy(); }

function dashboard_url($role = null) {
    $role = $role ?: role();
    return match ($role) {
        'admin'      => 'admin.php',
        'entreprise' => 'dashboard-entreprise.php',
        'freelance'  => 'dashboard-freelance.php',
        default      => 'index.php',
    };
}

/* ---------- Données ---------- */
function flash($msg = null, $type = 'success') {
    if ($msg !== null) { $_SESSION['flash'] = ['m' => $msg, 't' => $type]; return; }
    if (!empty($_SESSION['flash'])) { $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f; }
    return null;
}

function notify($user_id, $texte, $lien = '') {
    db()->prepare("INSERT INTO notifications (user_id,texte,lien) VALUES (?,?,?)")->execute([$user_id, $texte, $lien]);
}
function notifications($user_id, $limit = 10) {
    $st = db()->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT ?");
    $st->execute([$user_id, $limit]);
    return $st->fetchAll();
}
function nb_notifs_non_lues($user_id) {
    $st = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND lu = 0");
    $st->execute([$user_id]);
    return (int)$st->fetchColumn();
}

function statut_label($s) {
    return [
        'nouveau'   => 'Nouveau',
        'analyse'   => 'En analyse',
        'attribue'  => 'Attribué',
        'en_cours'  => 'En cours',
        'livraison' => 'En livraison',
        'termine'   => 'Terminé',
        'annule'    => 'Annulé',
    ][$s] ?? $s;
}
function statut_classe($s) {
    return [
        'nouveau'   => 'badge-gray',
        'analyse'   => 'badge-amber',
        'attribue'  => 'badge-blue',
        'en_cours'  => 'badge-blue',
        'livraison' => 'badge-violet',
        'termine'   => 'badge-green',
        'annule'    => 'badge-red',
    ][$s] ?? 'badge-gray';
}
function euros($n) { return number_format((float)$n, 0, ',', ' ') . ' €'; }
function date_fr($d) { return $d ? date('d/m/Y', strtotime($d)) : '—'; }
function initiales($u) { return strtoupper(mb_substr($u['prenom'] ?: $u['nom'], 0, 1) . mb_substr($u['nom'], 0, 1)); }

/* ---------- Moteur de matching (critères mesurables, coefficients configurables) ---------- */
function calculer_matching($projet) {
    $coefs = [
        'competences' => (float)param('coef_competences', 40),
        'budget'      => (float)param('coef_budget', 25),
        'dispo'       => (float)param('coef_dispo', 15),
        'experience'  => (float)param('coef_experience', 10),
        'note'        => (float)param('coef_note', 10),
    ];
    $total_coef = array_sum($coefs) ?: 1;

    $freelances = db()->query("SELECT * FROM users WHERE role = 'freelance' AND actif = 1")->fetchAll();
    $requises = array_filter(array_map('trim', explode(',', mb_strtolower($projet['competences']))));
    $budget_moy = ($projet['budget_min'] + $projet['budget_max']) / 2;

    $res = [];
    foreach ($freelances as $f) {
        $detail = [];

        // 1. Correspondance des compétences
        $skills = array_filter(array_map('trim', explode(',', mb_strtolower($f['competences']))));
        $match = $requises ? count(array_intersect($requises, $skills)) / count($requises) : 0;
        $detail['competences'] = round($match * 100);

        // 2. Adéquation budgétaire (TJM projeté vs budget)
        $cout_estime = $f['tjm'] * 30;
        if ($budget_moy <= 0) { $sb = 50; }
        elseif ($cout_estime <= $budget_moy) { $sb = 100; }
        else { $sb = max(0, 100 - (($cout_estime - $budget_moy) / $budget_moy) * 100); }
        $detail['budget'] = round($sb);

        // 3. Disponibilité
        $detail['dispo'] = $f['disponibilite'] === 'disponible' ? 100 : ($f['disponibilite'] === 'partiel' ? 50 : 10);

        // 4. Expérience (plafonnée à 10 ans)
        $detail['experience'] = round(min($f['experience'], 10) / 10 * 100);

        // 5. Note moyenne
        $detail['note'] = round(($f['note_moyenne'] / 5) * 100);

        $score = 0;
        foreach ($coefs as $k => $c) { $score += $detail[$k] * $c; }
        $score = round($score / $total_coef);

        $res[] = ['freelance' => $f, 'score' => $score, 'detail' => $detail];
    }
    usort($res, fn($a, $b) => $b['score'] <=> $a['score']);
    return $res;
}

/* ---------- Statistiques admin ---------- */
function stats_globales() {
    $d = db();
    return [
        'entreprises'   => (int)$d->query("SELECT COUNT(*) FROM users WHERE role='entreprise'")->fetchColumn(),
        'freelances'    => (int)$d->query("SELECT COUNT(*) FROM users WHERE role='freelance'")->fetchColumn(),
        'projets'       => (int)$d->query("SELECT COUNT(*) FROM projets")->fetchColumn(),
        'en_cours'      => (int)$d->query("SELECT COUNT(*) FROM projets WHERE statut IN ('attribue','en_cours','livraison')")->fetchColumn(),
        'a_traiter'     => (int)$d->query("SELECT COUNT(*) FROM projets WHERE statut IN ('nouveau','analyse')")->fetchColumn(),
        'termines'      => (int)$d->query("SELECT COUNT(*) FROM projets WHERE statut='termine'")->fetchColumn(),
        'ca'            => (int)$d->query("SELECT COALESCE(SUM(montant_final),0) FROM projets WHERE statut='termine'")->fetchColumn(),
        'commissions'   => (int)$d->query("SELECT COALESCE(SUM(commission),0) FROM factures")->fetchColumn(),
        'messages_nl'   => (int)$d->query("SELECT COUNT(*) FROM messages WHERE destinataire_id=1 AND lu=0")->fetchColumn(),
    ];
}
