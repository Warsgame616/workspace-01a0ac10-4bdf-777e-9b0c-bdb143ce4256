<?php
require_once __DIR__ . '/includes/functions.php';
if (is_logged()) { header('Location: ' . dashboard_url(null, true)); exit; }

$erreur = '';
/* Comptes de démonstration : connexion directe, sans jeton CSRF.
   Les identifiants sont publics et affichés à l'écran : aucune donnée n'est exposée.
   Cela garantit que les boutons fonctionnent même quand le navigateur bloque
   le cookie de session (aperçu embarqué en iframe, cookies tiers refusés…). */
$comptes_demo = [
    'entreprise@test.fr'    => 'test123',
    'marie@freelance.fr'    => 'test123',
    'admin@workconnects.fr' => 'admin123',
];

/* Accès démo par simple lien : connexion.php?demo=entreprise
   Fonctionne sans cookie, sans JavaScript et sans POST : c'est la méthode
   la plus robuste dans un aperçu embarqué où les cookies tiers sont bloqués. */
$raccourcis = [
    'entreprise' => 'entreprise@test.fr',
    'freelance'  => 'marie@freelance.fr',
    'admin'      => 'admin@workconnects.fr',
];
if (isset($_GET['demo']) && isset($raccourcis[$_GET['demo']])) {
    $em = $raccourcis[$_GET['demo']];
    if ($x = login($em, $comptes_demo[$em])) {
        header('Location: ' . dashboard_url($x['role'], true));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $em = strtolower(trim($_POST['email'] ?? ''));
    $pw = $_POST['password'] ?? '';
    $est_demo = isset($_POST['demo'])
             && isset($comptes_demo[$em])
             && hash_equals($comptes_demo[$em], $pw);

    // Le formulaire classique reste protégé contre le CSRF
    if (!$est_demo) { csrf_check(); }

    $u = login($em, $pw);
    if ($u) {
        header('Location: ' . dashboard_url($u['role'], true));
        exit;
    }
    $erreur = "Identifiants incorrects. Vérifiez votre adresse e-mail et votre mot de passe.";
}

$titre = "Connexion";
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
  <div class="auth-card">
    <h1>Connexion</h1>
    <p>Accédez à votre espace WorkConnects.</p>

    <?php if ($erreur): ?>
      <div class="alert alert-error"><?= e($erreur) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['inscrit'])): ?>
      <div class="alert alert-success">Votre compte a bien été créé. Vous pouvez vous connecter.</div>
    <?php endif; ?>

    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="email">Adresse e-mail professionnelle</label>
        <input type="email" id="email" name="email" class="input" required
               value="<?= e($_POST['email'] ?? '') ?>" placeholder="vous@entreprise.fr" autocomplete="username">
      </div>
      <div class="field">
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" class="input" required
               placeholder="••••••••" autocomplete="current-password">
      </div>
      <label class="check mb-3">
        <input type="checkbox" name="remember"> <span>Rester connecté sur cet appareil</span>
      </label>
      <button type="submit" class="btn btn-primary btn-block btn-lg">Se connecter</button>
    </form>

    <div class="demo-box">
      <h5>Comptes de démonstration — connexion en un clic</h5>
      <?php
      $demos = [
        ['🏢',  'entreprise', 'Espace entreprise',        'Nexora Industries'],
        ['👩‍💻', 'freelance',  'Espace freelance',         'Marie Leroy'],
        ['🛡️',  'admin',      'Back-office WorkConnects', 'Administration'],
      ];
      foreach ($demos as $d): ?>
        <a href="connexion.php?demo=<?= $d[1] ?>" class="demo-btn">
          <span style="font-size:1.125rem"><?= $d[0] ?></span>
          <span><strong><?= e($d[2]) ?></strong><span><?= e($d[3]) ?></span></span>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="auth-alt">
      Pas encore de compte ? <a href="<?= u('inscription.php') ?>">Créer un compte</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
