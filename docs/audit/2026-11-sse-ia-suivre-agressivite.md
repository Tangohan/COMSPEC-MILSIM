# SSE : « Suivez-moi » qui ne suit pas, et IA hostiles (audit, novembre 2026)

Lecture du code sur `origin/main` (addons `ia_rp` et `identity` du pack `@COMSPEC_SSE`) et du dernier RPT de TTRD
(aucune erreur de script SSE sur ce sujet). Rien n'a été lancé en jeu : les causes ci-dessous viennent du code.

## 1. Pourquoi l'IA ne suit pas

L'ordre existe : « Suivez-moi » est compris (`follow`), le serveur décide (`fn_iarpServer.sqf`), l'ordre part là où l'IA
est locale (`comspec_sse_iarpAiDo`, `ia_rp/XEH_postInit.sqf`). Le suivi lui-même tenait en quinze lignes
(`ia_rp/XEH_postInit.sqf:62-77`) : un `doMove` vers le joueur toutes les 2 s, sans état propre. Cinq défauts, du plus
fréquent au plus rare :

| # | Défaut | Où | Effet vu par le joueur |
|---|--------|----|------------------------|
| 1 | Une IA calme refuse un ordre presque une fois sur deux : 0,45 + peur − agressivité + confiance = 0,56 pour le profil par défaut | `fn_iarpServer.sqf:669-681` (`_obeys`), appelé en `:789` | « Je ne vous suis nulle part. » 44 fois sur 100, sans raison visible |
| 2 | Le `doMove` est redonné à chaque passage, même quand le joueur n'a pas bougé : chaque ordre relance le calcul du chemin | `XEH_postInit.sqf:75` | L'IA piétine ou reste figée, surtout en bâtiment |
| 3 | Aucune allure : jamais de `setSpeedMode`, et « Approchez » / « Reculez » laissent `forceSpeed 1.5` pour toujours | `XEH_postInit.sqf:51`, `:65-66` | Elle marche pendant que le joueur court, passe 80 m et abandonne sans rien dire (`:71`) |
| 4 | N'importe quel autre ordre annule le suivi, même un simple regard (témoin d'une scène) ou un demi-tour ; durée limitée à 4 min | `XEH_postInit.sqf:18`, `:76` | Le suivi s'arrête tout seul |
| 5 | Mains en l'air ou menottée (ACE) : elle répond « D'accord, je vous suis » (`_obeys` vrai en état « rendu », `:674`) mais l'animation ACE l'empêche de marcher ; et le « stop » de l'automate des IA réalistes (`identity/XEH_postInit.sqf:45` : `doStop` + `disableAI "PATH"`, envoyé par `fn_idAiEnter.sqf:48`) n'est jamais annulé par le suivi s'il arrive après | idem | Elle dit oui et ne bouge pas |

Aucun état « suit le joueur » n'était montré nulle part.

**Correction (commit isolé)** : `fn_iarpFollow` (nouveau) tient le suivi là où l'IA est locale, avec un état gardé sur
l'unité. Table de décision `data/iarp_follow.sqf` : arrêt à 2,5 m, départ au-delà de 4 m, marche / petite foulée / course
selon la distance et l'allure du joueur ; un `doMove` seulement si le point visé a bougé de 1,5 m, ou si elle est bloquée
depuis 6 s (elle vise alors le joueur lui-même) ; chemin rétabli à chaque passage (`enableAI "PATH"`) ; reprise sur la
nouvelle machine si l'IA change de propriétaire (client sans tête). Plus de limite de durée : fin sur « Reste ici »,
« Circulez », un autre ordre de déplacement, la mort, le départ du joueur, 150 m pendant 45 s, ou la fuite / l'hostilité
décidée par l'automate. Côté serveur : bonus d'obéissance pour cet ordre anodin (86 % pour le profil par défaut), refus
joué quand elle a vraiment peur, réplique propre quand elle est menottée (escorte ACE), mains baissées avant de marcher.
État « Suit le joueur » dans l'en-tête de « Parler à » (variable diffusée `comspec_sse_iarpFollowing`).

## 2. L'automate existant

- `fn_idAiFsm` (serveur, CBA statemachine) : calme, interpelle, coopere, nerveux, ment, refuse, fuit, agressif, rendu,
  renforts. `fn_idAiBrain` calcule la tension (0-100) une fois par seconde, `fn_idAiEnter` donne les ordres réels.
  « agressif » ne concerne que les insurgés et HVT **armés** : ils passent dans un groupe ennemi et tirent.
- `fn_iarpProfile` : curseurs par IA (nervosité, agressivité 0-100, peur, ivresse…), émotions actives (peur, armes,
  nervosité, colère, méfiance, soulagement) par IA ou globales.
- `fn_iarpServer` « say » : intentions du message (insulte, menace, fouille, papiers, ordres…), confiance par joueur,
  pression de la conversation (`fn_iarpReact` « press »).
- Rien ne permet à un civil **sans arme** de s'en prendre physiquement à un joueur.

## 3. Où se branche l'agressivité

- Nouveau `fn_iarpAggro` (serveur) : colère par couple IA/joueur, alimentée par les intentions déjà reconnues
  (`fn_iarpServer` « say » : insulte, menace, fouille, papiers, ordres, geste /me « pousse »), l'arme braquée
  (`fn_iarpReact` « aimedAt »), la durée de la provocation, le camp du joueur et l'ordre du Zeus. Échelle : agacé,
  hostile, bousculade, coups, puis fuite ou arme sortie. Tables dans `data/iarp_aggro.sqf`.
- Nouveau `fn_iarpMelee` (là où l'IA est locale) : approche, coup, dégâts plafonnés ; passe par TSP Melee quand il est
  chargé (`tsp_fnc_melee_action`), gestes de TSP Animate pour les provocations, sinon gestes du jeu.
- Répliques : `data/iarp_hostile.sqf` (niveau × caractère, déclencheur, registre, pays), sans redite, dans la bulle et la
  fenêtre « Parler à » déjà en place. Aucune fenêtre d'alerte du jeu.
- Réglages CBA, attributs Eden, menu Zeus (ZEN) : niveau, intensité des dégâts, mortel. Coupé par défaut.
- Hors périmètre : IA contre IA. `fn_radioGuard` et le contrôle d'identité entre joueurs ne sont pas touchés.

## 4. Mods TSP de TTRD (lecture seule du dossier Workshop, aucun code ni fichier repris)

| Mod | Dossier Workshop | CfgPatches | Ce qu'on en utilise |
|-----|------------------|------------|---------------------|
| TSP Melee | `3026491672` (pack « # S.O.A.R - FN », `tsp_melee.pbo` et ses armes) | `tsp_melee` (requiert `tsp_core`) | `[ia, mode, vrai] spawn tsp_fnc_melee_action` (modes `push`, `main`, `alt`), gardes `tsp_fnc_melee_can` et `tsp_fnc_melee_weapon` (= `fist`), réglage `tsp_cba_melee`, évènement CBA local `tsp_melee_damageMan` [attaquant, victime], `tsp_fnc_melee_stun`, `CfgMelee >> fist` (dégâts estimés) ; gestes `tsp_melee_fistPunchRight`, `tsp_melee_fistPunchLeft`, `tsp_melee_fistPush`, mouvement `tsp_melee_fistStun` |
| TSP Core | `3283642267` (« Speshal Core ») et copie du pack | `tsp_core` | rien directement : `tsp_fnc_gesture_stop` et l'action `tsp_common_stop` pour rendre la garde |
| TSP Animate | `3283612524` (« Animate - Rewrite ») et copies des packs | `tsp_animate` (+ `tsp_animate_ace`) | gestes `tsp_animate_bird_loop`, `tsp_animate_thumbsdown_loop`, `tsp_animate_loser_loop` quand une IA insulte |

- TSP Melee sait faire frapper une IA : `tsp_fnc_melee_action` choisit alors lui-même sa victime (l'homme le plus proche
  à 2,5 m) et applique lui-même dégâts, sonné, assommé, sons et parade (`tsp_fnc_hitpoint_damage`, ACE médical compris).
  Le SSE ne l'appelle donc que si le joueur visé est bien le plus proche, n'ajoute aucun dégât, et fait jouer ses
  plafonds en **arrêtant** la bagarre (pas en rognant le coup). Pour un plafond exact : réglage « Intégré SSE ».
- `tsp_fnc_melee_ai` (boucle de combat IA de TSP) n'est jamais appelée : elle vise tout ce qui n'est pas ami et ne
  s'arrête qu'à la mort de l'IA.
- TSP Animate n'a ni coup de poing, ni mains en l'air, ni marche d'escorte utilisable par une IA (ses gestes de captif
  sont ceux d'ACE, déjà suivis) : seuls ses gestes de provocation servent. Rien dans ses fichiers ne parle des IA.
- Sans TSP : Arma 3 n'a aucune animation de coup de poing debout. Le moteur intégré joue un signe de la main du jeu
  (`GestureAttack`, `GestureGo`…, le premier qui existe), un court élan, un recul du joueur, une secousse de caméra et
  un son de coup du jeu. C'est lisible, pas beau.

## 5. Non vérifié en jeu

Tout (Arma n'a pas été lancé) : allure réelle du suivi et passage des portes, reprise sur client sans tête, rendu des
gestes, `tsp_fnc_melee_action` lancée sur un serveur dédié, dégâts réels avec ACE médical, hauteur de la fenêtre
« SSE · <nom> » avec une ligne de plus, listes des attributs Eden.
