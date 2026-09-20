<?php
/**
 * WorkConnects — Page de diagnostic serveur
 *
 * Ouvrez cette page après l'envoi des fichiers sur votre hébergeur :
 *     https://votredomaine.fr/check.php
 *
 * IMPORTANT : supprimez ce fichier une fois les vérifications terminées.
 */

$tests = [];

// PHP
$v = PHP_VERSION;
$tests[] = [
    'Version de PHP',
    version_compare($v, '7.4', '>='),
    "PHP $v détecté",
    "PHP $v est trop ancien. Passez en PHP 8.1 ou supérieur dans hPanel → Avancé → Configuration PHP."
];

// PDO SQLite
$tests[] = [
    'Extension pdo_sqlite',
    extension_loaded('pdo_sqlite'),
    'Activée — la base de données peut fonctionner',
    "Manquante. Activez-la dans hPanel → Avancé → Configuration PHP → Extensions PHP."
];

// mbstring (optionnelle)
$tests[] = [
    'Extension mbstring (optionnelle)',
    extension_loaded('mbstring'),
    'Activée',
    "Absente — le site fonctionne quand même grâce au repli automatique intégré."
];

// Dossier data accessible en écriture
$dir = __DIR__ . '/data';
if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
$tests[] = [
    'Dossier /data accessible en écriture',
    is_dir($dir) && is_writable($dir),
    'Le serveur peut créer et modifier la base de données',
    "Le dossier data/ n'est pas accessible en écriture. Dans le gestionnaire de fichiers Hostinger, clic droit sur data → Permissions → 755."
];

// Création réelle de la base
$dbok = false; $dbmsg = '';
try {
    require_once __DIR__ . '/includes/db.php';
    $n = (int) db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $dbok = $n > 0;
    $dbmsg = "Base opérationnelle — $n comptes enregistrés";
} catch (Throwable $ex) {
    $dbmsg = 'Erreur : ' . $ex->getMessage();
}
$tests[] = ['Base de données SQLite', $dbok, $dbmsg, $dbmsg];

// Protection du dossier data
$protege = file_exists(__DIR__ . '/data/.htaccess');
$tests[] = [
    'Protection du dossier /data',
    $protege,
    'Le fichier data/.htaccess est bien présent',
    "Le fichier data/.htaccess est absent. Votre base serait téléchargeable publiquement. Renvoyez-le par FTP (les fichiers commençant par un point sont parfois masqués : activez l'affichage des fichiers cachés)."
];

// Sessions
$tests[] = [
    'Sessions PHP',
    function_exists('session_start'),
    'Disponibles — la connexion fonctionnera',
    'Sessions indisponibles, contactez le support de votre hébergeur.'
];

$ko = count(array_filter($tests, fn($t) => !$t[1] && strpos($t[0], 'optionnelle') === false));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Diagnostic — WorkConnects</title>
<style>
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#F8FAFC;color:#0F172A;margin:0;padding:40px 20px;line-height:1.6}
  .box{max-width:720px;margin:0 auto;background:#fff;border:1px solid #E2E8F0;border-radius:16px;padding:32px}
  h1{margin:0 0 6px;font-size:1.5rem}
  .sub{color:#64748B;margin:0 0 24px;font-size:.9375rem}
  .r{display:flex;gap:14px;padding:16px 0;border-bottom:1px solid #F1F5F9;align-items:flex-start}
  .r:last-of-type{border-bottom:0}
  .i{font-size:1.25rem;flex-shrink:0;line-height:1.3}
  .t{font-weight:600;font-size:.9375rem}
  .d{font-size:.875rem;color:#64748B;margin-top:3px}
  .ok{color:#0F9960}.no{color:#B91C1C}
  .banner{padding:18px;border-radius:10px;margin-bottom:26px;font-weight:600}
  .green{background:#E7F6EF;color:#0F9960}
  .red{background:#FEE2E2;color:#B91C1C}
  .warn{margin-top:26px;padding:16px;background:#FEF3C7;color:#B45309;border-radius:10px;font-size:.875rem}
  a.btn{display:inline-block;margin-top:22px;padding:12px 22px;background:#165DFF;color:#fff;border-radius:10px;text-decoration:none;font-weight:600}
</style>
</head>
<body>
<div class="box">
  <h1>Diagnostic de l'installation</h1>
  <p class="sub">Vérification de la compatibilité de votre hébergement avec WorkConnects.</p>

  <?php if ($ko === 0): ?>
    <div class="banner green">✅ Tout est bon — votre site est prêt à fonctionner.</div>
  <?php else: ?>
    <div class="banner red">⚠️ <?= $ko ?> point<?= $ko > 1 ? 's' : '' ?> à corriger avant que le site fonctionne.</div>
  <?php endif; ?>

  <?php foreach ($tests as $t): ?>
    <div class="r">
      <span class="i <?= $t[1] ? 'ok' : 'no' ?>"><?= $t[1] ? '✅' : '❌' ?></span>
      <div>
        <div class="t"><?= htmlspecialchars($t[0]) ?></div>
        <div class="d"><?= htmlspecialchars($t[1] ? $t[2] : $t[3]) ?></div>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="warn">
    🔒 <strong>Pensez à supprimer ce fichier</strong> (<code>check.php</code>) une fois vos vérifications terminées :
    il révèle des informations sur votre serveur.
  </div>

  <a class="btn" href="index.php">Ouvrir le site →</a>
</div>
</body>
</html>
