# Référence UX/UI mobile interne — Athena

> **Statut : référence, pas norme.** Ce document décrit les principes extraits de trois pages
> jugées réussies en mobile. Il sert d'inspiration pour les nouvelles pages, **pas** de gabarit à
> copier. Chaque module garde une UI adaptée à sa fonction : une carte tactique, un studio LMS et
> un annuaire n'ont pas les mêmes contraintes qu'une liste de documents.

## Pages de référence

| Page | Fichier | Ce qu'elle démontre bien |
|---|---|---|
| Documents | `views/documents/index.php` | En-tête riche + filtres denses qui restent lisibles en une colonne |
| Événements & opérations | `views/community/events.php` | Carte-événement dense, hiérarchie temporelle, actions inline |
| Messagerie interne | `views/messages/index.php`, `views/messages/thread.php` | Liste de conversations minimale, divulgation progressive du formulaire |

---

## 1. Hiérarchie

Les trois pages suivent le même ordre descendant :

1. **Bandeau d'identité** — micro-label en capitales espacées (`text-[10px] font-black uppercase
   tracking-[0.28em]`), puis `<h1>` `font-black tracking-tight`, puis un paragraphe d'intention
   de 1 à 2 phrases qui explique *à quoi sert la page*, pas ce qu'elle contient.
2. **Chiffres-clés / compteurs** — cartes statistiques à `tabular-nums`, avec un libellé court
   dessous (« Après filtres d'accès », « Dans la communauté »). Le chiffre seul ne suffit jamais.
3. **Contrôles** (recherche, filtres, tri) dans un bloc distinct, jamais mélangés au contenu.
4. **Contenu** — liste ou grille.
5. **Sortie** — un lien de retour explicite en bas de page (`← Retour tableau de bord`).

**Principe** : sur mobile, l'utilisateur doit comprendre *où il est* et *ce qu'il peut faire*
avant d'avoir scrollé.

## 2. Cartes et conteneurs

Deux niveaux de rayon, jamais trois :

- **Section de premier niveau** : `rounded-[2rem]`, `border border-slate-200`, `bg-white`,
  ombre très diffuse et basse opacité (`shadow-[0_20px_70px_-30px_rgba(15,23,42,0.14)]`).
- **Carte interne / ligne de liste** : `rounded-2xl` ou `rounded-xl`, `border`, `shadow-sm`.

L'ombre sert à séparer les plans, jamais à décorer. Pas d'ombre portée dure.

## 3. Densité

- Padding de section : `px-6 py-8` en mobile, `md:px-10 md:py-10` au-delà.
- Padding de ligne de liste : `px-4 py-3` — assez pour respirer, assez serré pour voir 5 à 7
  éléments sans scroller.
- Espacement vertical entre lignes : `space-y-2` (8 px). Entre sections : `mt-8` / `mb-8`.
- Le texte secondaire est tronqué (`line-clamp-2`, `truncate`) plutôt que rétréci. On ne descend
  jamais sous `text-xs` (12 px) pour du contenu lisible ; `text-[10px]` est réservé aux
  micro-labels en capitales.

## 4. Navigation

- **Une seule colonne en mobile, par défaut.** Les breakpoints ajoutent des colonnes
  (`sm:grid-cols-2`, `lg:grid-cols-3`), ils n'en retirent jamais. La page Messagerie
  n'utilise qu'un seul breakpoint (`sm:p-7`) : c'est un signe de santé, pas de pauvreté.
- Largeur maximale contextuelle : `max-w-3xl` pour la lecture (messagerie),
  `max-w-[1600px]`/`max-w-[1800px]` pour les catalogues.
- Retour explicite en fin de page plutôt que dépendance au bouton natif du navigateur.
- La barre basse (`views/partials/bottom_nav.php`) plafonne à 5 slots. Toute nouvelle page
  se rattache à un slot existant plutôt que d'en réclamer un.

## 5. Actions et boutons

Trois niveaux, pas plus, par écran :

| Niveau | Style | Usage |
|---|---|---|
| Primaire | `rounded-2xl bg-slate-950 text-white` | Une seule action par écran |
| Secondaire | `rounded-2xl border border-slate-300 bg-white text-slate-700` | Actions de gestion |
| Tertiaire | lien souligné `underline decoration-slate-300 underline-offset-2` | Navigation, retour |

- Libellés en capitales espacées (`text-[11px] font-black uppercase tracking-[0.14em]`) pour les
  boutons ; en casse normale pour les liens.
- Hauteur de frappe : `py-3` sur les boutons pleins, `py-2.5` minimum — jamais moins de ~44 px de
  zone tactile effective avec le padding horizontal.
- Une ligne de liste entière est cliquable (`<a>` englobant), avec un chevron à droite comme
  affordance. On ne met pas de bouton dans une ligne déjà cliquable.

## 6. Filtres

- Regroupés dans un `<form method="get">` unique, avec une phrase qui explique la combinaison
  (« Les champs s'appliquent ensemble »).
- Grille responsive `grid gap-4 md:grid-cols-2 xl:grid-cols-6` : en mobile tout s'empile,
  en desktop tout tient sur une ligne.
- Un indicateur booléen de filtres actifs (`$hasActiveFilters`) conditionne l'affichage d'une
  remise à zéro. Sans filtre actif, pas de bouton « réinitialiser » qui encombre.
- Icône de recherche en `absolute` dans le champ, `pointer-events-none`.

## 7. Espacements

Échelle utilisée, en pratique : `0.25 / 0.5 / 0.75 / 1 / 2 / 4 / 6 / 8` (rem × 0.25).
Les valeurs intermédiaires (`gap-0.5`, `mt-1.5`) ne servent qu'aux ajustements optiques
(alignement d'un chevron sur une première ligne de texte).

## 8. Affichage de l'information

- **Statuts par la couleur ET par le texte.** Un badge `À lire` accompagne toujours le fond
  `bg-emerald-50/40` — jamais la couleur seule.
- **Palette sémantique stable** : `emerald` = actif/positif/à traiter, `amber` = attention,
  `rose` = erreur/refus, `slate` = neutre. Cette convention est respectée dans les trois pages.
- **Avatar/initiale de repli** : un carré `h-9 w-9 rounded-lg` avec l'initiale, coloré selon
  l'état. Évite les images manquantes.
- **Aperçu tronqué serveur** : la troncature à 140 caractères se fait en PHP (`mb_substr` avec
  repli `substr`), pas seulement en CSS — le HTML reste léger.

## 9. Comportement responsive

- **Mobile-first strict** : les classes de base sont l'état mobile ; `sm:`/`md:`/`lg:`/`xl:`
  n'ajoutent que de l'enrichissement.
- Les grilles denses en desktop retombent en pile verticale, jamais en scroll horizontal.
- Les tableaux à plus de 3 colonnes sont remplacés par des cartes en mobile plutôt que
  compressés.
- Le CSS spécifique de la page Événements utilise une seule media query
  (`@media (min-width: 640px)`) pour passer les colonnes RSVP de 1 à 3.

## 10. Interaction tactile

- États de survol *et* transitions douces (`transition hover:border-emerald-300`), qui font aussi
  office de retour visuel au `:active` tactile.
- `<details>/<summary>` pour la divulgation progressive (formulaire « Nouvelle demande »),
  avec `[&::-webkit-details-marker]:hidden` et un libellé qui bascule
  (`group-open:hidden` / `hidden group-open:inline`). Zéro JavaScript.
- `aria-hidden="true"` systématique sur les SVG décoratifs, `aria-current="page"` sur l'onglet actif.
- Champs de saisie avec `focus:ring-2 focus:ring-emerald-100` : anneau visible, non agressif.

## 11. États vides

Le modèle de la messagerie est le meilleur du projet :

```
bordure pointillée + fond très clair
  → icône neutre dans un carré bordé
  → phrase d'état  (« Aucune conversation pour l'instant »)
  → phrase d'action (« Ouvrez une nouvelle demande ci-dessous… »)
```

Un état vide sans phrase d'action est considéré comme incomplet.

## 12. Messages de flash

Format constant : `rounded-xl border px-4 py-3 text-sm font-medium`, placé juste sous l'en-tête,
avant le contenu. Couleurs `rose` (erreur) / `emerald` (succès) / `amber` (avertissement bloquant).

---

## Pages à rapprocher de ce niveau

Par ordre de bénéfice décroissant, sans uniformisation aveugle :

1. **`views/personnel/file.php`** (1 943 lignes) — la fiche personnelle est la page la plus
   consultée après le dashboard et la plus dense ; elle mérite le traitement carte/section.
2. **`views/admin/recruitments/show.php`** (1 997 lignes) — le suivi de candidature est un
   workflow ; il gagnerait la hiérarchie « état → actions → historique ».
3. **`views/dashboard.php` / `views/partials/dashboard_command_center.php`** — beaucoup de blocs
   sans hiérarchie stable entre eux.
4. **Catalogue et parcours de formation** — proches du cas « Documents » (liste filtrable).

À **ne pas** aligner : `views/atak.php`, `views/overwatch/index.php`, les shells LMS/Studio et
la carte tactique. Ce sont des interfaces outil plein écran, avec leurs propres contraintes de
densité et de latence.
