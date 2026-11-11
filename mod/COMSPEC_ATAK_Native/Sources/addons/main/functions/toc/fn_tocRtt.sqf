/*
    Caméras de rendu des écrans TOC (caméra en direct, mosaïque, satellite) : seul endroit du code TOC qui touche à un
    effet de caméra. Retour de TTRD (4.7.1) : « quand je pose le module TOC en Zeus, ça me remet en première personne
    sur mon mec mais encore en Zeus, et ça le refait tout le temps ».
    Cause : chaque rendu vers une texture (cameraEffect "Internal" avec un nom de cible r2t, puis "Terminate" à sa
    libération) était lancé pendant que la caméra du Zeus tenait la vue. Le moteur rend alors la vue principale à
    l'unité du joueur, interface Zeus toujours ouverte ; et comme les écrans d'une salle posée créent et libèrent leurs
    caméras à chaque regard (bail de 3 s, pages de la mosaïque, palier de qualité), cela recommençait sans fin.
    Règle : une caméra TOC ne rend jamais que vers SA cible (nom r2t unique, obligatoire) ; elle n'est terminée que sur
    cette cible ; après chaque ouverture ou fermeture, la vue principale est rendue à la caméra du Zeus si son
    interface est ouverte. Rien ici ne touche à la vue du joueur (ni switchCamera, ni effet sans cible).
    Modes :
      ["open", [caméra, cible r2t]]    rendu de la caméra vers la cible -> true
      ["close", [caméra, cible r2t]]   fin du rendu vers la cible, caméra détruite -> true
      ["swap", [caméra, ancienne cible, nouvelle cible]]   même caméra, autre cible (résolution changée)
      ["keep"]                         vue principale rendue au Zeus si son interface est ouverte
*/
params [["_mode", "keep"], ["_a", []]];
private _keep = {
    // Interface Zeus ouverte (display 312) : sa caméra reprend la vue principale. Zeus fermé ou unité pilotée à
    // distance : rien (la vue est déjà celle du joueur, on n'y touche pas).
    if (hasInterface && {!isNull curatorCamera} && {!isNull (findDisplay 312)}) then {
        curatorCamera cameraEffect ["Internal", "Back"];
    };
};
switch (_mode) do {
    case "open": {
        _a params [["_cam", objNull], ["_rt", ""]];
        if (isNull _cam || {!(_rt isEqualType "")} || {_rt isEqualTo ""}) exitWith { false };
        _cam cameraEffect ["Internal", "Back", _rt];
        call _keep;
        true
    };
    case "close": {
        _a params [["_cam", objNull], ["_rt", ""]];
        if (isNull _cam) exitWith { false };
        if (_rt isEqualType "" && {_rt isNotEqualTo ""}) then { _cam cameraEffect ["Terminate", "Back", _rt]; };
        camDestroy _cam;
        call _keep;
        true
    };
    case "swap": {
        _a params [["_cam", objNull], ["_old", ""], ["_new", ""]];
        if (isNull _cam || {_new isEqualTo ""}) exitWith { false };
        if (_old isNotEqualTo "") then { _cam cameraEffect ["Terminate", "Back", _old]; };
        _cam cameraEffect ["Internal", "Back", _new];
        call _keep;
        true
    };
    default { call _keep; true };
};
