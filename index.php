<?php
$titre = "Vous décrivez le projet, on livre le résultat";
$page = 'accueil';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container hero-grid">
    <div>
      <span class="eyebrow">● Plateforme B2B de delivery projet</span>
      <h1>Vous décrivez le projet,<br>on livre le résultat.</h1>
      <p class="lead">
        Ne perdez plus de temps à chercher, trier et gérer des prestataires.
        Décrivez votre besoin : WorkConnects sélectionne le bon expert, pilote l'exécution
        et vous garantit un livrable conforme, dans les délais.
      </p>
      <div class="hero-actions">
        <a href="inscription.php?role=entreprise" class="btn btn-primary btn-lg">Décrire mon projet</a>
        <a href="entreprises.php" class="btn btn-ghost btn-lg">Comment ça marche</a>
      </div>
      <div class="hero-trust">
        <div><strong>48 h</strong><span>Proposition d'expert</span></div>
        <div><strong>1</strong><span>Interlocuteur unique</span></div>
        <div><strong>100 %</strong><span>Projets pilotés</span></div>
      </div>
    </div>

    <div class="hero-card">
      <div class="hero-card-top">
        <span class="dot"></span><span class="dot"></span><span class="dot"></span>
        <span class="small muted" style="margin-left:8px">Suivi de projet — Nexora Industries</span>
      </div>
      <div class="hero-card-body">
        <div class="flex-between mb-3">
          <div>
            <h4>Refonte du site vitrine</h4>
            <p class="small muted">Chargé de compte : Sophie D.</p>
          </div>
          <span class="badge badge-blue">En cours</span>
        </div>
        <div class="step-row">
          <span class="step-num done">✓</span>
          <div><h4>Brief analysé</h4><p>Cadrage validé sous 24 h</p></div>
        </div>
        <div class="step-row">
          <span class="step-num done">✓</span>
          <div><h4>Expert sélectionné</h4><p>Score de matching : 94 %</p></div>
        </div>
        <div class="step-row">
          <span class="step-num">3</span>
          <div><h4>Production en cours</h4><p>Avancement 65 % — suivi hebdomadaire</p></div>
        </div>
        <div class="step-row">
          <span class="step-num">4</span>
          <div><h4>Livraison &amp; recette</h4><p>Prévue le 15/11/2026</p></div>
        </div>
        <div class="mt-3">
          <div class="bar"><i style="width:65%"></i></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Problème / solution -->
<section class="section bg-white">
  <div class="container">
    <div class="section-head">
      <h2>Gérer des prestataires ne devrait pas être un métier</h2>
      <p>La plupart des plateformes vous laissent seul face à des centaines de profils. Nous faisons l'inverse.</p>
    </div>
    <div class="grid grid-3">
      <div class="card card-hover">
        <div class="card-icon">🎯</div>
        <h3>Zéro recherche de votre côté</h3>
        <p>Vous ne parcourez aucun catalogue de freelances. Notre équipe identifie le profil adapté grâce à un scoring objectif sur les compétences, le budget et la disponibilité.</p>
      </div>
      <div class="card card-hover">
        <div class="card-icon">🧭</div>
        <h3>Un pilotage professionnel</h3>
        <p>Un chargé de compte WorkConnects suit chaque projet de bout en bout : cadrage, jalons, points d'avancement et recette du livrable.</p>
      </div>
      <div class="card card-hover">
        <div class="card-icon">🛡️</div>
        <h3>Un engagement de résultat</h3>
        <p>Contractualisation, paiement sécurisé et contrôle qualité avant livraison. Si le livrable ne convient pas, nous reprenons la main.</p>
      </div>
    </div>
  </div>
</section>

<!-- Méthode -->
<section class="section" id="methode">
  <div class="container">
    <div class="section-head">
      <h2>Notre méthode en 4 étapes</h2>
      <p>Un processus structuré, transparent, et le même niveau d'exigence pour chaque projet.</p>
    </div>
    <div class="steps">
      <div class="step">
        <h3>Vous décrivez</h3>
        <p>Un formulaire guidé en 5 minutes : objectif, périmètre, budget et délai. Aucun jargon technique requis.</p>
      </div>
      <div class="step">
        <h3>Nous cadrons</h3>
        <p>Votre chargé de compte valide le besoin, affine le périmètre et qualifie les critères de réussite du projet.</p>
      </div>
      <div class="step">
        <h3>Nous attribuons</h3>
        <p>Notre moteur de matching classe les experts selon des critères mesurables. Nous sélectionnons et engageons le meilleur profil.</p>
      </div>
      <div class="step">
        <h3>Nous livrons</h3>
        <p>Suivi d'avancement, contrôle qualité et livraison conforme. Vous validez, nous facturons.</p>
      </div>
    </div>
  </div>
</section>

<!-- Deux publics -->
<section class="section bg-white">
  <div class="container grid grid-2">
    <div class="card" style="background:var(--blue-light);border-color:#C7D9FF">
      <span class="badge badge-blue mb-2">Entreprises</span>
      <h3 style="font-size:1.5rem">Un partenaire d'exécution, pas un annuaire</h3>
      <p class="mt-2">Confiez vos projets digitaux, marketing ou data. Nous mobilisons les bons experts et garantissons le résultat, sans que vous ayez à recruter ni à manager.</p>
      <ul style="list-style:none;margin:20px 0;display:grid;gap:10px">
        <li>✓ Proposition d'expert sous 48 h</li>
        <li>✓ Interlocuteur unique dédié</li>
        <li>✓ Tableau de bord de suivi en temps réel</li>
        <li>✓ Facturation unique et simplifiée</li>
      </ul>
      <a href="entreprises.php" class="btn btn-primary">Découvrir l'offre entreprise</a>
    </div>
    <div class="card">
      <span class="badge badge-gray mb-2">Freelances</span>
      <h3 style="font-size:1.5rem">Des missions qualifiées, sans prospection</h3>
      <p class="mt-2">Recevez uniquement des missions correspondant à vos compétences et à votre tarif. Le cadrage, la relation client et la facturation sont gérés par nous.</p>
      <ul style="list-style:none;margin:20px 0;display:grid;gap:10px">
        <li>✓ Missions pré-cadrées et pré-vendues</li>
        <li>✓ Zéro prospection commerciale</li>
        <li>✓ Paiement garanti et sécurisé</li>
        <li>✓ Interlocuteur unique côté WorkConnects</li>
      </ul>
      <a href="freelances.php" class="btn btn-ghost">Découvrir l'offre freelance</a>
    </div>
  </div>
</section>

<!-- Expertises -->
<section class="section">
  <div class="container">
    <div class="section-head">
      <h2>Nos domaines d'expertise</h2>
      <p>Des compétences couvrant l'ensemble de vos besoins digitaux.</p>
    </div>
    <div class="grid grid-4">
      <?php
      $dom = [
        ['💻','Développement Web','Sites, applications métier, e-commerce et intégrations.'],
        ['📱','Développement Mobile','Applications iOS, Android et cross-platform.'],
        ['🎨','Design UI/UX','Interfaces, design systems et parcours utilisateurs.'],
        ['📈','Marketing Digital','SEO, contenu, acquisition et automatisation.'],
        ['📊','Data &amp; Analytics','Tableaux de bord, pipelines et visualisation.'],
        ['⚙️','Automatisation','Processus internes, intégrations et outils no-code.'],
        ['✍️','Rédaction &amp; Contenu','Contenu B2B, documentation et traduction.'],
        ['🔐','Cybersécurité','Audits, conformité et sécurisation applicative.'],
      ];
      foreach ($dom as $d): ?>
        <div class="card card-hover">
          <div class="card-icon"><?= $d[0] ?></div>
          <h4 class="mb-1"><?= $d[1] ?></h4>
          <p class="small"><?= $d[2] ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Tarifs -->
<section class="section bg-white" id="tarifs">
  <div class="container">
    <div class="section-head">
      <h2>Une tarification claire</h2>
      <p>Pas d'abonnement, pas de frais cachés. Vous ne payez que lorsque le projet est lancé.</p>
    </div>
    <div class="grid grid-3">
      <div class="card">
        <h3>Publication du besoin</h3>
        <p class="mt-1" style="font-size:2rem;font-weight:800;color:var(--ink)">Gratuit</p>
        <p class="small mt-2">Décrivez votre projet et recevez une proposition d'expert et une estimation budgétaire sous 48 h, sans engagement.</p>
      </div>
      <div class="card" style="border-color:var(--blue);box-shadow:var(--sh-md)">
        <span class="badge badge-blue mb-2">Le plus courant</span>
        <h3>Projet piloté</h3>
        <p class="mt-1" style="font-size:2rem;font-weight:800;color:var(--ink)">Devis <span style="font-size:1rem;font-weight:600;color:var(--muted)">clair et sans surprise</span></p>
        <p class="small mt-2">Sélection de l'expert, pilotage complet, contrôle qualité, sécurisation du paiement et facturation unique.</p>
      </div>
      <div class="card">
        <h3>Programme sur mesure</h3>
        <p class="mt-1" style="font-size:2rem;font-weight:800;color:var(--ink)">Sur devis</p>
        <p class="small mt-2">Volume de projets récurrents, équipe dédiée, SLA contractuels et reporting consolidé pour les grands comptes.</p>
      </div>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="section" id="faq">
  <div class="container-sm">
    <div class="section-head">
      <h2>Questions fréquentes</h2>
    </div>
    <?php
    $faq = [
      ["Puis-je choisir moi-même le freelance ?", "Notre valeur ajoutée est justement de vous éviter cette charge. Nous sélectionnons le profil sur la base de critères objectifs et vous présentons notre recommandation argumentée. Vous gardez évidemment un droit de regard."],
      ["Comment est calculé le score de matching ?", "Il repose sur des critères mesurables : correspondance des compétences, adéquation budgétaire, disponibilité, années d'expérience et note moyenne des missions passées. Les coefficients sont pilotés par notre équipe."],
      ["Puis-je contacter directement le freelance ?", "Par défaut, tous les échanges passent par votre chargé de compte WorkConnects. Cela garantit la traçabilité, la qualité du suivi et la protection des deux parties."],
      ["Quand suis-je facturé ?", "La publication du besoin et l'étude sont gratuites. La facturation intervient au lancement du projet, selon l'échéancier défini dans le devis validé."],
      ["Que se passe-t-il si le livrable ne convient pas ?", "Un contrôle qualité est réalisé avant toute livraison. En cas de non-conformité, nous organisons les corrections et, si nécessaire, réattribuons la mission sans surcoût pour vous."],
    ];
    foreach ($faq as $f): ?>
      <details class="card mb-2" style="padding:18px 22px">
        <summary style="cursor:pointer;font-weight:600;list-style:none"><?= e($f[0]) ?></summary>
        <p class="mt-2 small"><?= e($f[1]) ?></p>
      </details>
    <?php endforeach; ?>
  </div>
</section>

<section class="section-sm">
  <div class="container">
    <div class="cta-band">
      <h2>Prêt à lancer votre prochain projet ?</h2>
      <p>Décrivez votre besoin en 5 minutes. Notre équipe revient vers vous sous 48 heures avec une proposition concrète.</p>
      <div class="cta-actions">
        <a href="inscription.php?role=entreprise" class="btn btn-white btn-lg">Décrire mon projet</a>
        <a href="connexion.php" class="btn btn-outline-white btn-lg">Accéder à mon espace</a>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
