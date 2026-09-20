<?php
require_once __DIR__ . '/includes/functions.php';
require_role('entreprise');
$u = user();
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $t = trim($_POST['titre'] ?? '');
    $c = trim($_POST['categorie'] ?? '');
    $d = trim($_POST['description'] ?? '');
    if (!$t || !$c || !$d) {
        $erreur = "Le titre, la catégorie et la description sont obligatoires.";
    } else {
        db()->prepare("INSERT INTO projets (entreprise_id,titre,categorie,description,competences,budget_min,budget_max,delai,date_limite,statut)
                       VALUES (?,?,?,?,?,?,?,?,?,'nouveau')")
            ->execute([$u['id'], $t, $c, $d, trim($_POST['competences'] ?? ''),
                       (int)($_POST['budget_min'] ?? 0), (int)($_POST['budget_max'] ?? 0),
                       trim($_POST['delai'] ?? ''), trim($_POST['date_limite'] ?? '')]);
        $pid = db()->lastInsertId();
        notify($u['id'], "Votre projet « $t » a bien été enregistré. Réponse sous 48 h.", 'projet.php?id='.$pid);
        notify(1, "Nouveau projet à analyser : « $t » (".($u['societe'] ?: $u['nom']).").", 'admin-matching.php?id='.$pid);
        db()->prepare("INSERT INTO messages (projet_id,expediteur_id,destinataire_id,contenu) VALUES (?,1,?,?)")
            ->execute([$pid, $u['id'],
              "Bonjour, nous avons bien reçu votre projet « $t ». Notre équipe analyse votre besoin et revient vers vous sous 48 heures avec une proposition d'expert et une estimation budgétaire."]);
        flash("Projet envoyé. Notre équipe revient vers vous sous 48 heures.");
        header('Location: projet.php?id='.$pid); exit;
    }
}

$titre = "Nouveau projet";
require_once __DIR__ . '/includes/header.php';
?>
<div class="app">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">

  <div class="wizard">
    <div class="page-head">
      <div>
        <h1>Décrire un nouveau projet</h1>
        <p>5 étapes guidées. Vous n'avez pas besoin de connaître le détail technique.</p>
      </div>
    </div>

    <?php if ($erreur): ?><div class="alert alert-error"><?= e($erreur) ?></div><?php endif; ?>

    <form method="post" id="wizard">
      <?= csrf_field() ?>

      <div class="wizard-bar">
        <div class="wz-step active"><div class="n">1</div><div class="t">Le besoin</div></div>
        <div class="wz-step"><div class="n">2</div><div class="t">Description</div></div>
        <div class="wz-step"><div class="n">3</div><div class="t">Compétences</div></div>
        <div class="wz-step"><div class="n">4</div><div class="t">Budget &amp; délai</div></div>
        <div class="wz-step"><div class="n">5</div><div class="t">Récapitulatif</div></div>
      </div>

      <div class="panel">
        <div class="panel-body">

          <!-- Étape 1 -->
          <div class="wz-panel active">
            <h3 class="mb-1">Quel est votre besoin ?</h3>
            <p class="small muted mb-3">Donnez un intitulé clair et choisissez le domaine concerné.</p>
            <div class="field">
              <label for="titre">Titre du projet *</label>
              <input type="text" id="titre" name="titre" class="input" required
                     placeholder="Ex. Refonte de notre site vitrine corporate">
              <div class="hint">Une phrase suffit. Votre chargé de compte affinera avec vous.</div>
            </div>
            <div class="field">
              <label for="categorie">Domaine *</label>
              <select id="categorie" name="categorie" class="select" required>
                <option value="">Sélectionner un domaine…</option>
                <?php foreach (['Développement Web','Développement Mobile','Design UI/UX','Marketing Digital','Data & Analytics','Data & Automatisation','Rédaction & Contenu','Cybersécurité','Autre'] as $c): ?>
                  <option value="<?= e($c) ?>"><?= e($c) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Étape 2 -->
          <div class="wz-panel">
            <h3 class="mb-1">Décrivez le projet</h3>
            <p class="small muted mb-3">Contexte, objectif attendu et contraintes éventuelles. Pas de jargon nécessaire.</p>
            <div class="field">
              <label for="description">Description détaillée *</label>
              <textarea id="description" name="description" class="textarea" required data-counter="cnt"
                        placeholder="Quel problème cherchez-vous à résoudre ? Qui utilisera le résultat ? Quels sont vos critères de réussite ?"></textarea>
              <div class="hint" id="cnt">0 caractères</div>
            </div>
            <div class="alert alert-info" style="margin:0">
              💡 Plus votre description est précise, plus notre proposition sera juste dès le premier échange.
            </div>
          </div>

          <!-- Étape 3 -->
          <div class="wz-panel">
            <h3 class="mb-1">Compétences attendues</h3>
            <p class="small muted mb-3">Si vous ne savez pas, passez cette étape : nous les déterminerons pour vous.</p>
            <div class="field">
              <input type="hidden" id="competences" name="competences" value="">
              <div class="tagbox" data-input="competences">
                <?php foreach (['PHP','JavaScript','React','Vue','Python','MySQL','API REST','WordPress','Flutter','Swift','Kotlin','Firebase','Figma','UI Design','UX Research','Design System','SEO','Rédaction','Analytics','Content Strategy','SQL','Data Viz','Automatisation','Cybersécurité'] as $t): ?>
                  <span class="tag-opt"><?= $t ?></span>
                <?php endforeach; ?>
              </div>
              <div class="hint">Cliquez pour sélectionner. Ces compétences alimentent notre moteur de matching.</div>
            </div>
          </div>

          <!-- Étape 4 -->
          <div class="wz-panel">
            <h3 class="mb-1">Budget et délai</h3>
            <p class="small muted mb-3">Une fourchette indicative suffit. Le devis ferme sera établi après cadrage.</p>
            <div class="field">
              <label>Fourchette budgétaire</label>

              <div class="range-wrap" id="budgetRange">
                <div class="range-values">
                  <div class="range-val">
                    <span class="range-lbl">Minimum</span>
                    <strong id="budgetMinTxt">500 €</strong>
                  </div>
                  <div class="range-val" style="text-align:right">
                    <span class="range-lbl">Maximum</span>
                    <strong id="budgetMaxTxt">2 500 €</strong>
                  </div>
                </div>

                <div class="range-track">
                  <div class="range-fill" id="budgetFill"></div>
                  <input type="range" id="budget_min" name="budget_min" min="1" max="5000" step="1" value="500" aria-label="Budget minimum">
                  <input type="range" id="budget_max" name="budget_max" min="1" max="5000" step="1" value="2500" aria-label="Budget maximum">
                </div>

                <div class="range-scale">
                  <span>1 €</span><span>1 250 €</span><span>2 500 €</span><span>3 750 €</span><span>5 000 €</span>
                </div>
              </div>

              <div class="hint">Faites glisser les deux curseurs pour définir votre fourchette, entre 1 € et 5 000 €.</div>
            </div>
            <div class="field-row">
              <div class="field">
                <label for="delai">Durée souhaitée</label>
                <select id="delai" name="delai" class="select">
                  <option value="">Sélectionner…</option>
                  <?php foreach (['Moins de 1 mois','1 mois','2 mois','3 mois','4 mois','6 mois','Plus de 6 mois','Récurrent'] as $d): ?>
                    <option><?= $d ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label for="date_limite">Date de livraison souhaitée</label>
                <input type="date" id="date_limite" name="date_limite" class="input">
              </div>
            </div>
            <div class="alert alert-warn" style="margin:0">
              La publication du projet et l'étude sont gratuites. Aucun engagement à ce stade.
            </div>
          </div>

          <!-- Étape 5 -->
          <div class="wz-panel">
            <h3 class="mb-1">Récapitulatif</h3>
            <p class="small muted mb-3">Vérifiez les informations avant envoi à notre équipe.</p>
            <div class="recap" id="recapBox"></div>
            <div class="alert alert-info mt-3" style="margin-bottom:0">
              ✅ Après envoi, votre chargé de compte analyse le besoin et vous répond sous 48 heures ouvrées
              avec un profil d'expert recommandé et une estimation budgétaire.
            </div>
          </div>

        </div>
      </div>

      <div class="wz-nav">
        <button type="button" class="btn btn-ghost" id="wzPrev">← Précédent</button>
        <div style="display:flex;gap:10px">
          <a href="dashboard-entreprise.php" class="btn btn-ghost">Annuler</a>
          <button type="button" class="btn btn-primary" id="wzNext">Continuer →</button>
          <button type="submit" class="btn btn-primary" id="wzSubmit" style="display:none">Envoyer le projet</button>
        </div>
      </div>
    </form>
  </div>

</main>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
