/*
    true quand COMSPEC Overwatch (addon connect) est chargé : il gère déjà la session Athena,
    la remontée de position, la synchro des marqueurs dans les deux sens, le chat et les ordres.
    Le terminal natif lit alors ses données au lieu d'ouvrir une seconde session sur sa propre DLL.
*/
!isNil "comspec_overwatch_connect_fnc_isReady"
