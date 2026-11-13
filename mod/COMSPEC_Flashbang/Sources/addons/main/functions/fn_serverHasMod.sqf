/*
    comspec_fb_fnc_serverHasMod : le serveur de la partie a-t-il le mod ? Vrai sur le serveur lui-même (solo, hébergé,
    dédié avec le mod). Sur un client : le serveur qui a le mod publie sa version au postInit (variable publique
    comspec_fb_serverVersion, reçue aussi par les joueurs qui arrivent en cours de partie) ; absente = serveur sans le mod.
    call comspec_fb_fnc_serverHasMod -> bool
*/
isServer || {(missionNamespace getVariable ["comspec_fb_serverVersion", ""]) isNotEqualTo ""}
