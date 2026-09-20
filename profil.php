<?php
require_once __DIR__ . '/includes/functions.php';
if (!is_logged()) { header('Location: connexion.php'); exit; }
$u = user();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    if (isset($_POST['form_profil'])) {
        db()->prepare("UPDATE users SET nom=?,prenom=?,telephone=?,societe=?,siret=?,secteur=?,taille=?,titre_pro=?,bio=?,competences=?,tjm=?,experience=?,disponibilite=? WHERE id=?")
            ->execute([
              trim($_POST['nom']), trim($_POST['prenom']), trim($_POST['telephone']),
              trim($_POST['societe'] ?? ''), trim($_POST['siret'] ?? ''), trim($_POST['secteur'] ?? ''), trim($_POST['taille'] ?? ''),
              trim($_POST['titre_pro'] ?? ''), trim($_POST['bio'] ?? ''), trim($_POST['competences'] ?? ''),
              (int)($_POST['tjm'] ?? 0), (int)($_POST['experience'] ?? 0), $_POST['disponibilite'] ?? 'disponible',
              $u['id']
            ]);
        flash("Profil mis à jour.");
    }
    if (isset($_POST['form_mdp'])) {
        if (!password_verify($_POST['ancien'] ?? '', $u['password_hash'])) { flash("Mot de passe actuel incorrect.", 'error'); }
        elseif (strlen($_POST['nouveau'] ?? '') < 6) { flash("Le nouveau mot de passe doit faire 6 caractères minimum.", 'error'); }
        elseif ($_POST['nouveau'] !== $_POST['nouveau2']) { flash("Les deux mots de passe ne correspondent pas.", 'error'); }
        else {
            db()->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($_POST['nouveau'], PASSWORD_DEFAULT), $u['id']]);
            flash("Mot de passe modifié.");
        }
    }
    header('Location: profil.php'); exit;
}

$titre = "Mon profil";
require_once __DIR__ . '/includes/header.php';
?>
<div class="app">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">

  <div class="page-head">
    <div class="profile-head">
      <span class="avatar lg"><?= initiales($u) ?></span>
      <div>
        <h1 style="font-size:1.5rem"><?= e(trim($u['prenom'].' '.$u['nom'])) ?></h1>
        <p><?= e($u['role']==='entreprise' ? ($u['societe'] ?: 'Entreprise') : ($u['role']==='freelance' ? $u['titre_pro'] : 'Équipe WorkConnects')) ?> · <?= e($u['email']) ?></p>
      </div>
    </div>
  </div>

  <?php if ($f = flash()): ?><div class="alert alert-<?= e($f['t']) ?>"><?= e($f['m']) ?></div><?php endif; ?>

  <div data-tabs class="tabs">
    <div class="tab active" data-tab="p1">Informations</div>
    <div class="tab" data-tab="p2">Sécurité</div>
    <div class="tab" data-tab="p3">Confidentialité / RGPD</div>
  </div>

  <div data-panel="p1" class="tab-panel active">
    <form method="post" style="max-width:720px">
      <?= csrf_field() ?><input type="hidden" name="form_profil" value="1">
      <div class="panel">
        <div class="panel-body">
          <div class="field-row">
            <div class="field"><label for="prenom">Prénom</label><input type="text" id="prenom" name="prenom" class="input" value="<?= e($u['prenom']) ?>"></div>
            <div class="field"><label for="nom">Nom</label><input type="text" id="nom" name="nom" class="input" value="<?= e($u['nom']) ?>"></div>
          </div>
          <div class="field"><label for="telephone">Téléphone</label><input type="tel" id="telephone" name="telephone" class="input" value="<?= e($u['telephone']) ?>"></div>

          <?php if ($u['role']==='entreprise'): ?>
            <div class="field"><label for="societe">Raison sociale</label><input type="text" id="societe" name="societe" class="input" value="<?= e($u['societe']) ?>"></div>
            <div class="field-row">
              <div class="field"><label for="siret">SIRET</label><input type="text" id="siret" name="siret" class="input" value="<?= e($u['siret']) ?>"></div>
              <div class="field"><label for="secteur">Secteur</label><input type="text" id="secteur" name="secteur" class="input" value="<?= e($u['secteur']) ?>"></div>
            </div>
            <div class="field"><label for="taille">Effectif</label><input type="text" id="taille" name="taille" class="input" value="<?= e($u['taille']) ?>"></div>
            <div class="field"><label for="bio">Présentation de l'entreprise</label><textarea id="bio" name="bio" class="textarea"><?= e($u['bio']) ?></textarea></div>
          <?php elseif ($u['role']==='freelance'): ?>
            <div class="field"><label for="titre_pro">Titre professionnel</label><input type="text" id="titre_pro" name="titre_pro" class="input" value="<?= e($u['titre_pro']) ?>"></div>
            <div class="field"><label for="bio">Présentation</label><textarea id="bio" name="bio" class="textarea"><?= e($u['bio']) ?></textarea></div>
            <div class="field">
              <label>Compétences</label>
              <input type="hidden" id="competences" name="competences" value="<?= e($u['competences']) ?>">
              <div class="tagbox" data-input="competences">
                <?php
                $all = ['PHP','JavaScript','React','Vue','Python','MySQL','API REST','WordPress','Flutter','Swift','Kotlin','Firebase','Figma','UI Design','UX Research','Design System','SEO','Rédaction','Analytics','Content Strategy','SQL','Data Viz'];
                foreach (array_unique(array_merge($all, array_filter(array_map('trim', explode(',', $u['competences']))))) as $t): ?>
                  <span class="tag-opt"><?= e($t) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="field-row">
              <div class="field"><label for="tjm">TJM (€)</label><input type="number" id="tjm" name="tjm" class="input" value="<?= (int)$u['tjm'] ?>"></div>
              <div class="field"><label for="experience">Années d'expérience</label><input type="number" id="experience" name="experience" class="input" value="<?= (int)$u['experience'] ?>"></div>
            </div>
            <div class="field">
              <label for="disponibilite">Disponibilité</label>
              <select id="disponibilite" name="disponibilite" class="select">
                <?php foreach (['disponible'=>'Disponible immédiatement','partiel'=>'Partiellement disponible','occupe'=>'Actuellement en mission'] as $k=>$v): ?>
                  <option value="<?= $k ?>" <?= $u['disponibilite']===$k?'selected':'' ?>><?= $v ?></option>
                <?php endforeach; ?>
              </select>
              <div class="hint">Ce paramètre influence directement votre score de matching.</div>
            </div>
          <?php endif; ?>

          <button type="submit" class="btn btn-primary">Enregistrer</button>
        </div>
      </div>
    </form>
  </div>

  <div data-panel="p2" class="tab-panel">
    <form method="post" style="max-width:520px">
      <?= csrf_field() ?><input type="hidden" name="form_mdp" value="1">
      <div class="panel"><div class="panel-body">
        <div class="field"><label for="ancien">Mot de passe actuel</label><input type="password" id="ancien" name="ancien" class="input" required></div>
        <div class="field"><label for="nouveau">Nouveau mot de passe</label><input type="password" id="nouveau" name="nouveau" class="input" required minlength="6"></div>
        <div class="field"><label for="nouveau2">Confirmation</label><input type="password" id="nouveau2" name="nouveau2" class="input" required minlength="6"></div>
        <button type="submit" class="btn btn-primary">Modifier le mot de passe</button>
      </div></div>
    </form>
  </div>

  <div data-panel="p3" class="tab-panel">
    <div class="panel" style="max-width:720px"><div class="panel-body">
      <h3 class="mb-2">Vos données personnelles</h3>
      <p class="small mb-3">
        Conformément au RGPD, vos données sont traitées uniquement dans le cadre de la mise en relation
        et du suivi de vos projets. Elles ne sont ni revendues ni transmises à des tiers non impliqués.
      </p>
      <div class="recap mb-3">
        <div class="recap-row"><span>Données collectées</span><strong>Identité, contact, profil professionnel</strong></div>
        <div class="recap-row"><span>Finalité</span><strong>Mise en relation et suivi de projet</strong></div>
        <div class="recap-row"><span>Durée de conservation</span><strong>3 ans après le dernier contact</strong></div>
        <div class="recap-row"><span>Visibilité inter-utilisateurs</span><strong>Aucune donnée privée exposée</strong></div>
      </div>
      <div class="alert alert-info">
        🔒 Les entreprises ne voient jamais les coordonnées des freelances, et inversement.
        Tous les échanges transitent par votre chargé de compte WorkConnects.
      </div>
      <div class="flex gap-1 wrap">
        <a href="mentions.php#rgpd" class="btn btn-ghost btn-sm">Politique de confidentialité</a>
        <a href="messages.php" class="btn btn-ghost btn-sm">Demander l'export de mes données</a>
        <a href="messages.php" class="btn btn-ghost btn-sm">Demander la suppression du compte</a>
      </div>
    </div></div>
  </div>

</main>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
