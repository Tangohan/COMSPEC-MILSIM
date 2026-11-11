/*
    comspec_fb_fnc_clock : horloge commune des détonations, en secondes.
    Multijoueur : serverTime (heure du serveur, tenue à jour par le moteur sur chaque client : une machine dont les
    images ralentissent ne prend pas de retard). Solo : CBA_missionTime.
    Toutes les heures absolues échangées (1re détonation, instants) sont sur cette horloge.
    call comspec_fb_fnc_clock -> secondes
*/
[CBA_missionTime, serverTime] select isMultiplayer
