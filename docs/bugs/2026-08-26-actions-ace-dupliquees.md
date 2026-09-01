# Bug — menu d’actions personnelles recopié sur tout l’écran

## Contexte

En session Arma, menu d’actions personnelles (Overwatch, photo, carte, santé) ouvert pendant que le terminal ATAK est à la main, souvent après un ou plusieurs retours au combat.

## Symptôme

Liste verticale d’icônes et de libellés qui couvre toute la hauteur de l’écran, hors fenêtre : « COMSPEC Overwatch », « Envoyer photo », « Relayer le lien », « Montrer la carte », « Transmettre bilan santé », « Interactions personnelles », et les icônes d’équipement / radio / médical, recopiés des dizaines de fois jusqu’à se chevaucher.

## Cause

Les actions Overwatch étaient collées **sur l’unité** (`addActionToObject`) au lieu d’une fois sur la classe. Au Respawn :

1. ACE recopie la liste objet de l’ancienne unité vers la nouvelle ;
2. le script remettait les drapeaux « déjà installé » à faux après la grâce, puis **ré-ajoutait** tout le menu ;
3. chaque retour au combat empilait une copie de plus (15 REAPP → 15 fois la même ligne).

Même schéma pour le clic droit Zeus (contexte joueur) : première passe trop tôt sans drapeau, seconde passe quand ZEN est prêt → double enregistrement.

En plus, « Rédiger une fiche de renseignement » était déclarée **deux fois** dans la même init.

Les libellés photo / relais / carte d’autres packs (cTab, bibliothèque photo) subissent le même empilement s’ils sont aussi collés sur l’unité : le menu ACE passe alors en liste pleine hauteur, tout se mélange.

## Correctif

- Installer les self-actions **une fois** sur `CAManBase` (classe), avec une clé path + identifiant.
- Au Respawn : retirer les copies objet, **ne plus** ré-installer.
- Garder les actions « sur une personne » (saisir l’ATAK, couper le GPS) derrière un drapeau qui ne se réinitialise pas au Respawn.
- Clic droit Zeus joueur : drapeau de contexte distinct, posé dès le premier enregistrement.
- Retirer le doublon de fiche de renseignement.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_aceAddSelfAction.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_aceSweepPlayerSelfActions.sqf` (nouveau)
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initACE.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_initATAK.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_onPlayerRespawn.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_registerZenAtakPlayerActions.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_registerZenTrackActions.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp` (1.4.86)

## Vérification

1. Rebuild du PBO `connect` (Overwatch 1.4.86) — relancer Arma, pas seulement la mission.
2. Activer les menus d’actions Overwatch, ouvrir le menu personnel : une seule entrée « COMSPEC Overwatch », enfants non recopiés.
3. REAPP / Respawn plusieurs fois, rouvrir le menu : toujours une seule pile, pas de dump plein écran.
4. Zeus : clic droit sur un joueur → un seul dossier ATAK.

(Non vérifié en jeu ici : pas de client Arma dans cet atelier.)

## Statut

corrigé (pack Overwatch 1.4.95)
