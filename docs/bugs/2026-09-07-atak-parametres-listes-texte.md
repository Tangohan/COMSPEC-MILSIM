# Texte trop petit dans les listes des paramètres ATAK

## Contexte

Téléphone ATAK en jeu, application Paramètres. Les listes (profil audio, précision de grille, police de la mini-carte) étaient difficiles à lire.

## Symptôme

Le texte des champs de sélection paraissait minuscule, surtout le profil audio.

## Cause

Le profil audio forçait une taille très réduite. Les autres listes n’avaient pas de taille explicite et héritaient d’un texte trop petit sur le terminal.

## Correctif

Taille portée à 13 px pour toutes les listes de l’écran Paramètres.

## Fichiers touchés

- `mod/Overwatch 2026/ProdVersion/GPT/@COMSPEC_ATAK/addons/comspec_atak_core/web/phone.html`
- `mod/Overwatch 2026/ProdVersion/GPT/Version chrome/@COMSPEC_ATAK/addons/comspec_atak_core/web/phone.html`

## Vérification

Ouvrir ATAK → Paramètres → dérouler profil audio, grille et police : le libellé choisi et les options doivent être lisibles.

## Statut

Corrigé (pack à reconstruire / relancer Arma).
