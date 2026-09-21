<?php
/**
 * WorkConnects — Configuration de l'installation
 * ------------------------------------------------------------------
 * Modifiez ces valeurs AVANT la première ouverture du site : le compte
 * administrateur est créé au tout premier chargement, à partir d'ici.
 *
 * Si vous changez l'email ou le mot de passe après coup, supprimez le
 * fichier data/workconnects.sqlite pour repartir d'une base neuve.
 */

// ---- Compte administrateur (accès au back-office) ----
define('ADMIN_EMAIL',    'admin@workconnects.fr');
define('ADMIN_PASSWORD', 'admin123');   // ⚠️ À CHANGER avant mise en ligne

// ---- Comptes de test ----
// true  : crée aussi un compte entreprise et un compte freelance de test
//         à la première ouverture (pratique pour essayer la plateforme).
// false : seul le compte administrateur est créé.
// ⚠️ Passez à false avant la mise en ligne, puis supprimez
//    data/workconnects.sqlite pour repartir d'une base propre.
define('COMPTES_TEST', true);

define('TEST_ENTREPRISE_EMAIL',    'entreprise@test.fr');
define('TEST_ENTREPRISE_PASSWORD', 'test123');
define('TEST_FREELANCE_EMAIL',     'freelance@test.fr');
define('TEST_FREELANCE_PASSWORD',  'test123');

// ---- Identité du site ----
define('SITE_NOM',    'WorkConnects');
define('SITE_EMAIL',  'contact@workconnects.fr');
define('SITE_TEL',    '');

// ---- Modèle économique ----
define('COMMISSION_DEFAUT', 20);   // Commission WorkConnects en %
define('BUDGET_PLAFOND_CFG', 5000); // Plafond du curseur de budget (€)
