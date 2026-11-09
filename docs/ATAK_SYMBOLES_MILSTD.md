# ATAK : symboles MIL-STD-2525D / APP-6D et curseurs GPS

Symboles dessinés par `mod/COMSPEC_ATAK_Native/tools/gen_milstd.py` (Pillow, géométrie écrite à la main d'après les
normes publiques, aucune image tierce), convertis en PAA DXT5 par ImageToPAA dans
`Sources/addons/items/data/milstd` (items.pbo, pas main.pbo). Couches blanches colorées au dessin : un seul jeu de
textures pour les thèmes clair, sombre et nuit.

## Contenu

- Cadres : ami (rectangle), hostile (losange), neutre (carré), inconnu (trèfle), en dimension terrestre, air (cadres
  ouverts en bas) et surface (cercle ami). Supposé ami et suspect : cadre ami / hostile en tirets. Prévu : cadre en tirets.
- Pictogrammes : infanterie, mécanisée, motorisée (véhicule léger), blindé, reconnaissance, artillerie, mortier, génie,
  transmissions, santé, commandement (C2), soutien (maintenance), logistique (ravitaillement), avion, hélicoptère, drone,
  sniper, antichar, forces spéciales (SF), JTAC (JFS), police militaire (MP), défense sol-air, civil (CIV).
- Échelons : équipe Ø, groupe ●, demi-section OTAN ●●, section / peloton ●●● (la « section » du site est le platoon OTAN),
  compagnie |, bataillon ||, régiment |||, brigade X, division XX.
- Hampe de QG, barre d'état (jaune : capacité réduite, rouge : détruit ou hors de combat), flèche de direction en mouvement,
  textes à gauche du cadre (désignation, unité supérieure ou vitesse) en vue rapprochée seulement.

## Règle d'association (fn_milSym)

Ordre de priorité, sans rien inventer : sans donnée, cadre générique sans pictogramme.

1. Affiliation : celle de la piste (ami, hostile, neutre, inconnu). Unité du camp civil : cadre neutre, fond violet, CIV.
2. Dimension : aéronef en vol (plus de 5 m, ne touche pas le sol) = air ; navire = surface ; sinon terrestre.
3. Pictogramme, à bord d'un véhicule : drone, avion, hélicoptère, mortier, arme fixe sol-air ou antichar, artillerie
   (`artilleryScanner`), santé (`attendant`), soutien (`transportRepair`), logistique (`transportFuel` / `transportAmmo`),
   VBTT à roues ou chenillé transportant 4 hommes ou plus = mécanisée, char = blindé, autre véhicule terrestre = motorisée.
4. Pictogramme, à pied : poste de l'ORBAT du site (fonction MIL-STD de l'unité) d'abord ; sinon trait médecin ou rôle MED
   = santé ; rôle ou classe Arma (JTAC, sniper / marksman, reco / éclaireur, mortier, opérateur drone, génie / EOD,
   antichar, sol-air, police militaire, radio, pilote d'hélicoptère ou d'avion, forces spéciales, logistique,
   réparation) ; sinon infanterie.
5. Échelon : titulaire d'un poste de chef (commandant ou adjoint) d'une unité de l'ORBAT du site = échelon de cette unité ;
   sinon chef d'un groupe Arma : équipe (4 au plus), groupe (13 au plus), section au-delà ; chef d'équipe de feu = équipe.
6. QG : unité de l'ORBAT marquée QG (code symbole du site), ou rôle « HQ / commandant / officier ».
7. État : véhicule endommagé (25 %) ou blessé = capacité réduite ; détruit, immobilisé ou inconscient = détruit.

Choix manuel : Réglages de la carte, « Forme » (mon symbole : AUTO 2525 ou un pictogramme ; l'échelon reste déduit) et
« Forme des alliés » (2525D). Le réglage « Icônes des unités » garde OTAN (textures d'Arma, par défaut) et POINTS, et gagne
2525D. Les anciens profils gardent leur forme.

## Code pour le site

`["sidc", descripteur] call comspec_atak_native_fnc_milSym` donne le code 2525D à 20 chiffres (identité, jeu de symboles,
statut, QG, échelon, entité), lu par milsymbol sur le site.

## Style tactique de la carte et contacts

Les symboles 2525D ne changent pas : ils restent dessinés entiers par `fn_milDraw` quand « Icônes des unités » vaut 2525D (cadre,
pictogramme, échelon, hampe), quel que soit le style de la carte. Le style tactique (Réglages > Carte > « Marqueurs et traits » :
TACTIQUE par défaut, CLASSIQUE, AUTO) habille le reste : pions, plaques, routes, zones, mesure.

Contacts non amis du style tactique (`fn_contactStyle`) : l'affiliation se lit à la forme, comme dans la norme, jamais à la couleur
seule. Ami : cercle ; ennemi : losange ; neutre : carré ; inconnu : carré à quatre bras ; civil : triangle. Les repères de classe
`o_`, `n_`, `u_`, `b_`, `c_` portent la même forme sous leur symbole. Une piste figée ou perdue est estompée et affiche son âge. Les
cadres 2525D (rectangle ami, losange hostile, carré neutre, trèfle inconnu) et ces formes de pions suivent donc la même convention.
