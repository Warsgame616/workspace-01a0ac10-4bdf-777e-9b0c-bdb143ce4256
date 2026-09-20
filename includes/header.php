<?php
require_once __DIR__ . '/functions.php';
$u = user();
$page = $page ?? '';
$titre = $titre ?? 'WorkConnects';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titre) ?> — WorkConnects</title>
<meta name="description" content="WorkConnects : vous décrivez le projet, on livre le résultat. Plateforme B2B de gestion de projets avec freelances sélectionnés et pilotés par notre équipe.">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="header">
  <div class="container header-inner">
    <a href="index.php" class="logo">
      <span class="logo-mark">W</span> WorkConnects
    </a>

    <nav class="nav" id="nav">
      <?php if (!$u): ?>
        <a href="index.php" class="<?= $page==='accueil'?'active':'' ?>">Accueil</a>
        <a href="entreprises.php" class="<?= $page==='entreprises'?'active':'' ?>">Entreprises</a>
        <a href="freelances.php" class="<?= $page==='freelances'?'active':'' ?>">Freelances</a>
        <a href="index.php#methode">Méthode</a>
        <a href="index.php#tarifs">Tarifs</a>
      <?php else: ?>
        <a href="<?= dashboard_url() ?>">Tableau de bord</a>
        <a href="messages.php">Messagerie</a>
        <?php if ($u['role']==='entreprise'): ?><a href="nouveau-projet.php">Nouveau projet</a><?php endif; ?>
      <?php endif; ?>
    </nav>

    <div class="header-cta">
      <?php if ($u): ?>
        <?php $nb = nb_notifs_non_lues($u['id']); ?>
        <div class="notif-wrap">
          <button class="notif-btn" onclick="toggleNotif(event)" aria-label="Notifications">
            🔔<?php if ($nb): ?><span class="dot-red"></span><?php endif; ?>
          </button>
          <div class="notif-panel" id="notifPanel">
            <div class="hd">Notifications</div>
            <?php $ns = notifications($u['id'], 6); ?>
            <?php if (!$ns): ?>
              <div style="padding:22px;text-align:center;color:var(--muted);font-size:.8125rem">Aucune notification</div>
            <?php else: foreach ($ns as $n): ?>
              <a class="notif-item <?= $n['lu']?'':'unread' ?>" href="<?= e($n['lien'] ?: '#') ?>">
                <?= e($n['texte']) ?><time><?= date_fr($n['created_at']) ?></time>
              </a>
            <?php endforeach; endif; ?>
          </div>
        </div>
        <a href="profil.php" class="avatar" title="<?= e($u['prenom'].' '.$u['nom']) ?>"><?= initiales($u) ?></a>
        <a href="deconnexion.php" class="btn btn-ghost btn-sm">Déconnexion</a>
      <?php else: ?>
        <a href="connexion.php" class="btn btn-ghost">Connexion</a>
        <a href="inscription.php" class="btn btn-primary">Démarrer un projet</a>
      <?php endif; ?>
      <button class="burger" onclick="document.getElementById('nav').classList.toggle('open')" aria-label="Menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>
