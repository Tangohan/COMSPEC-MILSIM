# Bug — Type de fiche et urgence illisibles

## Contexte

13 septembre 2026. Rédaction d’une fiche de renseignement dans le panneau Intel du poste. Les listes Type de fiche et Urgence s’affichent comme de grandes cases vides, le texte collé et coupé à droite.

## Symptôme

Chaque option (FRM, FRO, urgence…) apparaît comme un large rectangle sombre avec un point radio, et le libellé déborde hors de la case. Impossible de lire « Fiche de renseignement de mission » ou « Routine ».

## Cause

Les blocs Type de fiche et Urgence sont des `fieldset` avec la classe des champs de saisie. Une règle destinée aux champs texte (`width: 100%`, fond, padding, bordure) s’appliquait aussi aux boutons radio. Le radio s’étirait sur toute la largeur et poussait le texte hors de la case.

## Correctif

La règle des champs texte ignore désormais les boutons radio et les cases. Les options redeviennent une ligne compacte : petit choix à gauche, code et intitulé lisibles à droite, texte qui revient à la ligne.

## Fichiers touchés

- `public/assets/css/atak.css`

## Vérification

Contrôle des règles CSS (radio non étiré, libellé avec retour à la ligne). Rechargez le poste, Intel → Fiches → Nouvelle fiche : chaque type affiche son nom complet dans la case.

## Statut

Corrigé
