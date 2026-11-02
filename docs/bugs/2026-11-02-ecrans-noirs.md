# Grands écrans sur trépied : diffusion et affichage TOC restent noirs

## Contexte
ATAK natif 2.9.28, Eden puis partie multijoueur, objet « Grand écran renforcé (noir) »
(`Land_TripodScreen_01_large_black_F`). Diffusion des diapositives, caméra en direct ou « Affichage TOC » lancés depuis
le menu ACE « Écran COMSPEC ». Même symptôme signalé le 27/10 sur le grand écran standard, jamais vérifié en jeu depuis.

## Symptôme
L'écran reste noir, que l'on diffuse des diapositives, la carte du TOC ou une caméra. Le menu ACE fonctionne.

## Cause
Configuration du jeu (`props_f_exp`, `props_f_enoch`, lue le 02/11) :
- `Land_TripodScreen_01_large_F` et ses variantes noire et sable ont onze sélections : `Screen_0` à `Screen_9`, puis
  `Camo_1` (le cadre). La dalle 0 couvre tout l'écran : c'est la seule que l'éditeur propose de texturer. Les dalles
  1 à 9 sont superposées à la dalle 0 (sans doute un quadrillage 3 x 3 ; non vérifiable sans le modèle source).
- Ces dalles ont la matière `\A3\Data_F\Lights\Lamp_lcd.rvmat` : lumière x200, rendue sans écriture de profondeur,
  prévue pour une dalle vide. Les variantes noire et sable remplacent en plus les textures des dalles par `""`.
- Le mod posait l'image (texture d'interface, caméra) sur la dalle 0 sans toucher ni à la matière, ni aux dalles
  superposées. L'image restait donc cachée ou éteinte.
- `_face` (`fn_screenCast`, `fn_tocScreen`) cherche une sélection nommée `screen` ou `display` : les noms `Screen_N`
  ne correspondent pas, et l'index 0 était gardé. C'est le bon index, mais rien d'autre n'était préparé.
- L'ancien pack TOC (Iceman, `atak_toc_screens.pbo`, toujours dans le pack FN) affiche ses caméras sur ces écrans en
  retirant la matière de la dalle (`setObjectMaterial [index, ""]`). Le mod natif ne le faisait pas.
- Les deux erreurs `hemtt check` sur `ctrlWebBrowserAction` (`fn_screenCast.sqf:410`, `fn_slideWebShow.sqf:17`) ne
  sont pas en cause. La commande existe dans le jeu : l'app Briefing et Overwatch l'utilisent et
  fonctionnent. HEMTT 1.22 ne connaît pas la commande et renonce donc à analyser ces deux fichiers.
- Défaut annexe : pour les joueurs autres que le diffuseur, le passage TOC → diapositives pouvait remettre la texture
  d'origine par-dessus la diapositive déjà posée. L'écran restait alors noir jusqu'à la diapositive suivante.

## Correctif
- `fn_tocScreen` a un nouveau mode `surface` (sur chaque poste, sans réseau). Quand une image est posée :
  - les autres dalles `screen_*` d'un mur de plus de deux dalles sont masquées ;
  - la dalle reçoit la matière d'écran du mod, celle de la tablette JPC (`jpc_screen.rvmat` le jour,
    `jpc_screen_night.rvmat` la nuit) ;
  - les valeurs d'origine sont gardées, puis remises à la fin de la diffusion ou quand on s'éloigne.
- Appelé par les trois chemins : carte TOC (`fn_tocScreen`), diapositives et leçons Academy (`fn_screenCast`), caméra
  en direct (`fn_tocCam`).
- Passage TOC → diapositives : la texture d'origine n'est plus remise si une diapositive est déjà posée.
- Écrans proposés (pose et menu ACE par classe) : ajout des doubles écrans `dual_v1` noir et sable, et `dual_v2`
  standard, noir et sable.
- Traces dans le journal du jeu :
  - une ligne par écran préparé : classe, dalle, matière d'origine, dalles masquées ;
  - un avertissement unique si l'image d'un écran TOC ne se charge pas en 4 s.
- Performances : rien de nouveau par image. La caméra en direct n'est toujours rendue que si un joueur regarde
  l'écran de près ; l'écran TOC n'est redessiné que s'il est vu.

## Fichiers touchés
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/toc/fn_tocScreen.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/toc/fn_tocCam.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/pages/fn_screenCast.sqf`

## Vérification
- `hemtt check` : seulement les deux erreurs connues sur `ctrlWebBrowserAction`.
- À tester en jeu, sur le grand écran standard, noir et sable, sur un double écran et sur une TV, de jour et de nuit :
  1. Eden en solo : ACE > Écran COMSPEC > Affichage TOC > Afficher le réseau TOC. La carte et le flux apparaissent.
     Le journal contient « Écran Land_TripodScreen_01_large_black_F : image sur la dalle 0 (screen_0) … 9 dalle(s)
     masquée(s) ».
  2. Diffusion > Diffuser mes diapositives (app Briefing ouverte sur une diapositive) : la diapositive remplace le TOC.
     Arrêter : le TOC revient.
  3. Diffusion > Caméra en direct (drone du camp) : image en direct à moins de 40 m de l'écran, rien au-delà.
  4. Serveur : un second joueur voit la même chose. Il rejoint pendant une diffusion : il voit aussi l'image.
  5. On s'éloigne à plus de 150 m puis on revient : l'écran retrouve son aspect normal, puis l'image.

## Statut
Corrigé dans les sources (2.9.29), à vérifier en jeu.
