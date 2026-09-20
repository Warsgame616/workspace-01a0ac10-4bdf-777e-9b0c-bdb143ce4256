<?php
require_once __DIR__ . '/includes/functions.php';
if (!is_logged()) { header('Location: connexion.php'); exit; }
$u = user();
$id = (int)($_GET['id'] ?? 0);

$st = db()->prepare("SELECT p.*, e.societe, e.nom AS e_nom, e.prenom AS e_prenom,
                            f.nom AS f_nom, f.prenom AS f_prenom, f.titre_pro, f.note_moyenne, f.tjm
                     FROM projets p
                     LEFT JOIN users e ON e.id = p.entreprise_id
                     LEFT JOIN users f ON f.id = p.freelance_id
                     WHERE p.id = ?");
$st->execute([$id]);
$p = $st->fetch();
if (!$p) { header('Location: ' . dashboard_url()); exit; }

// Contrôle d'accès strict : seuls le client, l'expert affecté et l'admin peuvent voir
$autorise = ($u['role']==='admin') || ($p['entreprise_id']==$u['id']) || ($p['freelance_id']==$u['id']);
if (!$autorise) { header('Location: ' . dashboard_url()); exit; }

// Mise à jour (admin uniquement)
if ($_SERVER['REQUEST_METHOD']==='POST' && $u['role']==='admin') {
    csrf_check();
    db()->prepare("UPDATE projets SET statut=?, avancement=?, montant_final=? WHERE id=?")
        ->execute([$_POST['statut'], (int)$_POST['avancement'], (int)$_POST['montant_final'], $id]);
    if ($_POST['statut']==='termine') {
        $m = (int)$_POST['montant_final'];
        $com = (int)round($m * (param('commission',20)/100));
        $ex = db()->prepare("SELECT COUNT(*) FROM factures WHERE projet_id=?"); $ex->execute([$id]);
        if (!$ex->fetchColumn()) {
            db()->prepare("INSERT INTO factures (projet_id,numero,montant_ht,commission,montant_freelance,statut) VALUES (?,?,?,?,?,'en_attente')")
                ->execute([$id, 'FA-'.date('Y').'-'.str_pad($id,4,'0',STR_PAD_LEFT), $m, $com, $m-$com]);
        }
    }
    notify($p['entreprise_id'], "Mise à jour du projet « ".$p['titre']." » : ".statut_label($_POST['statut'])." (".(int)$_POST['avancement']." %).", 'projet.php?id='.$id);
    if ($p['freelance_id']) notify($p['freelance_id'], "Mise à jour de la mission « ".$p['titre']." ».", 'projet.php?id='.$id);
    flash("Projet mis à jour.");
    header('Location: projet.php?id='.$id); exit;
}

$fs = db()->prepare("SELECT * FROM fichiers WHERE projet_id=? ORDER BY id DESC"); $fs->execute([$id]);
$fichiers = $fs->fetchAll();
$fa = db()->prepare("SELECT * FROM factures WHERE projet_id=?"); $fa->execute([$id]);
$factures = $fa->fetchAll();

$etapes = ['nouveau'=>0,'analyse'=>1,'attribue'=>2,'en_cours'=>3,'livraison'=>4,'termine'=>5];
$cur_etape = $etapes[$p['statut']] ?? 0;
$noms_etapes = [
  ["Projet reçu","Votre demande a été enregistrée."],
  ["Analyse du besoin","Cadrage par votre chargé de compte."],
  ["Expert sélectionné","Profil retenu et mission engagée."],
  ["Production en cours","Réalisation et suivi d'avancement."],
  ["Livraison &amp; recette","Contrôle qualité puis validation."],
  ["Projet terminé","Livrables validés et facturés."],
];

$titre = $p['titre'];
require_once __DIR__ . '/includes/header.php';
?>
<div class="app">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">

  <div class="page-head">
    <div>
      <p class="small muted mb-1"><a href="<?= dashboard_url() ?>" style="color:var(--blue)">← Retour au tableau de bord</a></p>
      <h1><?= e($p['titre']) ?></h1>
      <p><?= e($p['categorie']) ?> · Créé le <?= date_fr($p['created_at']) ?></p>
    </div>
    <div class="flex gap-1 wrap">
      <span class="badge <?= statut_classe($p['statut']) ?>" style="padding:8px 14px;font-size:.875rem"><?= statut_label($p['statut']) ?></span>
      <a href="messages.php" class="btn btn-primary">💬 Contacter WorkConnects</a>
    </div>
  </div>

  <?php if ($f = flash()): ?><div class="alert alert-<?= e($f['t']) ?>"><?= e($f['m']) ?></div><?php endif; ?>

  <div class="panel mb-4">
    <div class="panel-body">
      <div class="flex-between mb-2">
        <h3>Avancement global</h3>
        <strong style="font-size:1.25rem"><?= (int)$p['avancement'] ?> %</strong>
      </div>
      <div class="bar <?= $p['avancement']>=100?'green':'' ?>" style="height:10px"><i style="width:<?= (int)$p['avancement'] ?>%"></i></div>
    </div>
  </div>

  <div class="grid" style="grid-template-columns:1.6fr 1fr;gap:24px;align-items:start">
    <div class="grid" style="gap:24px">

      <div class="panel">
        <div class="panel-head"><h3>Suivi du projet</h3></div>
        <div class="panel-body">
          <div class="timeline">
            <?php foreach ($noms_etapes as $i => $et): ?>
              <div class="tl-item <?= $i < $cur_etape ? 'done' : ($i == $cur_etape ? 'current' : '') ?>">
                <h4><?= $et[0] ?></h4>
                <p><?= $et[1] ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head"><h3>Description du besoin</h3></div>
        <div class="panel-body">
          <p><?= nl2br(e($p['description'])) ?></p>
          <?php if ($p['competences']): ?>
            <h4 class="mt-3 mb-1" style="font-size:.8125rem;text-transform:uppercase;color:var(--muted)">Compétences requises</h4>
            <div class="crit">
              <?php foreach (array_filter(array_map('trim', explode(',', $p['competences']))) as $c): ?>
                <span><?= e($c) ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head">
          <h3>Fichiers &amp; livrables</h3>
          <span class="badge badge-gray"><?= count($fichiers) ?> fichier<?= count($fichiers)>1?'s':'' ?></span>
        </div>
        <?php if (!$fichiers): ?>
          <div class="empty" style="padding:32px">
            <div class="ico">📎</div>
            <h3>Aucun fichier</h3>
            <p>Les livrables apparaîtront ici au fur et à mesure de la mission.</p>
          </div>
        <?php else: ?>
          <div class="panel-body">
            <?php foreach ($fichiers as $fi): ?>
              <div class="flex-between" style="padding:10px 0;border-bottom:1px solid var(--line-2)">
                <div class="flex-center"><span>📄</span><div><strong class="small"><?= e($fi['nom']) ?></strong>
                  <div class="t-sub"><?= date_fr($fi['created_at']) ?> · <?= round($fi['taille']/1024) ?> Ko</div></div></div>
                <span class="btn btn-ghost btn-sm">Télécharger</span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($u['role']==='admin'): ?>
      <div class="panel" style="border-color:var(--blue)">
        <div class="panel-head"><h3>🛠️ Gestion WorkConnects</h3><span class="badge badge-blue">Back-office</span></div>
        <div class="panel-body">
          <form method="post">
            <?= csrf_field() ?>
            <div class="field-row">
              <div class="field">
                <label for="statut">Statut du projet</label>
                <select name="statut" id="statut" class="select">
                  <?php foreach (['nouveau','analyse','attribue','en_cours','livraison','termine','annule'] as $s): ?>
                    <option value="<?= $s ?>" <?= $p['statut']===$s?'selected':'' ?>><?= statut_label($s) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field">
                <label for="avancement">Avancement (%)</label>
                <input type="number" name="avancement" id="avancement" class="input" min="0" max="100" value="<?= (int)$p['avancement'] ?>">
              </div>
            </div>
            <div class="field">
              <label for="montant_final">Montant final validé (€ HT)</label>
              <input type="number" name="montant_final" id="montant_final" class="input" min="0" step="100" value="<?= (int)$p['montant_final'] ?>">
              <div class="hint">Le passage au statut « Terminé » génère automatiquement la facture et la commission de <?= param('commission',20) ?> %.</div>
            </div>
            <div class="flex gap-1 wrap">
              <button type="submit" class="btn btn-primary">Enregistrer</button>
              <?php if (!$p['freelance_id']): ?>
                <a href="admin-matching.php?id=<?= $id ?>" class="btn btn-ghost">🎯 Lancer le matching</a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>

    </div>

    <div class="grid" style="gap:24px">
      <div class="panel">
        <div class="panel-head"><h3>Informations</h3></div>
        <div class="panel-body">
          <div class="recap">
            <?php if ($u['role'] !== 'freelance'): ?>
              <div class="recap-row"><span>Client</span><strong><?= e($p['societe'] ?: $p['e_nom']) ?></strong></div>
            <?php endif; ?>
            <div class="recap-row"><span>Budget estimé</span><strong><?= euros($p['budget_min']) ?> – <?= euros($p['budget_max']) ?></strong></div>
            <?php if ($p['montant_final']): ?>
              <div class="recap-row"><span>Montant validé</span><strong><?= euros($p['montant_final']) ?></strong></div>
            <?php endif; ?>
            <div class="recap-row"><span>Durée</span><strong><?= e($p['delai'] ?: '—') ?></strong></div>
            <div class="recap-row"><span>Échéance</span><strong><?= date_fr($p['date_limite']) ?></strong></div>
            <div class="recap-row"><span>Statut</span><strong><span class="badge <?= statut_classe($p['statut']) ?>"><?= statut_label($p['statut']) ?></span></strong></div>
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-head"><h3>Expert affecté</h3></div>
        <div class="panel-body">
          <?php if ($p['freelance_id']): ?>
            <div class="flex-center mb-2">
              <span class="avatar lg"><?= strtoupper(mb_substr($p['f_prenom'],0,1).mb_substr($p['f_nom'],0,1)) ?></span>
              <div>
                <h4><?= $u['role']==='entreprise'
                      ? e($p['f_prenom'].' '.mb_substr($p['f_nom'],0,1).'.')
                      : e($p['f_prenom'].' '.$p['f_nom']) ?></h4>
                <p class="small muted"><?= e($p['titre_pro']) ?></p>
                <p class="small stars"><?= str_repeat('★',(int)round($p['note_moyenne'])) ?> <span class="muted"><?= number_format($p['note_moyenne'],1,',','') ?>/5</span></p>
              </div>
            </div>
            <div class="alert alert-info" style="margin:0;font-size:.8125rem">
              🔒 Les échanges passent par votre chargé de compte WorkConnects pour garantir la traçabilité et la qualité du suivi.
            </div>
          <?php else: ?>
            <div class="empty" style="padding:24px 0">
              <div class="ico">🔎</div>
              <p class="small">Sélection de l'expert en cours par notre équipe.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($factures): ?>
      <div class="panel">
        <div class="panel-head"><h3>Facturation</h3></div>
        <div class="panel-body">
          <?php foreach ($factures as $fac): ?>
            <div class="recap">
              <div class="recap-row"><span>N° facture</span><strong><?= e($fac['numero']) ?></strong></div>
              <div class="recap-row"><span>Montant HT</span><strong><?= euros($fac['montant_ht']) ?></strong></div>
              <?php if ($u['role']!=='entreprise'): ?>
                <div class="recap-row"><span>Commission</span><strong><?= euros($fac['commission']) ?></strong></div>
                <div class="recap-row"><span>Net expert</span><strong><?= euros($fac['montant_freelance']) ?></strong></div>
              <?php endif; ?>
              <div class="recap-row"><span>Statut</span><strong><span class="badge <?= $fac['statut']==='payee'?'badge-green':'badge-amber' ?>"><?= $fac['statut']==='payee'?'Payée':'En attente' ?></span></strong></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>

</main>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
