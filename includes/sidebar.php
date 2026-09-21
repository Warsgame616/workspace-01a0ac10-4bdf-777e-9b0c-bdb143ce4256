<?php
$r = role();
$cur = basename($_SERVER['PHP_SELF']);
$nb_msg = 0;
if ($u = user()) {
    $st = db()->prepare("SELECT COUNT(*) FROM messages WHERE destinataire_id = ? AND lu = 0");
    $st->execute([$u['id']]);
    $nb_msg = (int)$st->fetchColumn();
}
?>
<aside class="sidebar">
<?php if (est_client($r)): ?>
  <div class="side-label">Pilotage</div>
  <a href="<?= u('dashboard-entreprise.php') ?>" class="side-link <?= $cur==='dashboard-entreprise.php'?'active':'' ?>"><span class="ico">📊</span> Tableau de bord</a>
  <a href="<?= u('projets.php') ?>" class="side-link <?= $cur==='projets.php'?'active':'' ?>"><span class="ico">📁</span> Mes projets</a>
  <a href="<?= u('nouveau-projet.php') ?>" class="side-link <?= $cur==='nouveau-projet.php'?'active':'' ?>"><span class="ico">➕</span> Nouveau projet</a>
  <div class="side-label">Échanges</div>
  <a href="<?= u('messages.php') ?>" class="side-link <?= $cur==='messages.php'?'active':'' ?>">
    <span class="ico">💬</span> Messagerie<?php if($nb_msg):?><span class="cnt"><?= $nb_msg ?></span><?php endif;?>
  </a>
  <a href="<?= u('factures.php') ?>" class="side-link <?= $cur==='factures.php'?'active':'' ?>"><span class="ico">🧾</span> Facturation</a>
  <div class="side-label">Compte</div>
  <a href="<?= u('profil.php') ?>" class="side-link <?= $cur==='profil.php'?'active':'' ?>"><span class="ico">⚙️</span> Mon profil</a>

<?php elseif ($r === 'freelance'): ?>
  <div class="side-label">Activité</div>
  <a href="<?= u('dashboard-freelance.php') ?>" class="side-link <?= $cur==='dashboard-freelance.php'?'active':'' ?>"><span class="ico">📊</span> Tableau de bord</a>
  <a href="<?= u('projets.php') ?>" class="side-link <?= $cur==='projets.php'?'active':'' ?>"><span class="ico">💼</span> Mes missions</a>
  <div class="side-label">Échanges</div>
  <a href="<?= u('messages.php') ?>" class="side-link <?= $cur==='messages.php'?'active':'' ?>">
    <span class="ico">💬</span> Messagerie<?php if($nb_msg):?><span class="cnt"><?= $nb_msg ?></span><?php endif;?>
  </a>
  <a href="<?= u('factures.php') ?>" class="side-link <?= $cur==='factures.php'?'active':'' ?>"><span class="ico">🧾</span> Mes revenus</a>
  <div class="side-label">Compte</div>
  <a href="<?= u('profil.php') ?>" class="side-link <?= $cur==='profil.php'?'active':'' ?>"><span class="ico">⚙️</span> Mon profil</a>

<?php elseif ($r === 'admin'): ?>
  <div class="side-label">Back-office</div>
  <a href="<?= u('admin.php') ?>" class="side-link <?= $cur==='admin.php'?'active':'' ?>"><span class="ico">📊</span> Vue d'ensemble</a>
  <a href="<?= u('admin-projets.php') ?>" class="side-link <?= $cur==='admin-projets.php'?'active':'' ?>"><span class="ico">📁</span> Projets</a>
  <a href="<?= u('admin-utilisateurs.php') ?>" class="side-link <?= $cur==='admin-utilisateurs.php'?'active':'' ?>"><span class="ico">👥</span> Utilisateurs</a>
  <div class="side-label">Opérations</div>
  <a href="<?= u('messages.php') ?>" class="side-link <?= $cur==='messages.php'?'active':'' ?>">
    <span class="ico">💬</span> Messagerie<?php if($nb_msg):?><span class="cnt"><?= $nb_msg ?></span><?php endif;?>
  </a>
  <a href="<?= u('factures.php') ?>" class="side-link <?= $cur==='factures.php'?'active':'' ?>"><span class="ico">🧾</span> Facturation</a>
  <a href="<?= u('admin-parametres.php') ?>" class="side-link <?= $cur==='admin-parametres.php'?'active':'' ?>"><span class="ico">⚙️</span> Paramètres</a>
<?php endif; ?>
</aside>
