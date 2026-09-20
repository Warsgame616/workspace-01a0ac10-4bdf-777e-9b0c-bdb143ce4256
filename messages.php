<?php
require_once __DIR__ . '/includes/functions.php';
if (!is_logged()) { header('Location: connexion.php'); exit; }
$u = user();
$ADMIN_ID = 1;

/* L'admin discute avec un utilisateur choisi ; les autres discutent uniquement avec WorkConnects */
if ($u['role'] === 'admin') {
    $contacts = db()->query("SELECT * FROM users WHERE role IN ('entreprise','freelance') ORDER BY role, nom")->fetchAll();
    $cid = (int)($_GET['c'] ?? ($contacts[0]['id'] ?? 0));
} else {
    $cid = $ADMIN_ID;
}

// Envoi d'un message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $txt = trim($_POST['contenu'] ?? '');
    $dest = (int)($_POST['dest'] ?? $cid);
    if ($txt !== '' && $dest) {
        db()->prepare("INSERT INTO messages (projet_id,expediteur_id,destinataire_id,contenu) VALUES (NULL,?,?,?)")
            ->execute([$u['id'], $dest, $txt]);
        notify($dest, "Nouveau message de " . ($u['role']==='admin' ? 'WorkConnects' : trim($u['prenom'].' '.$u['nom'])) . ".", 'messages.php');
    }
    header('Location: messages.php' . ($u['role']==='admin' ? '?c='.$dest : '')); exit;
}

// Marquer comme lus
db()->prepare("UPDATE messages SET lu=1 WHERE destinataire_id=? AND expediteur_id=?")->execute([$u['id'], $cid]);

$st = db()->prepare("SELECT * FROM messages
                     WHERE (expediteur_id=? AND destinataire_id=?) OR (expediteur_id=? AND destinataire_id=?)
                     ORDER BY id ASC");
$st->execute([$u['id'], $cid, $cid, $u['id']]);
$msgs = $st->fetchAll();

$cs = db()->prepare("SELECT * FROM users WHERE id=?"); $cs->execute([$cid]);
$contact = $cs->fetch();

$titre = "Messagerie";
require_once __DIR__ . '/includes/header.php';
?>
<div class="app">
<?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main">

  <div class="page-head">
    <div>
      <h1>Messagerie</h1>
      <p><?= $u['role']==='admin'
          ? "Échanges avec les entreprises et les freelances."
          : "Échangez avec votre chargé de compte WorkConnects." ?></p>
    </div>
  </div>

  <div class="chat">
    <div class="chat-list">
      <?php if ($u['role']==='admin'): ?>
        <?php foreach ($contacts as $c):
          $lm = db()->prepare("SELECT contenu FROM messages WHERE (expediteur_id=? AND destinataire_id=?) OR (expediteur_id=? AND destinataire_id=?) ORDER BY id DESC LIMIT 1");
          $lm->execute([$u['id'],$c['id'],$c['id'],$u['id']]);
          $last = $lm->fetchColumn();
          $nl = db()->prepare("SELECT COUNT(*) FROM messages WHERE expediteur_id=? AND destinataire_id=? AND lu=0");
          $nl->execute([$c['id'], $u['id']]); $nb = (int)$nl->fetchColumn();
        ?>
          <a href="messages.php?c=<?= $c['id'] ?>" class="chat-item <?= $c['id']==$cid?'active':'' ?>">
            <span class="avatar"><?= initiales($c) ?></span>
            <div class="meta">
              <strong><?= e(trim($c['prenom'].' '.$c['nom'])) ?><?php if($nb):?> <span class="badge badge-red" style="font-size:.625rem"><?= $nb ?></span><?php endif;?></strong>
              <p><?= e($c['role']==='entreprise' ? ($c['societe'] ?: 'Entreprise') : $c['titre_pro']) ?></p>
              <p style="font-size:.75rem"><?= e(mb_substr((string)$last, 0, 42)) ?><?= mb_strlen((string)$last)>42?'…':'' ?></p>
            </div>
          </a>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="chat-item active">
          <span class="avatar dark">WC</span>
          <div class="meta">
            <strong>WorkConnects</strong>
            <p>Sophie Dupont — Chargée de compte</p>
          </div>
        </div>
        <div style="padding:18px;font-size:.8125rem;color:var(--muted);border-top:1px solid var(--line-2)">
          🔒 Pour garantir la traçabilité et la qualité du suivi, tous vos échanges passent par votre chargé de compte.
          Aucun contact direct entre entreprise et freelance n'est ouvert par défaut.
        </div>
      <?php endif; ?>
    </div>

    <div class="chat-main">
      <div class="chat-head">
        <span class="avatar <?= $u['role']==='admin'?'':'dark' ?>">
          <?= $u['role']==='admin' ? ($contact ? initiales($contact) : '?') : 'WC' ?>
        </span>
        <div>
          <strong><?= $u['role']==='admin' ? e(trim($contact['prenom'].' '.$contact['nom'])) : 'WorkConnects' ?></strong>
          <p class="small muted">
            <?= $u['role']==='admin'
                ? e($contact['role']==='entreprise' ? ($contact['societe'] ?: 'Entreprise') : $contact['titre_pro'])
                : 'Sophie Dupont — Chargée de compte' ?>
          </p>
        </div>
      </div>

      <?php if ($u['role'] !== 'admin'): ?>
        <div class="chat-note">Conversation sécurisée avec votre chargé de compte WorkConnects</div>
      <?php endif; ?>

      <div class="chat-body" id="chatBody">
        <?php if (!$msgs): ?>
          <div class="empty" style="margin:auto">
            <div class="ico">💬</div>
            <h3>Aucun message</h3>
            <p>Démarrez la conversation ci-dessous.</p>
          </div>
        <?php else: foreach ($msgs as $m): ?>
          <div class="msg <?= $m['expediteur_id']==$u['id'] ? 'out' : 'in' ?>">
            <?= nl2br(e($m['contenu'])) ?>
            <span class="time"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></span>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <form class="chat-foot" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="dest" value="<?= $cid ?>">
        <input type="text" name="contenu" class="input" placeholder="Écrivez votre message…" required autocomplete="off">
        <button type="submit" class="btn btn-primary">Envoyer</button>
      </form>
    </div>
  </div>

</main>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
