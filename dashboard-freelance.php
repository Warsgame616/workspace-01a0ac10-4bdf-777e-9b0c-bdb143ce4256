<?php
require_once __DIR__ . '/includes/functions.php';
require_role('freelance');
$u = user();

$st = db()->prepare("SELECT p.*, e.societe FROM projets p
                     LEFT JOIN users e ON e.id = p.entreprise_id
                     WHERE p.freelance_id = ? ORDER BY p.id DESC");
$st->execute([$u['id']]);
$missions = $st->fetchAll();

$en_cours = count(array_filter($missions, fn($p) => in_array($p['statut'], ['attribue','en_cours','livraison'])));
$termine  = count(array_filter($missions, fn($p) => $p['statut'] === 'termine'));
$revenus  = 0;
foreach ($missions as $m) { if ($m['statut']==='termine') { $revenus += $m['montant_final'] * (1 - COMMISSION_RATE); } }

// Propositions de mission : projets non attribués où ce freelance figure bien classé
$ouverts = db()->query("SELECT * FROM projets WHERE freelance_id IS NULL AND statut IN ('nouveau','analyse')")->fetchAll();
$propositions = [];
foreach ($ouverts as $p) {
    foreach (calculer_matching($p) as $m) {
        if ($m['freelance']['id'] == $u['id'] && $m['score'] >= 45) {
            $propositions[] = ['projet' => $p, 'score' => $m['score']];
        }
    }
}
usort($propositions, fn($a,$b) => $b['score'] <=> $a['score']);

$titre = "Tableau de bord";
require_once __DIR__ . '/includes/header.php';
?>
<div class="app">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">

  <div class="page-head">
    <div>
      <h1>Bonjour <?= e($u['prenom'] ?: $u['nom']) ?> 👋</h1>
      <p><?= e($u['titre_pro']) ?> — vos missions et propositions en cours.</p>
    </div>
    <a href="profil.php" class="btn btn-ghost">Mettre à jour mon profil</a>
  </div>

  <?php if ($f = flash()): ?><div class="alert alert-<?= e($f['t']) ?>"><?= e($f['m']) ?></div><?php endif; ?>

  <div class="grid grid-4 mb-4">
    <div class="stat"><div class="lbl">Missions en cours</div><div class="val" style="color:var(--blue)"><?= $en_cours ?></div><div class="sub">actives actuellement</div></div>
    <div class="stat"><div class="lbl">Missions terminées</div><div class="val"><?= $termine ?></div><div class="sub">sur la plateforme</div></div>
    <div class="stat"><div class="lbl">Note moyenne</div><div class="val"><?= number_format($u['note_moyenne'],1,',','') ?> <span style="font-size:1rem;color:var(--muted)">/5</span></div><div class="sub stars"><?= str_repeat('★', (int)round($u['note_moyenne'])) ?></div></div>
    <div class="stat accent"><div class="lbl">Revenus nets perçus</div><div class="val"><?= euros($revenus) ?></div><div class="sub">après commission de 20 %</div></div>
  </div>

  <div class="grid" style="grid-template-columns:1.7fr 1fr;gap:24px;align-items:start">
    <div class="grid" style="gap:24px">

      <div class="panel">
        <div class="panel-head">
          <h3>Propositions de mission</h3>
          <span class="badge badge-blue"><?= count($propositions) ?> proposition<?= count($propositions)>1?'s':'' ?></span>
        </div>
        <?php if (!$propositions): ?>
          <div class="empty" style="padding:36px 24px">
            <div class="ico">🔎</div>
            <h3>Aucune proposition pour l'instant</h3>
            <p>Nous vous contactons dès qu'un projet correspond à votre profil.</p>
          </div>
        <?php else: ?>
          <div class="panel-body" style="padding:8px 22px">
          <?php foreach ($propositions as $pr): $p = $pr['projet']; $s = $pr['score']; ?>
            <div class="match-row">
              <div class="score <?= $s>=75?'high':($s>=50?'mid':'low') ?>"><?= $s ?>%</div>
              <div style="flex:1;min-width:0">
                <h4><?= e($p['titre']) ?></h4>
                <p class="small muted"><?= e($p['categorie']) ?> · <?= euros($p['budget_min']) ?> – <?= euros_max($p['budget_max']) ?> · <?= e($p['delai']) ?></p>
                <div class="crit">
                  <?php foreach (array_filter(array_map('trim', explode(',', $p['competences']))) as $c): ?>
                    <span><?= e($c) ?></span>
                  <?php endforeach; ?>
                </div>
              </div>
              <a href="messages.php" class="btn btn-ghost btn-sm">Je suis intéressé</a>
            </div>
          <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="panel">
        <div class="panel-head">
          <h3>Mes missions</h3>
          <a href="projets.php" class="btn btn-ghost btn-sm">Tout voir</a>
        </div>
        <?php if (!$missions): ?>
          <div class="empty" style="padding:36px 24px">
            <div class="ico">💼</div><h3>Aucune mission attribuée</h3>
            <p>Complétez votre profil pour améliorer votre score de matching.</p>
          </div>
        <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Mission</th><th>Client</th><th>Statut</th><th>Avancement</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($missions as $p): ?>
              <tr>
                <td>
                  <div class="t-title"><?= e($p['titre']) ?></div>
                  <div class="t-sub">Échéance : <?= date_fr($p['date_limite']) ?></div>
                </td>
                <td class="small"><?= e($p['societe'] ?: 'Client confidentiel') ?></td>
                <td><span class="badge <?= statut_classe($p['statut']) ?>"><?= statut_label($p['statut']) ?></span></td>
                <td>
                  <div class="bar <?= $p['avancement']>=100?'green':'' ?>"><i style="width:<?= (int)$p['avancement'] ?>%"></i></div>
                  <div class="t-sub"><?= (int)$p['avancement'] ?> %</div>
                </td>
                <td><a href="projet.php?id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm">Détail</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

    </div>

    <div class="grid" style="gap:24px">
      <div class="panel">
        <div class="panel-head"><h3>Mon profil</h3></div>
        <div class="panel-body">
          <div class="flex-center mb-3">
            <span class="avatar lg"><?= initiales($u) ?></span>
            <div>
              <h4><?= e($u['prenom'].' '.$u['nom']) ?></h4>
              <p class="small muted"><?= e($u['titre_pro']) ?></p>
            </div>
          </div>
          <div class="recap">
            <div class="recap-row"><span>TJM</span><strong><?= euros($u['tjm']) ?></strong></div>
            <div class="recap-row"><span>Expérience</span><strong><?= (int)$u['experience'] ?> ans</strong></div>
            <div class="recap-row"><span>Disponibilité</span><strong><?= ucfirst(e($u['disponibilite'])) ?></strong></div>
            <div class="recap-row"><span>Missions réalisées</span><strong><?= (int)$u['nb_missions'] ?></strong></div>
          </div>
          <div class="crit mt-3">
            <?php foreach (array_filter(array_map('trim', explode(',', $u['competences']))) as $c): ?>
              <span><?= e($c) ?></span>
            <?php endforeach; ?>
          </div>
          <a href="profil.php" class="btn btn-ghost btn-block mt-3">Modifier mon profil</a>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head"><h3>Votre contact WorkConnects</h3></div>
        <div class="panel-body">
          <div class="flex-center mb-3">
            <span class="avatar dark">SD</span>
            <div><h4 style="font-size:.9375rem">Sophie Dupont</h4><p class="small muted">Responsable de compte</p></div>
          </div>
          <p class="small muted mb-3">Toutes vos questions passent par votre chargé de compte. Aucun contact direct avec le client final.</p>
          <a href="messages.php" class="btn btn-primary btn-block">💬 Envoyer un message</a>
        </div>
      </div>
    </div>
  </div>

</main>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
