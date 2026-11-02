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

## Fichiers touchés
`functions/map/fn_mapColors.sqf`

## Vérification
En jeu, fond SOMBRE puis NUIT : marqueurs bleu, rouge, vert, noir posés et modifiés (MODIFIER → couleur) : bleu visible en
bleu clair, noir en gris clair ; marqueur d'un autre joueur et d'Athena idem.

## Statut
corrigé à vérifier en jeu
