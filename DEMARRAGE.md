# WorkConnects — Guide de démarrage

Le site est livré **vide**, prêt pour une utilisation réelle.
Aucun projet, aucun message, aucune facture fictive : tous les compteurs
démarrent à zéro et se remplissent avec votre activité réelle.

## 1. Avant la mise en ligne

Ouvrez `config.php` et changez **au minimum** le mot de passe administrateur :

```php
define('ADMIN_EMAIL',    'votre@email.fr');
define('ADMIN_PASSWORD', 'un-mot-de-passe-solide');
```

> Ces valeurs ne servent qu'à créer le compte au tout premier chargement.
> Si vous les modifiez ensuite, supprimez `data/workconnects.sqlite`
> pour repartir d'une base neuve.

## 1 bis. Comptes de test

Trois comptes sont créés à la première ouverture :

| Rôle | Identifiant | Mot de passe |
|---|---|---|
| Administrateur | `admin@workconnects.fr` | `admin123` |
| Entreprise | `entreprise@test.fr` | `test123` |
| Freelance | `freelance@test.fr` | `test123` |

Ils servent à parcourir la plateforme dans les trois rôles sans rien saisir.
Aucun projet, message ni facture fictif : les tableaux de bord restent à zéro.

**Avant la mise en ligne**, désactivez les deux comptes de test dans
`config.php` :

```php
define('COMPTES_TEST', false);
```

puis supprimez `data/workconnects.sqlite` pour repartir d'une base propre.
Seul le compte administrateur sera recréé.

## 2. Installation sur l'hébergeur

1. Envoyez tous les fichiers dans le dossier public (`public_html`).
2. Donnez les droits d'écriture **755** aux dossiers `data/` et `uploads/`.
3. Ouvrez le site : la base de données se crée automatiquement.

Prérequis : PHP 8.0 ou supérieur avec l'extension SQLite (standard partout).

## 3. Premiers pas

1. Connectez-vous avec le compte administrateur défini dans `config.php`.
2. Réglez vos coefficients de matching et votre commission dans
   **Back-office → Paramètres**.
3. Les entreprises et freelances s'inscrivent eux-mêmes depuis le site.

## 4. Le cycle d'un projet

```
Entreprise dépose un projet   →  statut « nouveau »
  ↓
Vous l'étudiez au back-office →  « analyse »
  ↓
Vous attribuez un freelance   →  « attribué »   (score de matching à l'appui)
  ↓
Production                    →  « en cours »   (avancement en %)
  ↓
Livraison                     →  « terminé »    + facture et évaluation
```

L'entreprise ne cherche jamais de freelance elle-même : vous pilotez
l'attribution depuis le back-office.

## 5. Remettre le site à zéro

Supprimez `data/workconnects.sqlite`. Au prochain chargement, une base
neuve est recréée avec le seul compte administrateur.

## 6. Sauvegarde

Deux éléments à sauvegarder régulièrement :
- `data/workconnects.sqlite` — toutes les données
- `uploads/` — les fichiers livrés et les portfolios


## 7. Modèle de paiement

**La part WorkConnects est incluse dans le prix affiché au client.**

Un freelance qui annonce 2 500 € touche 2 500 €. Le client voit 3 000 €
dès le départ (avec un taux à 20 %) et ne voit jamais apparaître de
ligne de commission. Le détail n'existe que dans le back-office.

### Les deux échéances

| Étape | Quand | Montant | Effet |
|---|---|---|---|
| 1. Validation | À l'attribution de l'expert | 2 € de frais de dossier | Lance la mission |
| 2. Règlement | **À la livraison du livrable** | Prix total (part WC incluse) | Clôture le projet |

Le règlement de l'étape 2 est **verrouillé** tant que le projet n'est
pas passé au statut « Terminé ».

Les 2 € restent **en suspens** pendant toute la durée du projet :

- projet livré → statut `libere`, les frais sont acquis
- projet annulé → statut `rembourse`, les 2 € sont rendus au client

### Régler les montants

- Taux WorkConnects : Back-office → Paramètres → Commission
- Frais de dossier : `FRAIS_DOSSIER` dans `config.php` (2 € par défaut)

### Raccordement à un prestataire de paiement

Les règlements sont enregistrés en base mais aucun flux bancaire n'est
déclenché. Pour encaisser réellement, brancher le prestataire sur la
fonction `regler_paiement()` dans `includes/functions.php`.
