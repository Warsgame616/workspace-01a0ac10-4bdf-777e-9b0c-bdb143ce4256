<?php
require_once __DIR__ . '/includes/functions.php';
if (!is_logged()) { header('Location: connexion.php'); exit; }
$u = user();

if ($u['role']==='entreprise') {
    $st = db()->prepare("SELECT f.*, p.titre FROM factures f JOIN projets p ON p.id=f.projet_id WHERE p.entreprise_id=? ORDER BY f.id DESC");
    $st->execute([$u['id']]);
} elseif ($u['role']==='freelance') {
    $st = db()->prepare("SELECT f.*, p.titre FROM factures f JOIN projets p ON p.id=f.projet_id WHERE p.freelance_id=? ORDER BY f.id DESC");
    $st->execute([$u['id']]);
} else {
    $st = db()->query("SELECT f.*, p.titre, e.societe FROM factures f JOIN projets p ON p.id=f.projet_id LEFT JOIN users e ON e.id=p.entreprise_id ORDER BY f.id DESC");
}
$factures = $st->fetchAll();

$tot_ht  = array_sum(array_column($factures,'montant_ht'));
$tot_com = array_sum(array_column($factures,'commission'));
$tot_net = array_sum(array_column($factures,'montant_freelance'));

$titre = $u['role']==='freelance' ? "Mes revenus" : "Facturation";
require_once __DIR__ . '/includes/header.php';
?>
<div class="app">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">

  <div class="page-head">
    <div><h1><?= e($titre) ?></h1><p><?= count($factures) ?> facture<?= count($factures)>1?'s':'' ?></p></div>
  </div>

  <div class="grid grid-3 mb-4">
    <?php if ($u['role']==='entreprise'): ?>
      <div class="stat"><div class="lbl">Total facturé HT</div><div class="val"><?= euros($tot_ht) ?></div><div class="sub">tous projets</div></div>
      <div class="stat"><div class="lbl">Factures payées</div><div class="val"><?= count(array_filter($factures, fn($f)=>$f['statut']==='payee')) ?></div><div class="sub">réglées</div></div>
      <div class="stat accent"><div class="lbl">En attente</div><div class="val"><?= euros(array_sum(array_map(fn($f)=>$f['statut']!=='payee'?$f['montant_ht']:0, $factures))) ?></div><div class="sub">à régler</div></div>
    <?php elseif ($u['role']==='freelance'): ?>
      <div class="stat"><div class="lbl">Chiffre d'affaires brut</div><div class="val"><?= euros($tot_ht) ?></div><div class="sub">missions facturées</div></div>
      <div class="stat"><div class="lbl">Commission plateforme</div><div class="val" style="color:var(--muted)"><?= euros($tot_com) ?></div><div class="sub"><?= param('commission',20) ?> %</div></div>
      <div class="stat accent"><div class="lbl">Revenus nets</div><div class="val"><?= euros($tot_net) ?></div><div class="sub">versés ou à venir</div></div>
    <?php else: ?>
      <div class="stat"><div class="lbl">Volume facturé</div><div class="val"><?= euros($tot_ht) ?></div><div class="sub">toutes entreprises</div></div>
      <div class="stat accent"><div class="lbl">Commissions WorkConnects</div><div class="val"><?= euros($tot_com) ?></div><div class="sub">marge brute</div></div>
      <div class="stat"><div class="lbl">Reversé aux experts</div><div class="val"><?= euros($tot_net) ?></div><div class="sub">net freelances</div></div>
    <?php endif; ?>
  </div>

  <div class="alert alert-info">
    💳 L'architecture de paiement est en place (séquestre, échéancier, reversement, facturation automatique)
    mais aucun flux financier réel n'est activé dans cette version.
  </div>

  <div class="panel">
    <div class="panel-head"><h3>Historique de facturation</h3></div>
    <?php if (!$factures): ?>
      <div class="empty"><div class="ico">🧾</div><h3>Aucune facture</h3><p>Les factures sont générées automatiquement à la clôture d'un projet.</p></div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>Facture</th><th>Projet</th>
          <?php if ($u['role']==='admin'): ?><th>Client</th><?php endif; ?>
          <th>Montant HT</th>
          <?php if ($u['role']!=='entreprise'): ?><th>Commission</th><th>Net expert</th><?php endif; ?>
          <th>Statut</th><th>Date</th>
        </tr></thead>
        <tbody>
        <?php foreach ($factures as $f): ?>
          <tr>
            <td class="t-title"><?= e($f['numero']) ?></td>
            <td class="small"><?= e($f['titre']) ?></td>
            <?php if ($u['role']==='admin'): ?><td class="small"><?= e($f['societe'] ?? '—') ?></td><?php endif; ?>
            <td class="strong"><?= euros($f['montant_ht']) ?></td>
            <?php if ($u['role']!=='entreprise'): ?>
              <td class="small muted">−<?= euros($f['commission']) ?></td>
              <td class="strong" style="color:var(--green)"><?= euros($f['montant_freelance']) ?></td>
            <?php endif; ?>
            <td><span class="badge <?= $f['statut']==='payee'?'badge-green':'badge-amber' ?>"><?= $f['statut']==='payee'?'Payée':'En attente' ?></span></td>
            <td class="small muted"><?= date_fr($f['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</main>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
