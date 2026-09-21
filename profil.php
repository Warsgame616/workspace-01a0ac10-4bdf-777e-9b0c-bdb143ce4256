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
    // Ajout d'une réalisation au portfolio (freelance)
    if (isset($_POST['form_portfolio']) && $u['role'] === 'freelance') {
        $r = traiter_upload($_FILES['image'] ?? [], 'portfolio', ext_img_autorisees());
        if ($r['ok']) {
            db()->prepare("INSERT INTO portfolio (user_id,titre,description,image) VALUES (?,?,?,?)")
                ->execute([$u['id'], trim($_POST['pf_titre'] ?? ''), trim($_POST['pf_desc'] ?? ''), $r['chemin']]);
            flash("Réalisation ajoutée à votre portfolio.");
        } else {
            flash($r['erreur'], 'error');
        }
    }
    // Suppression d'une réalisation
    if (isset($_POST['suppr_pf']) && $u['role'] === 'freelance') {
        $q = db()->prepare("SELECT * FROM portfolio WHERE id=? AND user_id=?");
        $q->execute([(int)$_POST['suppr_pf'], $u['id']]);
        if ($pf = $q->fetch()) {
            @unlink(__DIR__.'/uploads/'.$pf['image']);
            db()->prepare("DELETE FROM portfolio WHERE id=?")->execute([$pf['id']]);
            flash("Réalisation supprimée.");
        }
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
        <p><?= e(est_client($u['role']) ? ($u['societe'] ?: role_label($u['role'])) : ($u['role']==='freelance' ? $u['titre_pro'] : 'Équipe WorkConnects')) ?> · <?= e($u['email']) ?></p>
      </div>
    </div>
  </div>

  <?php if ($f = flash()): ?><div class="alert alert-<?= e($f['t']) ?>"><?= e($f['m']) ?></div><?php endif; ?>

  <div data-tabs class="tabs">
    <div class="tab active" data-tab="p1">Informations</div>
    <?php if ($u['role']==='freelance'): ?><div class="tab" data-tab="p4">Portfolio</div><?php endif; ?>
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

          <?php if (est_client($u['role'])): ?>
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
              <label>Vos compétences</label>
              <?php champ_competences($u['competences']); ?>
              <div class="hint">Elles alimentent directement votre score de matching.</div>
            </div>
            <div class="field-row">
              <div class="field"><label for="tjm">Tarif journalier (€ / jour)</label><input type="number" id="tjm" name="tjm" class="input" min="0" step="10" value="<?= (int)$u['tjm'] ?>"></div>
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

  <?php if ($u['role']==='freelance'): $pfs = portfolio_de($u['id']); ?>
  <div data-panel="p4" class="tab-panel">
    <div class="panel mb-3" style="max-width:860px">
      <div class="panel-head">
        <h3>Ajouter une réalisation</h3>
        <span class="badge badge-gray"><?= count($pfs) ?> visuel<?= count($pfs)>1?'s':'' ?></span>
      </div>
      <div class="panel-body">
        <form method="post" enctype="multipart/form-data">
          <?= csrf_field() ?><input type="hidden" name="form_portfolio" value="1">
          <div class="field-row">
            <div class="field">
              <label for="pf_titre">Titre de la réalisation</label>
              <input type="text" id="pf_titre" name="pf_titre" class="input" placeholder="Ex. Refonte e-commerce Maison Duval">
            </div>
            <div class="field">
              <label for="pf_desc">Courte description</label>
              <input type="text" id="pf_desc" name="pf_desc" class="input" placeholder="Ex. Site sur mesure, +38 % de conversion">
            </div>
          </div>
          <div class="field">
            <label for="pfImage">Visuel *</label>
            <input type="file" name="image" id="pfImage" accept="image/jpeg,image/png,image/gif,image/webp" required
                   class="input" onchange="previewImg(this,'pfPreview')">
            <div class="hint">JPG, PNG, GIF ou WEBP — 8 Mo maximum.</div>
            <img id="pfPreview" class="pf-preview" alt="">
          </div>
          <button type="submit" class="btn btn-primary">Ajouter au portfolio</button>
        </form>
      </div>
    </div>

    <div class="panel" style="max-width:860px">
      <div class="panel-head"><h3>Mes réalisations</h3></div>
      <?php if (!$pfs): ?>
        <div class="empty" style="padding:40px">
          <div class="ico">🖼️</div>
          <h3>Votre portfolio est vide</h3>
          <p>Ajoutez des visuels de vos projets : ils sont transmis aux clients lors de nos recommandations.</p>
        </div>
      <?php else: ?>
        <div class="panel-body">
          <div class="pf-grid">
            <?php foreach ($pfs as $pf): ?>
              <figure class="pf-card">
                <img src="uploads/<?= e($pf['image']) ?>" alt="<?= e($pf['titre']) ?>" loading="lazy">
                <figcaption>
                  <strong><?= e($pf['titre'] ?: 'Sans titre') ?></strong>
                  <?php if ($pf['description']): ?><span><?= e($pf['description']) ?></span><?php endif; ?>
                  <form method="post" onsubmit="return confirm('Supprimer cette réalisation ?')">
                    <?= csrf_field() ?>
                    <button name="suppr_pf" value="<?= $pf['id'] ?>" class="btn btn-ghost btn-sm">🗑 Supprimer</button>
                  </form>
                </figcaption>
              </figure>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

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
        <a href="<?= u('messages.php') ?>" class="btn btn-ghost btn-sm">Demander l'export de mes données</a>
        <a href="<?= u('messages.php') ?>" class="btn btn-ghost btn-sm">Demander la suppression du compte</a>
      </div>
    </div></div>
  </div>

</main>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
