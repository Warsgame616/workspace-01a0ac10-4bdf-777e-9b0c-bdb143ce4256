<?php
// WorkConnects - Connexion base de données SQLite (PHP pur, aucune API externe)

require_once __DIR__ . '/../config.php';

define('DB_FILE', __DIR__ . '/../data/workconnects.sqlite');
define('COMMISSION_RATE', 0.20); // Commission WorkConnects par défaut : 20%

function db() {
    static $pdo = null;
    if ($pdo === null) {
        if (!is_dir(dirname(DB_FILE))) { mkdir(dirname(DB_FILE), 0777, true); }
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        install_schema($pdo);
        // Injecte les données de démonstration si la base est vide
        if ((int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() === 0) {
            seed_data($pdo);
        }
    }
    return $pdo;
}

function install_schema(PDO $pdo) {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL,              -- entreprise | freelance | admin
        nom TEXT NOT NULL,
        prenom TEXT DEFAULT '',
        telephone TEXT DEFAULT '',
        societe TEXT DEFAULT '',
        siret TEXT DEFAULT '',
        secteur TEXT DEFAULT '',
        taille TEXT DEFAULT '',
        titre_pro TEXT DEFAULT '',
        bio TEXT DEFAULT '',
        competences TEXT DEFAULT '',     -- liste séparée par virgules
        tarif_projet INTEGER DEFAULT 0,
        experience INTEGER DEFAULT 0,
        disponibilite TEXT DEFAULT 'disponible',
        note_moyenne REAL DEFAULT 0,
        nb_missions INTEGER DEFAULT 0,
        rgpd_consent INTEGER DEFAULT 1,
        actif INTEGER DEFAULT 1,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS projets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        entreprise_id INTEGER NOT NULL REFERENCES users(id),
        freelance_id INTEGER REFERENCES users(id),
        titre TEXT NOT NULL,
        categorie TEXT NOT NULL,
        description TEXT NOT NULL,
        competences TEXT DEFAULT '',
        budget_min INTEGER DEFAULT 0,
        budget_max INTEGER DEFAULT 0,
        delai TEXT DEFAULT '',
        date_limite TEXT DEFAULT '',
        statut TEXT DEFAULT 'nouveau',   -- nouveau|analyse|attribue|en_cours|livraison|termine|annule
        avancement INTEGER DEFAULT 0,
        montant_final INTEGER DEFAULT 0,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        projet_id INTEGER REFERENCES projets(id),
        expediteur_id INTEGER NOT NULL REFERENCES users(id),
        destinataire_id INTEGER NOT NULL REFERENCES users(id),
        contenu TEXT NOT NULL,
        lu INTEGER DEFAULT 0,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS fichiers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        projet_id INTEGER NOT NULL REFERENCES projets(id),
        uploader_id INTEGER NOT NULL REFERENCES users(id),
        nom TEXT NOT NULL,
        chemin TEXT NOT NULL,
        taille INTEGER DEFAULT 0,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS portfolio (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES users(id),
        titre TEXT DEFAULT '',
        description TEXT DEFAULT '',
        image TEXT NOT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL REFERENCES users(id),
        texte TEXT NOT NULL,
        lien TEXT DEFAULT '',
        lu INTEGER DEFAULT 0,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS paiements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        projet_id INTEGER NOT NULL REFERENCES projets(id),
        type TEXT NOT NULL,              -- 'frais' (etape 1) ou 'projet' (etape 2)
        montant INTEGER DEFAULT 0,       -- ce que l'entreprise regle
        statut TEXT DEFAULT 'a_payer',   -- a_payer | en_suspens | libere | rembourse | paye
        reference TEXT DEFAULT '',
        paye_le TEXT DEFAULT '',
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS factures (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        projet_id INTEGER NOT NULL REFERENCES projets(id),
        numero TEXT NOT NULL,
        montant_ht INTEGER DEFAULT 0,
        commission INTEGER DEFAULT 0,
        montant_freelance INTEGER DEFAULT 0,
        statut TEXT DEFAULT 'en_attente',
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS evaluations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        projet_id INTEGER NOT NULL REFERENCES projets(id),
        auteur_id INTEGER NOT NULL REFERENCES users(id),
        cible_id INTEGER NOT NULL REFERENCES users(id),
        note INTEGER NOT NULL,
        commentaire TEXT DEFAULT '',
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS parametres (
        cle TEXT PRIMARY KEY,
        valeur TEXT NOT NULL
    );
    ");

    // Coefficients de matching configurables
    $defaults = [
        'coef_competences' => '40',
        'coef_budget'      => '25',
        'coef_dispo'       => '15',
        'coef_experience'  => '10',
        'coef_note'        => '10',
        'commission'       => '20',
    ];
    $st = $pdo->prepare("INSERT OR IGNORE INTO parametres (cle, valeur) VALUES (?, ?)");
    foreach ($defaults as $k => $v) { $st->execute([$k, $v]); }
}

function param($cle, $defaut = 0) {
    $st = db()->prepare("SELECT valeur FROM parametres WHERE cle = ?");
    $st->execute([$cle]);
    $r = $st->fetchColumn();
    return $r === false ? $defaut : $r;
}

/**
 * Amorçage d'une installation neuve.
 * Crée UNIQUEMENT le compte administrateur WorkConnects.
 * Aucune donnée fictive : les tableaux de bord démarrent à zéro et se
 * remplissent avec les vrais comptes, projets et messages.
 */
function seed_data(PDO $pdo) {
    $cols = "email,password_hash,role,nom,prenom,telephone,societe,siret,secteur,taille,"
          . "titre_pro,bio,competences,tarif_projet,experience,disponibilite,note_moyenne,nb_missions";
    $st = $pdo->prepare("INSERT INTO users ($cols) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    // --- Compte administrateur (toujours créé) ---
    $st->execute([
        ADMIN_EMAIL, password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT),
        'admin', 'Administrateur', 'WorkConnects', '', 'WorkConnects', '', '', '',
        'Responsable de compte', '', '', 0, 0, '', 0, 0,
    ]);

    // --- Comptes de test (désactivables dans config.php) ---
    if (!defined('COMPTES_TEST') || !COMPTES_TEST) { return; }

    // Entreprise de test
    $st->execute([
        TEST_ENTREPRISE_EMAIL, password_hash(TEST_ENTREPRISE_PASSWORD, PASSWORD_DEFAULT),
        'entreprise', 'Martin', 'Julien', '0611223344',
        'Atelier Martin', '81234567800021', 'Artisanat', '10-50',
        '', "Atelier d'ébénisterie sur mesure.", '', 0, 0, '', 0, 0,
    ]);

    // Freelance de test
    $st->execute([
        TEST_FREELANCE_EMAIL, password_hash(TEST_FREELANCE_PASSWORD, PASSWORD_DEFAULT),
        'freelance', 'Leroy', 'Marie', '0633445566', '', '', '', '',
        'Développeuse Web', "Développeuse web spécialisée sites vitrines et e-commerce.",
        'PHP,JavaScript,HTML,CSS,WordPress,SEO', 2500, 6, 'disponible', 0, 0,
    ]);
}
