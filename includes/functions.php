<?php
// WorkConnects - Fonctions utilitaires, sessions, sécurité, matching
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    // Nom de session court et uniforme : c'est aussi le paramètre d'URL
    // utilisé en repli quand le navigateur bloque les cookies (?sid=...).
    session_name('sid');

    // En HTTPS (et notamment dans un aperçu embarqué en iframe), les navigateurs
    // n'acceptent le cookie de session que s'il est marqué SameSite=None; Secure.
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $local = (strpos($host, 'localhost') === 0) || (strpos($host, '127.0.0.1') === 0);
    // Hors développement local, le site est servi en HTTPS (hébergeur ou aperçu
    // embarqué). Le cookie doit alors être Secure + SameSite=None, sinon les
    // navigateurs le refusent dans une iframe et la session ne tient pas.
    $https = !$local
          || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
          || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $https,
        'samesite' => $https ? 'None' : 'Lax',
    ]);
    // Repli : si le navigateur bloque les cookies, l'identifiant de session est
    // relayé par l'URL. PHP le reprend alors automatiquement.
    $sid = $_GET['sid'] ?? $_POST['sid'] ?? '';
    if (empty($_COOKIE[session_name()]) && $sid
        && preg_match('/^[A-Za-z0-9,\-]{20,128}$/', $sid)) {
        session_id($sid);
        // Le cookie est refusé : PHP réécrit lui-même TOUS les liens et
        // formulaires internes pour y propager l'identifiant de session.
        // Indispensable pour les liens construits dynamiquement
        // (ex. admin-matching.php?id=12), que l'on ne peut pas traiter un à un.
        ini_set('session.use_cookies',      '0');
        ini_set('session.use_only_cookies', '0');
        ini_set('session.use_trans_sid',    '1');
        ini_set('session.trans_sid_tags',   'a=href,area=href,frame=src,form=');
        $h = $_SERVER['HTTP_HOST'] ?? '';
        if ($h !== '') { ini_set('session.trans_sid_hosts', $h); }
        @ini_set('url_rewriter.tags',       'a=href,area=href,frame=src,form=');
    }
    session_start();
}

/**
 * URL d'un fichier statique suffixée par sa date de modification.
 * Le navigateur recharge automatiquement la feuille de style ou le script
 * dès qu'on les modifie, sans avoir à vider le cache manuellement.
 */
function asset($chemin) {
    $abs = __DIR__ . '/../' . ltrim($chemin, '/');
    $v   = is_file($abs) ? filemtime($abs) : time();
    return $chemin . '?v=' . $v;
}

/** Ajoute l'identifiant de session à une URL quand le cookie est refusé. */
function u($url) {
    if (!empty($_COOKIE[session_name()]) || empty($_SESSION['uid'])) { return $url; }
    if (strpos($url, 'sid=') !== false || strpos($url, '://') !== false) { return $url; }
    return $url . (strpos($url, '?') === false ? '?' : '&') . 'sid=' . urlencode(session_id());
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

/** Clé de signature, persistée dans /data (hors racine web). */
function csrf_secret() {
    static $k = null;
    if ($k !== null) return $k;
    $f = __DIR__ . '/../data/.csrf_key';
    if (is_file($f)) { $k = trim(file_get_contents($f)); }
    if (empty($k)) {
        $k = bin2hex(random_bytes(32));
        if (!is_dir(dirname($f))) { @mkdir(dirname($f), 0755, true); }
        @file_put_contents($f, $k);
        @chmod($f, 0600);
    }
    return $k;
}

/**
 * Jeton CSRF signé (HMAC) et horodaté.
 * Il ne dépend PAS de la session : les formulaires restent utilisables même
 * quand le navigateur refuse les cookies (aperçu en iframe, cookies tiers
 * bloqués). La signature empêche toute falsification.
 */
function csrf_token() {
    $t = time();
    return $t . '.' . hash_hmac('sha256', (string)$t, csrf_secret());
}
function csrf_field() {
    $h = '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
    // Relaie aussi la session quand le cookie est bloqué
    if (empty($_COOKIE[session_name()]) && !empty($_SESSION['uid'])) {
        $h .= '<input type="hidden" name="sid" value="' . htmlspecialchars(session_id(), ENT_QUOTES) . '">';
    }
    return $h;
}
function csrf_valide($jeton) {
    if (!is_string($jeton) || strpos($jeton, '.') === false) { return false; }
    [$t, $sig] = explode('.', $jeton, 2);
    if (!ctype_digit($t)) { return false; }
    if (abs(time() - (int)$t) > 86400) { return false; }          // valable 24 h
    return hash_equals(hash_hmac('sha256', $t, csrf_secret()), $sig);
}

function csrf_check() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { return; }
    if (csrf_valide($_POST['csrf'] ?? '')) { return; }

    // Échec : le plus souvent la session a expiré ou le navigateur refuse le cookie.
    http_response_code(400);
    $retour = htmlspecialchars($_SERVER['HTTP_REFERER'] ?? 'index.php', ENT_QUOTES);
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Session expirée</title>'
       . '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;'
       . 'background:#F8FAFC;color:#0F172A;display:grid;place-items:center;min-height:100vh;margin:0;padding:24px}'
       . '.b{max-width:460px;background:#fff;border:1px solid #E2E8F0;border-radius:16px;padding:32px;text-align:center}'
       . 'h1{font-size:1.25rem;margin:0 0 10px}p{color:#64748B;font-size:.9375rem;line-height:1.6;margin:0 0 22px}'
       . 'a{display:inline-block;padding:12px 22px;background:#165DFF;color:#fff;border-radius:10px;'
       . 'text-decoration:none;font-weight:600;font-size:.9375rem}</style></head><body><div class="b">'
       . '<div style="font-size:2.5rem;margin-bottom:12px">⏱️</div>'
       . '<h1>Votre session a expiré</h1>'
       . '<p>Par sécurité, le formulaire a été refusé car votre session n\'était plus active. '
       . 'Cela arrive après une longue inactivité, ou si votre navigateur bloque les cookies. '
       . 'Il suffit de recommencer.</p>'
       . '<a href="' . $retour . '">Réessayer</a>'
       . '</div></body></html>';
    exit;
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
    if (!is_logged()) { header('Location: ' . u('connexion.php')); exit; }
    if (!in_array(role(), $roles, true)) { header('Location: ' . u('index.php')); exit; }
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

function dashboard_url($role = null, $avec_sid = false) {
    $role = $role ?: role();
    $url = match ($role) {
        'admin'                     => 'admin.php',
        'entreprise', 'particulier' => 'dashboard-entreprise.php',
        'freelance'                 => 'dashboard-freelance.php',
        default                     => 'index.php',
    };
    return $avec_sid ? u($url) : $url;
}

/** Un client est une entreprise OU un particulier : même parcours, même espace. */
function est_client($role = null) {
    $role = $role ?: role();
    return in_array($role, ['entreprise', 'particulier'], true);
}

/** Libellé lisible d'un rôle */
function role_label($role) {
    return ['entreprise'=>'Entreprise','particulier'=>'Particulier','freelance'=>'Freelance','admin'=>'Administration'][$role] ?? ucfirst($role);
}

/* ---------- Données ---------- */
function flash($msg = null, $type = 'success') {
    if ($msg !== null) { $_SESSION['flash'] = ['m' => $msg, 't' => $type]; return; }
    if (!empty($_SESSION['flash'])) { $f = $_SESSION['flash']; unset($_SESSION['flash']); return $f; }
    return null;
}

/** Identifiant du compte administrateur (robuste même après suppression du n°1). */
function admin_id() {
    static $id = null;
    if ($id !== null) return $id;
    $id = (int) db()->query("SELECT id FROM users WHERE role='admin' ORDER BY id LIMIT 1")->fetchColumn();
    return $id ?: 0;
}

function notify($user_id, $texte, $lien = '') {
    if (!$user_id) { return; }   // destinataire inconnu : on n'enregistre rien
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
/* Plafond du curseur budget : au-dela on affiche "5 000 €+" */
define('BUDGET_PLAFOND', 5000);
function euros_max($n) {
    return number_format((float)$n, 0, ',', ' ') . ' €' . ((float)$n >= BUDGET_PLAFOND ? '+' : '');
}
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

        // 2. Adéquation budgétaire : le tarif par projet se compare
        //    directement au budget annoncé par le client.
        $tarif = (int) $f['tarif_projet'];
        if ($budget_moy <= 0)      { $sb = 50; }   // budget inconnu : neutre
        elseif ($tarif <= 0)       { $sb = 50; }   // tarif non renseigné : neutre
        elseif ($tarif <= $budget_moy) { $sb = 100; }
        else { $sb = max(0, 100 - (($tarif - $budget_moy) / $budget_moy) * 100); }
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
        'entreprises'   => (int)$d->query("SELECT COUNT(*) FROM users WHERE role IN ('entreprise','particulier')")->fetchColumn(),
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

/* ============================================
   Gestion des fichiers téléversés
   ============================================ */

define('UPLOAD_MAX', 8 * 1024 * 1024); // 8 Mo

/** Extensions autorisées pour les livrables de projet */
function ext_doc_autorisees() {
    return ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv','zip','rar',
            'jpg','jpeg','png','gif','webp','svg','ai','psd','fig','mp4','mov'];
}
/** Extensions autorisées pour les images de portfolio */
function ext_img_autorisees() { return ['jpg','jpeg','png','gif','webp']; }

/**
 * Traite un fichier téléversé et le range dans /uploads/<sous_dossier>/
 * Renvoie ['ok'=>bool, 'nom'=>string, 'chemin'=>string, 'taille'=>int, 'erreur'=>string]
 */
function traiter_upload(array $file, $sous_dossier, array $extensions) {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'erreur' => "Aucun fichier sélectionné."];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'erreur' => "Le téléversement a échoué (le fichier est peut-être trop lourd)."];
    }
    if ($file['size'] > UPLOAD_MAX) {
        return ['ok' => false, 'erreur' => "Fichier trop volumineux : 8 Mo maximum."];
    }

    $nom_original = $file['name'];
    $ext = strtolower(pathinfo($nom_original, PATHINFO_EXTENSION));
    if (!in_array($ext, $extensions, true)) {
        return ['ok' => false, 'erreur' => "Format non autorisé (." . htmlspecialchars($ext) . ")."];
    }

    // Nom de stockage aléatoire : empêche l'écrasement et l'exécution
    $nom_stocke = bin2hex(random_bytes(12)) . '.' . $ext;
    $dossier = __DIR__ . '/../uploads/' . $sous_dossier;
    if (!is_dir($dossier)) { @mkdir($dossier, 0755, true); }
    $destination = $dossier . '/' . $nom_stocke;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['ok' => false, 'erreur' => "Impossible d'enregistrer le fichier sur le serveur."];
    }
    @chmod($destination, 0644);

    return [
        'ok'     => true,
        'nom'    => mb_substr($nom_original, 0, 180),
        'chemin' => $sous_dossier . '/' . $nom_stocke,
        'taille' => (int)$file['size'],
    ];
}

/** Récupère le portfolio d'un freelance */
function portfolio_de($user_id) {
    $st = db()->prepare("SELECT * FROM portfolio WHERE user_id = ? ORDER BY id DESC");
    $st->execute([$user_id]);
    return $st->fetchAll();
}

/** Icône associée à une extension de fichier */
function icone_fichier($nom) {
    $e = strtolower(pathinfo($nom, PATHINFO_EXTENSION));
    if (in_array($e, ['jpg','jpeg','png','gif','webp','svg','psd','ai','fig'])) return '🖼️';
    if (in_array($e, ['pdf'])) return '📕';
    if (in_array($e, ['doc','docx','txt'])) return '📄';
    if (in_array($e, ['xls','xlsx','csv'])) return '📊';
    if (in_array($e, ['ppt','pptx'])) return '📽️';
    if (in_array($e, ['zip','rar'])) return '🗜️';
    if (in_array($e, ['mp4','mov'])) return '🎬';
    return '📎';
}

/** Taille lisible */
function taille_lisible($o) {
    if ($o >= 1048576) return round($o / 1048576, 1) . ' Mo';
    if ($o >= 1024)    return round($o / 1024) . ' Ko';
    return $o . ' o';
}

/* ============================================
   Référentiel des compétences, par domaine
   ============================================ */
function competences_par_domaine() {
    return [
        'Développement Web' => ['PHP','JavaScript','TypeScript','React','Vue','Angular','Node.js','Laravel','Symfony','WordPress','Shopify','HTML/CSS','MySQL','PostgreSQL','API REST'],
        'Développement Mobile' => ['Flutter','React Native','Swift','Kotlin','iOS','Android','Firebase'],
        'Design & Création' => ['UI Design','UX Design','UX Research','Design System','Figma','Adobe XD','Photoshop','Illustrator','InDesign','Direction artistique','Identité visuelle','Logo','Charte graphique','Maquettage','Prototypage','Webdesign','Design produit','Motion Design','Illustration','Retouche photo','Print / Édition','Packaging'],
        'Marketing & Contenu' => ['SEO','SEA / Google Ads','Réseaux sociaux','Community Management','Content Strategy','Rédaction web','Copywriting','Newsletter','Emailing','Analytics','Growth Hacking','Publicité Meta'],
        'Vidéo & Audio' => ['Montage vidéo','After Effects','Premiere Pro','Animation 2D','Animation 3D','Voix off','Sound Design','Podcast'],
        'Data & Automatisation' => ['Python','SQL','Data Visualisation','Power BI','Tableau','Excel avancé','Web Scraping','Automatisation','Zapier / Make','Machine Learning'],
        'Rédaction & Traduction' => ['Rédaction','Correction','Relecture','Traduction anglais','Traduction espagnol','Documentation technique','Storytelling'],
        'Technique & Sécurité' => ['DevOps','Docker','Linux','Cybersécurité','Audit de sécurité','RGPD','Hébergement','Maintenance','Migration'],
        'Conseil & Gestion' => ['Gestion de projet','Product Management','Agile / Scrum','Business Plan','Étude de marché','Formation'],
    ];
}

/** Liste à plat de toutes les compétences */
function toutes_competences() {
    $out = [];
    foreach (competences_par_domaine() as $liste) { $out = array_merge($out, $liste); }
    return $out;
}

/**
 * Affiche le sélecteur de compétences groupé par domaine.
 * $selection : chaîne "PHP,Figma" des compétences déjà choisies.
 */
function champ_competences($selection = '', $input_id = 'competences') {
    $sel = array_filter(array_map('trim', explode(',', (string)$selection)));
    echo '<input type="hidden" id="'.htmlspecialchars($input_id).'" name="'.htmlspecialchars($input_id).'" value="'.htmlspecialchars($selection, ENT_QUOTES).'">';
    echo '<div class="skills-box" data-input="'.htmlspecialchars($input_id).'">';
    foreach (competences_par_domaine() as $domaine => $liste) {
        echo '<div class="skills-group">';
        echo '<div class="skills-title">'.htmlspecialchars($domaine).'</div>';
        echo '<div class="tagbox-inner">';
        foreach ($liste as $c) {
            $on = in_array($c, $sel, true) ? ' on' : '';
            echo '<span class="tag-opt'.$on.'">'.htmlspecialchars($c).'</span>';
        }
        echo '</div></div>';
    }
    echo '</div>';
}
