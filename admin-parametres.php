<?php
require_once __DIR__ . '/includes/functions.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_check();
    $st = db()->prepare("INSERT INTO parametres (cle,valeur) VALUES (?,?) ON CONFLICT(cle) DO UPDATE SET valeur=excluded.valeur");
    foreach (['coef_competences','coef_budget','coef_dispo','coef_experience','coef_note','commission'] as $k) {
        $st->execute([$k, max(0, (int)($_POST[$k] ?? 0))]);
    }
    flash("Paramètres enregistrés. Le moteur de matching utilise désormais ces coefficients.");
    header('Location: admin-parametres.php'); exit;
}

$titre = "Paramètres";
require_once __DIR__ . '/includes/header.php';
$total = (int)param('coef_competences',40)+(int)param('coef_budget',25)+(int)param('coef_dispo',15)+(int)param('coef_experience',10)+(int)param('coef_note',10);
?>
<div class="app">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">

  <div class="page-head">
    <div><h1>Paramètres de la plateforme</h1><p>Configuration du moteur de matching et du modèle économique.</p></div>
  </div>

  <?php if ($f = flash()): ?><div class="alert alert-<?= e($f['t']) ?>"><?= e($f['m']) ?></div><?php endif; ?>

  <form method="post" style="max-width:720px">
    <?= csrf_field() ?>

    <div class="panel mb-3">
      <div class="panel-head">
        <h3>Coefficients du moteur de matching</h3>
        <span class="badge <?= $total===100?'badge-green':'badge-amber' ?>">Total : <?= $total ?> %</span>
      </div>
      <div class="panel-body">
        <p class="small muted mb-3">
          Ces coefficients pondèrent les critères mesurables utilisés pour classer les experts.
          Le score final est une moyenne pondérée normalisée sur 100.
        </p>
        <?php
        $ch = [
          ['coef_competences','Correspondance des compétences','Part des compétences requises présentes sur le profil.'],
          ['coef_budget','Adéquation budgétaire','Compatibilité du TJM projeté avec le budget du projet.'],
          ['coef_dispo','Disponibilité','Disponible = 100, partiel = 50, occupé = 10.'],
          ['coef_experience','Années d\'expérience','Normalisé sur 10 ans maximum.'],
          ['coef_note','Note moyenne','Moyenne des évaluations sur 5, ramenée sur 100.'],
        ];
        foreach ($ch as $c): ?>
          <div class="field">
            <label for="<?= $c[0] ?>"><?= e($c[1]) ?></label>
            <div class="flex-center">
              <input type="number" id="<?= $c[0] ?>" name="<?= $c[0] ?>" class="input" min="0" max="100"
                     value="<?= (int)param($c[0]) ?>" style="max-width:110px">
              <span class="small muted">%</span>
              <div class="bar" style="flex:1"><i style="width:<?= min(100,(int)param($c[0])*2) ?>%"></i></div>
            </div>
            <div class="hint"><?= e($c[2]) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="panel mb-3">
      <div class="panel-head"><h3>Modèle économique</h3></div>
      <div class="panel-body">
        <div class="field">
          <label for="commission">Taux de commission WorkConnects (%)</label>
          <input type="number" id="commission" name="commission" class="input" min="0" max="100"
                 value="<?= (int)param('commission',20) ?>" style="max-width:140px">
          <div class="hint">Appliqué automatiquement lors de la génération des factures à la clôture d'un projet.</div>
        </div>
        <div class="alert alert-info" style="margin:0">
          💳 <strong>Architecture de paiement</strong> — les points d'intégration sont prévus
          (séquestre, échéancier, reversement expert, génération de facture) mais aucun flux de paiement réel
          n'est activé dans cette version. Le branchement d'un prestataire de paiement se fait au niveau de la
          table <code>factures</code> sans modification du reste de l'application.
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg">Enregistrer les paramètres</button>
  </form>

</main>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
