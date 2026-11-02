# Carte ATAK : la couleur choisie d'un marqueur n'apparaît pas (icône grise)

## Contexte
ATAK natif 2.9.36 (pack FN), app Carte sur fond sombre, nuit ou satellite. Marqueur « IP INDIA » posé par TA1, couleur bleue
choisie dans la palette.

## Symptôme
La fiche du marqueur montre l'icône et la bordure en bleu, mais sur la carte de l'ATAK l'icône est gris clair. Même chose
pour les marqueurs reçus d'autres joueurs et d'Athena dans cette couleur.

## Cause
`fn_mapColors`, fonction `lift` (fonds sombres) : une couleur dont la luminance est sous 0,22 était remplacée par un gris
clair, pour qu'un marqueur noir reste visible. Le bleu pur d'Arma (`ColorBlue`, [0, 0, 1], luminance 0,11) tombait dans
ce cas et perdait sa teinte, comme tout bleu ou violet foncé. `fn_mapGameMarkers` (marqueurs de la mission et des joueurs)
et `fn_mapOnDraw` (marqueurs d'Athena) passent tous deux par `lift`. La vague perf 2.9.37 (cache des couleurs par classe)
garde les couleurs brutes et n'y est pour rien.

## Correctif
`lift` éclaircit en gardant la teinte (mélange vers le blanc : bleu → bleu clair) ; seuls le noir et les gris très foncés
deviennent gris clair.

## Suite 2.9.40 : couleurs délavées
Retour en 2.9.39 : marqueur « TEST » rouge, icône rouge vif dans sa fiche mais rose / gris pâle sur la carte. Le
correctif 2.9.38 modifiait encore la couleur (mélange vers le blanc des couleurs sombres) : il délavait les couleurs au
lieu de les rendre lisibles. Correction à la racine : l'icône est dessinée avec sa couleur exacte, alpha 1 (la même que
la fiche), et un halo (même icône 30 % plus grande, noir 0,75, ou blanc 0,7 sous une couleur sombre) assure la
lisibilité sur fond sombre ou satellite. Seules les couleurs vraiment trop sombres passent à leur version vive sur carte
sombre, par une table par classe (`ColorBlack` -> gris clair, `ColorBlue` -> #2F6BFF, `ColorWEST`, `ColorCIV`,
`ColorBrown`). Halo calculé à l'entrée du cache des marqueurs (rien de plus par image qu'un drawIcon). Le mode nuit
n'assombrit pas les marqueurs (palette seule) : rien à garder. Étiquettes inchangées (`liftLbl`).

## Fichiers touchés
`functions/map/fn_mapColors.sqf` ; 2.9.40 : aussi `fn_mapGameMarkers.sqf`, `fn_mapOnDraw.sqf`

## Vérification
En jeu, fond SOMBRE puis NUIT : marqueurs bleu, rouge, vert, noir posés et modifiés (MODIFIER → couleur) : bleu visible en
bleu clair, noir en gris clair ; marqueur d'un autre joueur et d'Athena idem.

## Statut
corrigé à vérifier en jeu (2.9.40 : rouge, bleu, noir, blanc, jaune sur TOPO, SOMBRE, NUIT, satellite)
