<?php
require_once __DIR__ . '/includes/functions.php';
if (is_logged()) { header('Location: ' . dashboard_url(null, true)); exit; }

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $em = strtolower(trim($_POST['email'] ?? ''));
    $pw = $_POST['password'] ?? '';
    csrf_check();

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

    <div class="auth-alt">
      Pas encore de compte ? <a href="inscription.php">Créer un compte</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
