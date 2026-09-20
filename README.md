# WorkConnects

Plateforme B2B de mise en relation et de gestion de projets entre entreprises et freelances,
avec gestion centralisée par l'équipe WorkConnects.

> **Positionnement :** « Vous décrivez le projet, on livre le résultat. »
> L'entreprise ne recherche pas elle-même de freelance : WorkConnects sélectionne, pilote et livre.

---

## Stack technique

Aucune API externe, aucun framework, aucune dépendance à installer.

| Couche | Technologie |
|---|---|
| Structure | HTML5 sémantique |
| Style | CSS3 pur (design system maison, variables CSS) |
| Interactions | JavaScript natif (aucune librairie) |
| Serveur | PHP 8 procédural |
| Base de données | SQLite via PDO (requêtes préparées) |

---

## Installation

### En local (5 secondes)

```bash
cd workconnects-php
php -S localhost:8080
```

Puis ouvrir http://localhost:8080

La base de données SQLite et les données de démonstration sont créées automatiquement au premier chargement.

### Sur un hébergement mutualisé

1. Envoyer le dossier en FTP dans le répertoire web (`www/`, `public_html/`…)
2. Donner les droits d'écriture au dossier `data/` (chmod 755 ou 775)
3. C'est tout — aucune configuration nécessaire

**Prérequis serveur :** PHP 7.4 ou supérieur avec l'extension `pdo_sqlite` (activée par défaut partout).
L'extension `mbstring` est utilisée si présente, sinon un repli automatique prend le relais.

### Passer sur MySQL (optionnel)

Modifier uniquement la fonction `db()` dans `includes/db.php` :

```php
$pdo = new PDO('mysql:host=localhost;dbname=workconnects;charset=utf8mb4', 'user', 'pass');
```

Le reste de l'application ne change pas : tout passe par PDO en requêtes préparées.

---

## Comptes de démonstration

| Rôle | E-mail | Mot de passe |
|---|---|---|
| Entreprise | `entreprise@test.fr` | `test123` |
| Freelance | `marie@freelance.fr` | `test123` |
| Back-office | `admin@workconnects.fr` | `admin123` |

La page de connexion propose trois boutons de connexion en un clic pour ces comptes.

---

## Arborescence

```
workconnects-php/
├── index.php                  Landing page
├── entreprises.php            Page d'offre entreprise
├── freelances.php             Page d'offre freelance
├── mentions.php               Mentions légales, CGU/CGV, RGPD
│
├── inscription.php            Inscription (formulaire adaptatif selon le rôle)
├── connexion.php              Connexion
├── deconnexion.php            Déconnexion
│
├── dashboard-entreprise.php   Tableau de bord entreprise
├── dashboard-freelance.php    Tableau de bord freelance
├── projets.php                Liste des projets / missions
├── nouveau-projet.php         Formulaire progressif en 5 étapes
├── projet.php                 Détail projet (vue adaptée au rôle)
├── messages.php               Messagerie
├── factures.php               Facturation / revenus
├── profil.php                 Profil, sécurité, RGPD
│
├── admin.php                  Back-office : vue d'ensemble
├── admin-matching.php         Moteur de matching et attribution
├── admin-projets.php          Gestion de tous les projets
├── admin-utilisateurs.php     Gestion des comptes
├── admin-parametres.php       Coefficients de matching, commission
│
├── includes/
│   ├── db.php                 Connexion PDO, schéma, données de démo
│   ├── functions.php          Sessions, sécurité, matching, helpers
│   ├── header.php             En-tête + navigation + notifications
│   ├── sidebar.php            Menu latéral selon le rôle
│   └── footer.php             Pied de page
│
├── assets/
│   ├── css/style.css          Design system complet
│   ├── js/app.js              Interactions (wizard, tags, onglets…)
│   └── img/
│
└── data/
    └── workconnects.sqlite    Base de données (générée automatiquement)
```

---

## Les 6 phases du MVP

**Phase 1 — Fondations**
Landing page, inscription/connexion, rôles entreprise/freelance/admin, profils éditables.

**Phase 2 — Projets**
Formulaire progressif en 5 étapes, dashboards entreprise et freelance, cycle de statuts
(nouveau → analyse → attribué → en cours → livraison → terminé).

**Phase 3 — Matching et back-office**
Moteur de scoring, back-office séparé du site public, attribution de mission en un clic.

**Phase 4 — Communication**
Messagerie cloisonnée, gestion de fichiers par projet, notifications en temps réel.

**Phase 5 — Paiements**
Architecture de commission et de facturation automatique. Aucun flux financier réel activé.

**Phase 6 — Statistiques et UX**
Tableaux de bord chiffrés, système d'évaluation, responsive complet.

---

## Moteur de matching

Le score est une moyenne pondérée de critères **mesurables** (aucune IA générative) :

| Critère | Coefficient par défaut | Calcul |
|---|---|---|
| Correspondance des compétences | 40 % | Part des compétences requises présentes sur le profil |
| Adéquation budgétaire | 25 % | TJM × 30 jours comparé au budget moyen du projet |
| Disponibilité | 15 % | Disponible = 100, partiel = 50, occupé = 10 |
| Années d'expérience | 10 % | Normalisé sur 10 ans |
| Note moyenne | 10 % | Note sur 5 ramenée sur 100 |

Les coefficients sont modifiables dans **Back-office → Paramètres** et s'appliquent immédiatement.

---

## Sécurité

- Mots de passe hachés avec `password_hash()` (bcrypt)
- Requêtes préparées PDO sur 100 % des accès base
- Jeton CSRF sur tous les formulaires POST
- Échappement systématique des sorties via `htmlspecialchars()`
- Contrôle d'accès par rôle sur chaque page protégée
- Vérification de propriété sur le détail projet : seuls le client, l'expert affecté et l'administrateur y accèdent
- Régénération de l'identifiant de session à la connexion

---

## Cloisonnement des données (RGPD)

- L'entreprise ne voit jamais les coordonnées du freelance (nom affiché en forme abrégée)
- Le freelance ne voit jamais les coordonnées du client
- Aucun canal de communication direct entreprise ↔ freelance
- Toute la messagerie transite par WorkConnects : Entreprise ↔ WorkConnects et Freelance ↔ WorkConnects

---

## Compatibilité WordPress

Trois voies d'intégration possibles sans réécriture :

1. **Sous-répertoire** — déposer le dossier dans `/plateforme/` à côté de WordPress. Les deux cohabitent sans conflit.
2. **Sous-domaine** — `app.votredomaine.fr` pointant sur ce dossier, le site vitrine WordPress restant sur le domaine principal.
3. **Plugin** — la logique est déjà séparée en `includes/` (données, fonctions) et en pages de vue ; l'encapsulation dans un plugin ne demande que d'enrober les vues dans des shortcodes.

Le design system CSS est autonome et sans conflit de nommage avec les thèmes WordPress courants.

---

## Responsive

- **Ordinateur** — sidebar fixe, tableaux complets, grilles multi-colonnes
- **Tablette** — grilles réduites à 2 colonnes, sidebar resserrée
- **Smartphone** — menu burger, sidebar convertie en barre horizontale défilante, tableaux à défilement latéral, messagerie réorganisée verticalement, boutons pleine largeur
