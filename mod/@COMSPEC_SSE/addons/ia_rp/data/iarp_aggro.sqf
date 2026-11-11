/*
    Agressivité des IA du SSE (fn_iarpAggro, fn_iarpMelee) : tables, lues une fois. [[clé, valeur]…]
      modes     niveaux du réglage « Agressivité » : off (jamais), verbal (insultes, menaces, refus), physical (bouscule, frappe)
      traits    [id, gain de colère ×, retombée par minute, bluffe (ne frappe jamais), chance de fuir après la bagarre,
                 bonus de chance de toucher]
                none (sans trait), hostile, irritable, drunk (ivre), fanatic, resentful (rancunier), proud (fier),
                bluff (lâche qui bluffe)
      triggers  [id, colère ajoutée] : insulted (insulté), threatened (menacé), searched (fouillé), papers (papiers
                contrôlés), pushed (bousculé, geste /me), weapon (arme braquée), ordered (ordre reçu), provoked (provoqué
                dans la durée : par tranche de 10 s passée à moins de 3 m d'une IA déjà hostile), side (camp du joueur
                ennemi du sien, une fois), zeus (ordre du Zeus : passe tout de suite aux coups)
      stages    [id, colère mini, geste physique] du plus calme au plus violent : calm, irritated (agacé), hostile,
                shove (bouscule, gifle), punch (coups de poing). Un palier « physique » demande le niveau physical et un
                trait qui ne bluffe pas ; sinon l'IA reste à « hostile ».
      melee     reach (portée d'un coup, m), approach (distance d'approche, m), cooldown (s entre deux coups), hitChance
                (chance de toucher), maxSwings (coups par bagarre), chase (abandon si le joueur est plus loin, m), giveUp
                (abandon si elle ne l'a pas rejoint, s), rest (s sans nouvelle bagarre après une bagarre), perVictim
                (IA au plus sur le même joueur en même temps)
      damage    dégâts par coup pour l'intensité 50 (0-1, vie du jeu) : shove, slap, punch ; perMinute (plafond de dégâts
                reçus par un joueur en 60 s, intensité 50) ; nonLethalCeil (sans « mortel », aucun coup ne porte un joueur
                au-delà de ces dégâts) ; koChance (chance d'assommer par coup de poing, réglage « assommer » coché) ;
                knockShove / knockPunch (recul du joueur, m/s)
    Banc d'essai : tests\Unit\SseIaAgressiviteAssetTest.php (mêmes règles, lues ici) et fn_iarpSelfTest « agressivité ».
*/
[
    ["modes", ["off", "verbal", "physical"]],
    ["traits", [
        ["none", 1, 20, false, 0.3, 0],
        ["hostile", 1.5, 8, false, 0.05, 0.1],
        ["irritable", 1.3, 25, false, 0.2, 0],
        ["drunk", 1.2, 10, false, 0.1, -0.2],
        ["fanatic", 1.6, 5, false, 0, 0.1],
        ["resentful", 1.1, 4, false, 0.1, 0],
        ["proud", 1, 12, false, 0.1, 0.05],
        ["bluff", 1.4, 20, true, 0.9, 0]
    ]],
    ["triggers", [
        ["insulted", 25],
        ["threatened", 20],
        ["searched", 18],
        ["papers", 6],
        ["pushed", 35],
        ["weapon", 15],
        ["ordered", 8],
        ["provoked", 6],
        ["side", 10],
        ["zeus", 100]
    ]],
    ["stages", [
        ["calm", 0, false],
        ["irritated", 20, false],
        ["hostile", 40, false],
        ["shove", 60, true],
        ["punch", 80, true]
    ]],
    ["melee", [
        ["reach", 1.6],
        ["approach", 1.3],
        ["cooldown", 2.5],
        ["hitChance", 0.7],
        ["maxSwings", 6],
        ["chase", 25],
        ["giveUp", 20],
        ["rest", 60],
        ["perVictim", 2]
    ]],
    ["damage", [
        ["shove", 0.02],
        ["slap", 0.03],
        ["punch", 0.08],
        ["perMinute", 0.3],
        ["nonLethalCeil", 0.6],
        ["koChance", 0.15],
        ["knockShove", 3],
        ["knockPunch", 1.5]
    ]]
]
