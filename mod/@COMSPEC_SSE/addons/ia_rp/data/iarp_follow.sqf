/*
    Suivi d'un joueur par une IA (« Suivez-moi », fn_iarpFollow) : table de décision, lue une fois.
    [[clé, valeur]…] ; distances en mètres, vitesses du joueur en km/h, durées en secondes.
      stopAt     en mouvement, l'IA s'arrête dès qu'elle est à cette distance du joueur ou moins
      startAt    à l'arrêt, elle repart dès que le joueur est plus loin que cela (écart avec stopAt : pas d'à-coups)
      keep       point visé : à cette distance du joueur, de son côté
      pace       [[distance mini, vitesse mini du joueur, allure]…], première ligne vraie (l'une OU l'autre) :
                 run (course), jog (petite foulée), walk (marche)
      tick       période de la boucle        minOrder  délai mini entre deux ordres de déplacement
      replan     nouvel ordre seulement si le point visé a bougé de plus que cela
      stallAfter immobile ce temps alors qu'elle devrait avancer : elle vise le joueur lui-même
      lostAt     joueur plus loin que cela…  lostAfter  …pendant ce temps : suivi abandonné (« perdu de vue »)
    Banc d'essai : tests\Unit\SseIaSuivreAssetTest.php (mêmes règles, lues ici) et fn_iarpSelfTest « suivi ».
*/
[
    ["stopAt", 2.5],
    ["startAt", 4],
    ["keep", 2],
    ["pace", [[12, 15, "run"], [6, 7, "jog"], [0, 0, "walk"]]],
    ["tick", 1],
    ["minOrder", 1.5],
    ["replan", 1.5],
    ["stallAfter", 6],
    ["lostAt", 150],
    ["lostAfter", 45]
]
