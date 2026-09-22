# Mode Roleplay — formulaire illisible (colonne écrasée)

**Statut :** corrigé (sources)

## Contexte

Page back-office `/back-office/atak/roleplay` (vue `roleplay_enhanced.php`, dashboard 2 colonnes).

## Symptôme

Sur grand écran, le formulaire (Simulation réseau, zones, etc.) apparaît dans une bande étroite à gauche : libellés tronqués, champs empilés, lecture quasi impossible. Les cartes « Tests serveur » / « Carte des zones » restent à largeur normale ; un grand vide occupe le centre.

## Cause

La grille utilisait `xl:grid-cols-12` + `xl:col-span-8` / `xl:col-span-4`.

Le CSS Tailwind compilé (`public/assets/css/tailwind.css`) contenait `xl:grid-cols-12` et `xl:col-span-4`, **mais pas** `xl:col-span-8` (classe absente du scan/purge au moment du build).

Résultat : à partir du breakpoint `xl`, la grille passe à 12 colonnes, la colonne droite s’étend correctement sur 4, la colonne config reste à **1 colonne sur 12**.

## Correctif

- Remplacer `xl:grid-cols-12` / `xl:col-span-8` / `xl:col-span-4` par les variantes `lg:` déjà présentes dans le CSS compilé.
- Ajouter `min-w-0` sur les colonnes.
- Safelister les classes de grille dans `tailwind.config.js` pour les prochains builds.

## Fichiers touchés

- `views/admin/atak/roleplay_enhanced.php`
- `tailwind.config.js`
- `routes/web.php` (endpoint tests serveur)
- `app/Controllers/Admin/AdminAtakRoleplayController.php`

## Vérification

- Recharger `/back-office/atak/roleplay` (≥ 1024 px) : colonne config ≈ 2/3, tests/carte ≈ 1/3, formulaires lisibles.
- Contrôle optionnel `?legacy=1` : ancienne vue intacte.

## Statut

corrigé (sources) — déploiement portail requis pour athena.ttrd.fr
