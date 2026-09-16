# Visées impossibles à supprimer (Overwatch Beta)

## Contexte

Outil Visée / masque sur Overwatch Beta. Plusieurs traits (dégagé + masqué) restent sur la carte après le calcul.

## Symptôme

Clic droit sur une visée : le menu ne propose pas **Supprimer**. Les anneaux de portée et le trait d’interception se comportent de la même façon. Les traits s’empilent.

Variante : le menu propose Supprimer, mais le poste tente de retirer un **marqueur voisin** (erreur « introuvable » répétée). La visée reste.

## Cause

Le dessin de visée posait les polylignes directement sur la carte, sans les enregistrer comme élément retirable. Le menu ne regardait qu’un calque unique jamais renseigné, et ne détectait le clic que sur les extrémités (pas au milieu d’un long trait). Les anneaux n’étaient pas cliquables.

Ensuite, un marqueur dans un rayon trop large passait avant le trait : le clic sur la visée visait le repère, souvent déjà absent du poste.

## Correctif

- Chaque visée est un groupe nommé « Visée », retirable au clic droit (y compris au milieu du trait).
- Les anneaux de portée et le relevé d’interception passent par le même menu.
- Un clic sur un vieux trait orphelin ne retire que ce trait, pas les visées déjà identifiées.
- Le plus proche l’emporte : un trait sous le curseur n’est plus volé par un marqueur à côté.
- Le menu indique Visée (ou le nom du repère) avant Supprimer.
- Un repère déjà absent quitte la carte sans bloquer.

## Fichiers touchés

- `public/assets/js/atak-overwatch-beta.js`
- `public/assets/js/atak-overwatch-ops.js`
- `public/assets/js/atak-overwatch-tools.js`
- `public/assets/js/atak-overwatch-gotak.js`

## Vérification

Recharger Overwatch Beta. Tracer une visée, clic droit sur le trait : le menu affiche **Visée**, **Supprimer** retire le trait (pas un marqueur à côté).

## Statut

corrigé
