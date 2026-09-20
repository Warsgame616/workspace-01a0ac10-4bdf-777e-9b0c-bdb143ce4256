<?php
// WorkConnects - Connexion base de données SQLite (PHP pur, aucune API externe)

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
        tjm INTEGER DEFAULT 0,
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

function seed_data(PDO $pdo) {
    $h = fn($p) => password_hash($p, PASSWORD_DEFAULT);

    $users = [
        ['admin@workconnects.fr', $h('admin123'), 'admin', 'Dupont', 'Sophie', '0600000000', 'WorkConnects', '', '', '', 'Responsable de compte', '', '', 0, 0, 'disponible', 0, 0],
        ['entreprise@test.fr', $h('test123'), 'entreprise', 'Martin', 'Julien', '0611223344', 'Nexora Industries', '81234567800021', 'Industrie', '50-200', '', 'PME industrielle en transformation numérique.', '', 0, 0, '', 0, 0],
        ['contact@luminatech.fr', $h('test123'), 'entreprise', 'Bernard', 'Claire', '0622334455', 'LuminaTech', '81234567800039', 'SaaS', '10-50', '', 'Éditeur de logiciels B2B.', '', 0, 0, '', 0, 0],
        ['marie@freelance.fr', $h('test123'), 'freelance', 'Leroy', 'Marie', '0633445566', '', '', '', '', 'Développeuse Full-Stack', 'Développeuse web avec 8 ans d\'expérience sur des projets B2B exigeants.', 'PHP,JavaScript,React,MySQL,API REST', 120, 8, 'disponible', 4.8, 23],
        ['thomas@freelance.fr', $h('test123'), 'freelance', 'Moreau', 'Thomas', '0644556677', '', '', '', '', 'Designer UI/UX', 'Designer produit spécialisé interfaces B2B et design systems.', 'Figma,UI Design,UX Research,Design System,Webflow', 110, 6, 'disponible', 4.6, 17],
        ['sarah@freelance.fr', $h('test123'), 'freelance', 'Benali', 'Sarah', '0655667788', '', '', '', '', 'Consultante SEO & Contenu', 'Stratégie de contenu et référencement naturel pour le B2B.', 'SEO,Rédaction,Analytics,Content Strategy', 95, 5, 'occupe', 4.9, 31],
        ['karim@freelance.fr', $h('test123'), 'freelance', 'Haddad', 'Karim', '0666778899', '', '', '', '', 'Développeur Mobile', 'Applications iOS et Android natives et cross-platform.', 'Flutter,Swift,Kotlin,Firebase,API REST', 140, 7, 'disponible', 4.7, 14],
    ];
    $st = $pdo->prepare("INSERT INTO users (email,password_hash,role,nom,prenom,telephone,societe,siret,secteur,taille,titre_pro,bio,competences,tjm,experience,disponibilite,note_moyenne,nb_missions) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    foreach ($users as $u) { $st->execute($u); }

    $projets = [
        [2, 4, 'Refonte du site vitrine corporate', 'Développement Web', "Refonte complète de notre site vitrine avec un design premium, optimisation des performances et intégration d'un back-office simple pour l'équipe marketing.", 'PHP,JavaScript,SEO', 2500, 4000, '2 mois', '2026-11-15', 'en_cours', 65, 3600],
        [2, null, 'Application mobile de suivi de production', 'Développement Mobile', "Application interne permettant aux chefs d'atelier de suivre la production en temps réel, avec mode hors-ligne et synchronisation.", 'Flutter,API REST,Firebase', 3500, 5000, '4 mois', '2027-01-30', 'analyse', 0, 0],
        [3, 5, 'Design system et refonte UI du produit SaaS', 'Design UI/UX', "Création d'un design system complet et refonte des écrans principaux de notre plateforme SaaS B2B.", 'Figma,Design System,UI Design', 2800, 4500, '3 mois', '2026-12-20', 'en_cours', 40, 4200],
        [3, 6, 'Stratégie SEO et contenu 2026', 'Marketing Digital', "Audit SEO complet, définition d'une stratégie de contenu sur 12 mois et production des premiers articles piliers.", 'SEO,Rédaction,Analytics', 1500, 2800, '6 mois', '2027-03-01', 'termine', 100, 2400],
        [2, null, "Automatisation des rapports financiers", 'Data & Automatisation', "Mise en place de tableaux de bord automatisés consolidant les données de nos différents outils de gestion.", 'Python,SQL,Data Viz', 1200, 2500, '2 mois', '2026-12-10', 'nouveau', 0, 0],
    ];
    $st = $pdo->prepare("INSERT INTO projets (entreprise_id,freelance_id,titre,categorie,description,competences,budget_min,budget_max,delai,date_limite,statut,avancement,montant_final) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
    foreach ($projets as $p) { $st->execute($p); }

    $messages = [
        [1, 2, 1, "Bonjour, où en est l'avancement de la refonte du site ?", 1],
        [1, 1, 2, "Bonjour Julien, le projet avance très bien, nous sommes à 65%. La maquette finale a été validée et l'intégration est en cours. Livraison prévue dans les délais.", 1],
        [1, 4, 1, "L'intégration des pages principales est terminée, je passe sur le back-office cette semaine.", 1],
        [3, 3, 1, "Pouvons-nous ajouter un écran supplémentaire au design system ?", 0],
        [2, 1, 2, "Nous avons identifié 3 profils correspondant à votre projet d'application mobile. Nous revenons vers vous sous 48h avec notre recommandation.", 0],
    ];
    $st = $pdo->prepare("INSERT INTO messages (projet_id,expediteur_id,destinataire_id,contenu,lu) VALUES (?,?,?,?,?)");
    foreach ($messages as $m) { $st->execute($m); }

    $notifs = [
        [2, "Votre projet « Refonte du site vitrine corporate » est passé à 65% d'avancement.", 'projet.php?id=1'],
        [2, "Nouveau message de votre chargé de compte WorkConnects.", 'messages.php'],
        [4, "Nouvelle mission attribuée : Refonte du site vitrine corporate.", 'projet.php?id=1'],
        [1, "Nouveau projet à analyser : Automatisation des rapports financiers.", 'admin.php'],
    ];
    $st = $pdo->prepare("INSERT INTO notifications (user_id,texte,lien) VALUES (?,?,?)");
    foreach ($notifs as $n) { $st->execute($n); }

    $pdo->prepare("INSERT INTO factures (projet_id,numero,montant_ht,commission,montant_freelance,statut) VALUES (?,?,?,?,?,?)")
        ->execute([4, 'FA-2026-0041', 2400, 480, 1920, 'payee']);
    $pdo->prepare("INSERT INTO evaluations (projet_id,auteur_id,cible_id,note,commentaire) VALUES (?,?,?,?,?)")
        ->execute([4, 3, 6, 5, "Travail remarquable, résultats mesurables dès le troisième mois."]);
}
