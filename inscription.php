<?php
require_once __DIR__ . '/includes/functions.php';
if (is_logged()) { header('Location: ' . dashboard_url(null, true)); exit; }

$erreur = '';
$role_pre = in_array($_GET['role'] ?? '', ['entreprise','particulier','freelance'], true) ? $_GET['role'] : 'entreprise';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $role  = in_array($_POST['role'] ?? '', ['entreprise','particulier','freelance'], true) ? $_POST['role'] : '';
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';
    $nom   = trim($_POST['nom'] ?? '');
    $prenom= trim($_POST['prenom'] ?? '');

    if (!$role)                                        { $erreur = "Veuillez choisir un type de compte."; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)){ $erreur = "Adresse e-mail invalide."; }
    elseif (strlen($pass) < 6)                         { $erreur = "Le mot de passe doit contenir au moins 6 caractères."; }
    elseif ($pass !== ($_POST['password2'] ?? ''))     { $erreur = "Les deux mots de passe ne correspondent pas."; }
    elseif (!$nom)                                     { $erreur = "Le nom est obligatoire."; }
    elseif (empty($_POST['rgpd']))                     { $erreur = "Vous devez accepter la politique de confidentialité."; }
    else {
        $st = db()->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $st->execute([$email]);
        if ($st->fetchColumn()) {
            $erreur = "Un compte existe déjà avec cette adresse e-mail.";
        } else {
            db()->prepare("INSERT INTO users
                (email,password_hash,role,nom,prenom,telephone,societe,siret,secteur,taille,titre_pro,bio,competences,tjm,experience,disponibilite)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
              ->execute([
                $email, password_hash($pass, PASSWORD_DEFAULT), $role, $nom, $prenom,
                trim($_POST['telephone'] ?? ''),
                trim($_POST['societe'] ?? ''), trim($_POST['siret'] ?? ''),
                trim($_POST['secteur'] ?? ''), trim($_POST['taille'] ?? ''),
                trim($_POST['titre_pro'] ?? ''), trim($_POST['bio'] ?? ''),
                trim($_POST['competences'] ?? ''),
                (int)($_POST['tjm'] ?? 0), (int)($_POST['experience'] ?? 0),
                $_POST['disponibilite'] ?? 'disponible',
              ]);
            $uid = db()->lastInsertId();
            notify($uid, "Bienvenue sur WorkConnects. Votre chargé de compte vous contactera sous 48 h.", 'index.php');
            notify(1, "Nouvelle inscription : " . $nom . " (" . $role . ").", 'admin.php');
            header('Location: connexion.php?inscrit=1'); exit;
        }
    }
    $role_pre = $role ?: $role_pre;
}

$titre = "Créer un compte";
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrap">
  <div class="auth-card" style="max-width:560px">
    <h1>Créer un compte</h1>
    <p>Quelques informations pour démarrer. Cela prend moins de 3 minutes.</p>

    <?php if ($erreur): ?><div class="alert alert-error"><?= e($erreur) ?></div><?php endif; ?>

    <form method="post" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="role" id="role" value="<?= e($role_pre) ?>">

      <label class="strong small mb-1" style="display:block">Je suis…</label>
      <div class="role-grid role-grid-3">
        <div class="role-opt <?= $role_pre==='entreprise'?'selected':'' ?>" data-target="role" data-value="entreprise">
          <div class="ico">🏢</div><strong>Une entreprise</strong><span>Je veux faire réaliser un projet</span>
        </div>
        <div class="role-opt <?= $role_pre==='particulier'?'selected':'' ?>" data-target="role" data-value="particulier">
          <div class="ico">🙋</div><strong>Un particulier</strong><span>J'ai un projet personnel</span>
        </div>
        <div class="role-opt <?= $role_pre==='freelance'?'selected':'' ?>" data-target="role" data-value="freelance">
          <div class="ico">👩‍💻</div><strong>Un freelance</strong><span>Je veux recevoir des missions</span>
        </div>
      </div>

      <div class="field-row">
        <div class="field">
          <label for="prenom">Prénom</label>
          <input type="text" id="prenom" name="prenom" class="input" value="<?= e($_POST['prenom'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="nom">Nom *</label>
          <input type="text" id="nom" name="nom" class="input" required value="<?= e($_POST['nom'] ?? '') ?>">
        </div>
      </div>

      <div class="field">
        <label for="email">Adresse e-mail *</label>
        <input type="email" id="email" name="email" class="input" required value="<?= e($_POST['email'] ?? '') ?>">
      </div>

      <div class="field">
        <label for="telephone">Téléphone</label>
        <input type="tel" id="telephone" name="telephone" class="input" value="<?= e($_POST['telephone'] ?? '') ?>">
      </div>

      <!-- Bloc entreprise -->
      <div data-role-block="entreprise" style="<?= $role_pre==='entreprise'?'':'display:none' ?>">
        <div class="field">
          <label for="societe">Nom de l'entreprise</label>
          <input type="text" id="societe" name="societe" class="input" value="<?= e($_POST['societe'] ?? '') ?>">
        </div>
        <div class="field">
          <label for="taille">Effectif</label>
          <select id="taille" name="taille" class="select">
            <option value="">Sélectionner…</option>
            <?php foreach (['1-10','10-50','50-200','200-1000','1000+'] as $t): ?>
              <option <?= ($_POST['taille'] ?? '')===$t?'selected':'' ?>><?= $t ?> salariés</option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Bloc particulier : aucun champ supplémentaire -->
      <div data-role-block="particulier" style="<?= $role_pre==='particulier'?'':'display:none' ?>">
        <div class="alert alert-info" style="margin-bottom:18px">
          🙋 En tant que particulier, vous décrivez simplement votre projet : notre équipe s'occupe de tout le reste.
        </div>
      </div>

      <!-- Bloc freelance -->
      <div data-role-block="freelance" style="<?= $role_pre==='freelance'?'':'display:none' ?>">
        <div class="field">
          <label for="titre_pro">Titre professionnel</label>
          <input type="text" id="titre_pro" name="titre_pro" class="input"
                 placeholder="Ex. Développeuse Full-Stack" value="<?= e($_POST['titre_pro'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Vos compétences</label>
          <?php champ_competences($_POST['competences'] ?? ''); ?>
          <div class="hint">Cliquez pour sélectionner toutes vos compétences, dans un ou plusieurs domaines.</div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="tjm">Tarif journalier (€ / jour)</label>
            <input type="number" id="tjm" name="tjm" class="input" min="0" step="10" placeholder="Ex. 250" value="<?= e($_POST['tjm'] ?? '') ?>">
            <div class="hint">Ce que vous facturez pour une journée de travail.</div>
          </div>
          <div class="field">
            <label for="experience">Années d'expérience</label>
            <input type="number" id="experience" name="experience" class="input" min="0" max="50" value="<?= e($_POST['experience'] ?? '') ?>">
          </div>
        </div>
        <div class="field">
          <label for="disponibilite">Disponibilité</label>
          <select id="disponibilite" name="disponibilite" class="select">
            <option value="disponible">Disponible immédiatement</option>
            <option value="partiel">Partiellement disponible</option>
            <option value="occupe">Actuellement en mission</option>
          </select>
        </div>
      </div>

      <div class="field-row">
        <div class="field">
          <label for="password">Mot de passe *</label>
          <input type="password" id="password" name="password" class="input" required minlength="6">
          <div class="hint">6 caractères minimum.</div>
        </div>
        <div class="field">
          <label for="password2">Confirmation *</label>
          <input type="password" id="password2" name="password2" class="input" required minlength="6">
        </div>
      </div>

      <label class="check mb-3">
        <input type="checkbox" name="rgpd" value="1" required>
        <span>J'accepte les <a href="mentions.php#cgu" style="color:var(--blue)">conditions générales</a> et la
        <a href="mentions.php#rgpd" style="color:var(--blue)">politique de confidentialité</a>. Mes données sont utilisées uniquement
        dans le cadre de la mise en relation et du suivi de projet (RGPD).</span>
      </label>

      <button type="submit" class="btn btn-primary btn-block btn-lg">Créer mon compte</button>
    </form>

    <div class="auth-alt">Déjà inscrit ? <a href="<?= u('connexion.php') ?>">Se connecter</a></div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
