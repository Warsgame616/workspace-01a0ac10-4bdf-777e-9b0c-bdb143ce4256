<?php
require_once __DIR__ . '/includes/functions.php';
if (!is_logged()) { header('Location: ' . u('connexion.php')); exit; }
$u = user();
$id = (int)($_GET['id'] ?? 0);

$st = db()->prepare("SELECT p.*, e.societe, e.nom AS e_nom, e.prenom AS e_prenom,
                            f.nom AS f_nom, f.prenom AS f_prenom, f.titre_pro, f.note_moyenne, f.tarif_projet
                     FROM projets p
                     LEFT JOIN users e ON e.id = p.entreprise_id
                     LEFT JOIN users f ON f.id = p.freelance_id
                     WHERE p.id = ?");
$st->execute([$id]);
$p = $st->fetch();
if (!$p) { header('Location: ' . dashboard_url(null, true)); exit; }

// Contrôle d'accès strict : seuls le client, l'expert affecté et l'admin peuvent voir
$autorise = ($u['role']==='admin') || ($p['entreprise_id']==$u['id']) || ($p['freelance_id']==$u['id']);
if (!$autorise) { header('Location: ' . dashboard_url(null, true)); exit; }

// Téléversement d'un livrable (tous les intervenants du projet)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['form_fichier'])) {
    csrf_check();
    $r = traiter_upload($_FILES['fichier'] ?? [], 'projets', ext_doc_autorisees());
    if ($r['ok']) {
        db()->prepare("INSERT INTO fichiers (projet_id,uploader_id,nom,chemin,taille) VALUES (?,?,?,?,?)")
            ->execute([$id, $u['id'], $r['nom'], $r['chemin'], $r['taille']]);
        $qui = $u['role']==='admin' ? 'WorkConnects' : trim($u['prenom'].' '.$u['nom']);
        foreach ([$p['entreprise_id'], $p['freelance_id'], 1] as $dest) {
            if ($dest && (int)$dest !== (int)$u['id']) {
                notify($dest, "Nouveau fichier déposé sur « ".$p['titre']." » par ".$qui.".", 'projet.php?id='.$id);
            }
        }
        flash("Fichier « ".$r['nom']." » déposé.");
    } else {
        flash($r['erreur'], 'error');
    }
    header('Location: ' . u('projet.php?id=' . $id)); exit;
}

// Suppression d'un fichier (son auteur ou l'administrateur)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['suppr_fichier'])) {
    csrf_check();
    $fid = (int)$_POST['suppr_fichier'];
    $q = db()->prepare("SELECT * FROM fichiers WHERE id=? AND projet_id=?");
    $q->execute([$fid, $id]);
    if ($fi = $q->fetch()) {
        if ($u['role']==='admin' || (int)$fi['uploader_id'] === (int)$u['id']) {
            @unlink(__DIR__.'/uploads/'.$fi['chemin']);
            db()->prepare("DELETE FROM fichiers WHERE id=?")->execute([$fid]);
            flash("Fichier supprimé.");
        }
    }
    header('Location: ' . u('projet.php?id=' . $id)); exit;
}

// Évaluation du projet livré (par le client)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['form_eval'])) {
    csrf_check();
    $note = max(1, min(5, (int)($_POST['note'] ?? 0)));
    if ($p['statut']==='termine' && $p['freelance_id'] && (int)$p['entreprise_id'] === (int)$u['id']) {
        $dej = db()->prepare("SELECT COUNT(*) FROM evaluations WHERE projet_id=? AND auteur_id=?");
        $dej->execute([$id, $u['id']]);
        if (!$dej->fetchColumn()) {
            db()->prepare("INSERT INTO evaluations (projet_id,auteur_id,cible_id,note,commentaire) VALUES (?,?,?,?,?)")
                ->execute([$id, $u['id'], $p['freelance_id'], $note, trim($_POST['commentaire'] ?? '')]);
            // Moyenne pondérée : l'historique déjà acquis est conservé
            $fq = db()->prepare("SELECT note_moyenne, nb_missions FROM users WHERE id=?");
            $fq->execute([$p['freelance_id']]);
            $fl = $fq->fetch();
            $nb_av  = max(0, (int)$fl['nb_missions']);
            $moy_av = (float)$fl['note_moyenne'];
            $moy = $nb_av > 0 ? round((($moy_av * $nb_av) + $note) / ($nb_av + 1), 2) : (float)$note;
            db()->prepare("UPDATE users SET note_moyenne=?, nb_missions=nb_missions+1 WHERE id=?")
                ->execute([$moy, $p['freelance_id']]);
            notify(admin_id(), "Nouvelle évaluation (".$note."/5) sur « ".$p['titre']." ».", 'projet.php?id='.$id);
            flash("Merci, votre évaluation a bien été enregistrée.");
        }
    }
    header('Location: ' . u('projet.php?id=' . $id)); exit;
}

// Mise à jour (admin uniquement)
/* Règlement des échéances par l'entreprise (étapes 1 et 2). */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['form_paiement'])) {
    csrf_check();
    if (!est_client($u['role']) || $p['entreprise_id'] != $u['id']) {
        header('Location: ' . u('projet.php?id=' . $id)); exit;
    }
    $type = $_POST['type_paiement'] ?? '';
    // Le règlement du projet n'est ouvert qu'une fois le livrable remis.
    if ($type === 'projet' && $p['statut'] !== 'termine') {
        flash("Le règlement sera disponible à la livraison du projet.");
        header('Location: ' . u('projet.php?id=' . $id)); exit;
    }
    if (in_array($type, ['frais','projet'], true)) {
        regler_paiement($id, $type);
        if ($type === 'frais') {
            db()->prepare("UPDATE projets SET statut='en_cours' WHERE id=? AND statut='attribue'")->execute([$id]);
            notify(admin_id(), "Proposition validée par le client sur « ".$p['titre']." ».", 'projet.php?id='.$id);
            if ($p['freelance_id']) notify($p['freelance_id'], "La mission « ".$p['titre']." » est confirmée, vous pouvez démarrer.", 'projet.php?id='.$id);
            flash("Proposition validée. La mission démarre.");
        } else {
            notify(admin_id(), "Règlement du projet « ".$p['titre']." » reçu.", 'projet.php?id='.$id);
            flash("Règlement enregistré. Merci.");
        }
    }
    header('Location: ' . u('projet.php?id=' . $id)); exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && $u['role']==='admin' && isset($_POST['form_suivi'])) {
    csrf_check();
    db()->prepare("UPDATE projets SET statut=?, avancement=?, montant_final=? WHERE id=?")
        ->execute([$_POST['statut'], (int)$_POST['avancement'], (int)$_POST['montant_final'], $id]);
    if ($_POST['statut']==='termine') {
        // Le montant saisi est ce que perçoit l'expert ; les frais s'y ajoutent.
        $d = decomposer_montant((int)$_POST['montant_final']);
        liberer_frais($id);   // livraison : les frais de dossier sont acquis
        $ex = db()->prepare("SELECT COUNT(*) FROM factures WHERE projet_id=?"); $ex->execute([$id]);
        if (!$ex->fetchColumn()) {
            db()->prepare("INSERT INTO factures (projet_id,numero,montant_ht,commission,montant_freelance,statut) VALUES (?,?,?,?,?,'en_attente')")
                ->execute([$id, 'FA-'.date('Y').'-'.str_pad($id,4,'0',STR_PAD_LEFT), $d['total'], $d['part'], $d['base']]);
        }
    }
    if ($_POST['statut']==='annule') { rembourser_frais($id); }
    notify($p['entreprise_id'], "Mise à jour du projet « ".$p['titre']." » : ".statut_label($_POST['statut'])." (".(int)$_POST['avancement']." %).", 'projet.php?id='.$id);
    if ($p['freelance_id']) notify($p['freelance_id'], "Mise à jour de la mission « ".$p['titre']." ».", 'projet.php?id='.$id);
    flash("Projet mis à jour.");
    header('Location: ' . u('projet.php?id=' . $id)); exit;
}

$fs = db()->prepare("SELECT f.*, u.nom AS u_nom, u.prenom AS u_prenom, u.role AS u_role
                     FROM fichiers f LEFT JOIN users u ON u.id=f.uploader_id
                     WHERE f.projet_id=? ORDER BY f.id DESC"); $fs->execute([$id]);
$fichiers = $fs->fetchAll();
$fa = db()->prepare("SELECT * FROM factures WHERE projet_id=?"); $fa->execute([$id]);
$factures = $fa->fetchAll();

$ev = db()->prepare("SELECT e.*, a.nom AS a_nom, a.prenom AS a_prenom, a.societe
                     FROM evaluations e LEFT JOIN users a ON a.id=e.auteur_id
                     WHERE e.projet_id=?");
$ev->execute([$id]);
$evaluations = $ev->fetchAll();
$peut_evaluer = (est_client($u['role']) && (int)$p['entreprise_id']===(int)$u['id']
                 && $p['statut']==='termine' && $p['freelance_id'] && !$evaluations);

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
        <div class="panel-body">
          <form method="post" enctype="multipart/form-data" class="upload-zone" id="upProjet">
            <?= csrf_field() ?><input type="hidden" name="form_fichier" value="1">
            <input type="file" name="fichier" id="fichierProjet" hidden
                   onchange="document.getElementById('upProjet').submit()">
            <label for="fichierProjet" class="upload-label">
              <span class="upload-ico">📤</span>
              <strong>Déposer un fichier</strong>
              <span class="small muted">PDF, Word, Excel, images, ZIP, vidéo — 8 Mo maximum</span>
            </label>
          </form>

          <?php if (!$fichiers): ?>
            <div class="empty" style="padding:26px 0 6px">
              <div class="ico">📎</div>
              <h3>Aucun fichier pour l'instant</h3>
              <p>Les livrables déposés apparaîtront ici.</p>
            </div>
          <?php else: ?>
            <div class="mt-3">
            <?php foreach ($fichiers as $fi): ?>
              <div class="file-row">
                <span class="file-ico"><?= icone_fichier($fi['nom']) ?></span>
                <div style="flex:1;min-width:0">
                  <strong class="small" style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($fi['nom']) ?></strong>
                  <div class="t-sub">
                    <?= taille_lisible($fi['taille']) ?> · <?= date_fr($fi['created_at']) ?>
                    · déposé par <?= $fi['u_role']==='admin' ? 'WorkConnects' : e(trim($fi['u_prenom'].' '.mb_substr($fi['u_nom'],0,1).'.')) ?>
                  </div>
                </div>
                <a href="download.php?id=<?= $fi['id'] ?>" class="btn btn-ghost btn-sm">⬇ Télécharger</a>
                <?php if ($u['role']==='admin' || (int)$fi['uploader_id'] === (int)$u['id']): ?>
                  <form method="post" style="margin:0" onsubmit="return confirm('Supprimer ce fichier ?')">
                    <?= csrf_field() ?>
                    <button name="suppr_fichier" value="<?= $fi['id'] ?>" class="btn btn-ghost btn-sm" title="Supprimer">🗑</button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($peut_evaluer): ?>
      <div class="panel" style="border-color:var(--green)">
        <div class="panel-head"><h3>⭐ Évaluer la prestation</h3><span class="badge badge-green">Projet livré</span></div>
        <div class="panel-body">
          <p class="small muted mb-3">Votre retour nous aide à sélectionner les meilleurs experts pour vos prochains projets.</p>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="form_eval" value="1">
            <div class="field">
              <label>Votre note</label>
              <div class="star-pick" id="starPick">
                <?php for ($i=5; $i>=1; $i--): ?>
                  <input type="radio" name="note" id="st<?= $i ?>" value="<?= $i ?>" <?= $i===5?'checked':'' ?>>
                  <label for="st<?= $i ?>" title="<?= $i ?> sur 5">★</label>
                <?php endfor; ?>
              </div>
            </div>
            <div class="field">
              <label for="commentaire">Commentaire (facultatif)</label>
              <textarea id="commentaire" name="commentaire" class="textarea" style="min-height:90px"
                        placeholder="Qualité du livrable, respect des délais, communication…"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Envoyer mon évaluation</button>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($evaluations): ?>
      <div class="panel">
        <div class="panel-head"><h3>Évaluation</h3></div>
        <div class="panel-body">
          <?php foreach ($evaluations as $ev1): ?>
            <div class="flex-between mb-1">
              <strong class="small"><?= e($ev1['societe'] ?: trim($ev1['a_prenom'].' '.$ev1['a_nom'])) ?></strong>
              <span class="stars"><?= str_repeat('★',(int)$ev1['note']).str_repeat('☆',5-(int)$ev1['note']) ?></span>
            </div>
            <?php if ($ev1['commentaire']): ?><p class="small">« <?= e($ev1['commentaire']) ?> »</p><?php endif; ?>
            <p class="t-sub mt-1"><?= date_fr($ev1['created_at']) ?></p>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($u['role']==='admin'): ?>
      <div class="panel" style="border-color:var(--blue)">
        <div class="panel-head"><h3>🛠️ Gestion WorkConnects</h3><span class="badge badge-blue">Back-office</span></div>
        <div class="panel-body">
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="form_suivi" value="1">
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
              <div class="hint">Le montant saisi est celui versé à l'expert. Le passage à « Terminé » génère la facture et libère les frais de service.</div>
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
            <div class="recap-row"><span>Budget estimé</span><strong><?= euros($p['budget_min']) ?> – <?= euros_max($p['budget_max']) ?></strong></div>
            <?php if ($p['montant_final']): ?>
              <div class="recap-row"><span>Montant validé</span><strong><?= euros($p['montant_final']) ?></strong></div>
            <?php endif; ?>
            <div class="recap-row"><span>Durée</span><strong><?= e($p['delai'] ?: '—') ?></strong></div>
            <div class="recap-row"><span>Échéance</span><strong><?= date_fr($p['date_limite']) ?></strong></div>
            <div class="recap-row"><span>Statut</span><strong><span class="badge <?= statut_classe($p['statut']) ?>"><?= statut_label($p['statut']) ?></span></strong></div>
          </div>
        </div>
      </div>

      <?php
      /* ── Échéances de paiement (entreprise uniquement) ────────────────
         Aucun taux ni aucune répartition n'est affiché : le client ne voit
         que ce qu'il doit régler. Le détail reste dans le back-office. */
      $pay = paiements_projet($id);
      if (est_client($u['role']) && $pay):
        $pf = $pay['frais']  ?? null;
        $pp = $pay['projet'] ?? null;
      ?>
      <div class="panel">
        <div class="panel-head"><h3>Règlement</h3></div>
        <div class="panel-body">

          <?php if ($pf && $pf['statut'] === 'a_payer'): ?>
            <div class="pay-step">
              <div class="pay-num">1</div>
              <div class="pay-body">
                <h4>Valider la proposition</h4>
                <p class="small muted">Frais de dossier à régler pour lancer la mission. Ce montant est conservé jusqu'à la livraison et vous est remboursé en cas d'annulation.</p>
                <div class="pay-amount"><?= euros($pf['montant']) ?></div>
                <form method="post" class="mt-1">
                  <?= csrf_field() ?>
                  <input type="hidden" name="form_paiement" value="1">
                  <input type="hidden" name="type_paiement" value="frais">
                  <button class="btn btn-primary btn-block">Accepter et régler</button>
                </form>
              </div>
            </div>
          <?php elseif ($pf): ?>
            <div class="pay-step done">
              <div class="pay-num">✓</div>
              <div class="pay-body">
                <h4>Proposition validée</h4>
                <p class="small muted">
                  <?= euros($pf['montant']) ?> —
                  <?= $pf['statut']==='rembourse' ? 'remboursés'
                      : ($pf['statut']==='libere' ? 'acquis à la livraison'
                      : 'conservés jusqu\'à la livraison') ?>
                </p>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($pp): ?>
            <?php if ($pp['statut'] === 'a_payer' && $pf && $pf['statut'] !== 'a_payer' && $p['statut'] === 'termine'): ?>
              <div class="pay-step">
                <div class="pay-num">2</div>
                <div class="pay-body">
                  <h4>Régler le projet</h4>
                  <p class="small muted">Le livrable a été remis. Montant total de la prestation.</p>
                  <div class="pay-amount"><?= euros($pp['montant']) ?></div>
                  <form method="post" class="mt-1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_paiement" value="1">
                    <input type="hidden" name="type_paiement" value="projet">
                    <button class="btn btn-primary btn-block">Régler <?= euros($pp['montant']) ?></button>
                  </form>
                </div>
              </div>
            <?php elseif ($pp['statut'] === 'paye'): ?>
              <div class="pay-step done">
                <div class="pay-num">✓</div>
                <div class="pay-body">
                  <h4>Projet réglé</h4>
                  <p class="small muted"><?= euros($pp['montant']) ?></p>
                </div>
              </div>
            <?php else: ?>
              <div class="pay-step pending">
                <div class="pay-num">2</div>
                <div class="pay-body">
                  <h4>Règlement du projet</h4>
                  <p class="small muted">
                    <?= ($pf && $pf['statut']==='a_payer')
                        ? 'Disponible après validation de la proposition.'
                        : 'À régler à la livraison du livrable — ' . euros($pp['montant']) . '.' ?>
                  </p>
                </div>
              </div>
            <?php endif; ?>
          <?php endif; ?>

          <?php if ($pf && $pp): ?>
            <div class="recap mt-2">
              <div class="recap-row total"><span>Total du projet</span><strong><?= euros($pf['montant'] + $pp['montant']) ?></strong></div>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="panel">
        <div class="panel-head"><h3>Expert affecté</h3></div>
        <div class="panel-body">
          <?php if ($p['freelance_id']): ?>
            <div class="flex-center mb-2">
              <span class="avatar lg"><?= strtoupper(mb_substr($p['f_prenom'],0,1).mb_substr($p['f_nom'],0,1)) ?></span>
              <div>
                <h4><?= est_client($u['role'])
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
              <?php if ($u['role']==='admin'): ?>
                <div class="recap-row"><span>Frais de service</span><strong><?= euros($fac['commission']) ?></strong></div>
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
