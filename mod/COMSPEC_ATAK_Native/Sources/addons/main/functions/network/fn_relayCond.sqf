/*
    Condition d'activation d'un relais ou d'une passerelle de véhicule (variable COMSPEC_AtakRelayCond de l'objet) : le relais
    ne porte le réseau que tant qu'elle est remplie ; sinon fn_relayNet le liste hors service avec la raison (page Réseau,
    fiche du relais sur la carte). Relue avec la liste des relais (toutes les 2 s).
      ""          toujours actif (par défaut) ;
      "stopped"   véhicule à l'arrêt (moins de 2 km/h) ;
      "engineoff" moteur coupé ;
      "mast"      mât ou antenne déployé(e) : sources d'animation du véhicule dont le nom contient mast, antenna, antena,
                  telescop, aerial, pole ou rod (la plus déployée doit dépasser 90 %). Exemple : GAZ-66 radio de RHS
                  (mât télescopique), véhicules de commandement. Aucune source de ce genre sur le véhicule : jamais actif,
                  « aucun mât reconnu » (écrire alors la condition en code) ;
      autre texte : code SQF, _this = l'objet, qui rend vrai quand le relais doit marcher.
                  Exemple : _this animationSourcePhase "mast_src" > 0.9 && {(abs speed _this) < 1}
    Raison affichée : COMSPEC_AtakRelayCondText de l'objet, sinon celle du préréglage (« mât replié », « en mouvement »...).
    Depuis une mission ou un script (variable publique, réglée sur le serveur ou par le module « ATAK · Relais ») :
      _veh setVariable ["COMSPEC_AtakRelayCond", "mast", true];
    Params : [objet] -> [actif, raison ("" quand actif)]
*/
params [["_o", objNull]];
if (isNull _o) exitWith { [false, ""] };
private _c = _o getVariable ["COMSPEC_AtakRelayCond", ""];
if (!(_c isEqualType "") || {(trim _c) isEqualTo ""}) exitWith { [true, ""] };
private _why = _o getVariable ["COMSPEC_AtakRelayCondText", ""];
if !(_why isEqualType "") then { _why = ""; };
private _def = "";
private _ok = switch (toLower trim _c) do {
    case "stopped": { _def = "en mouvement"; (abs speed _o) < 2 };
    case "engineoff": { _def = "moteur allumé"; !(isEngineOn _o) };
    case "mast": {
        // Sources du mât par classe, cherchées une fois (config du véhicule).
        private _cache = missionNamespace getVariable "COMSPEC_ATAK_MastSrc";
        if (isNil "_cache") then { _cache = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_MastSrc", _cache]; };
        private _src = _cache getOrDefault [typeOf _o, -1];
        if (_src isEqualTo -1) then {
            _src = ((configProperties [configOf _o >> "AnimationSources", "isClass _x", true]) apply { configName _x })
                select { (toLower _x) regexMatch ".*(mast|antenna|antena|telescop|aerial|pole|rod).*" };
            _cache set [typeOf _o, _src];
        };
        if (_src isEqualTo []) exitWith { _def = "aucun mât reconnu"; false };
        _def = "mât replié";
        (selectMax (_src apply { _o animationSourcePhase _x })) >= 0.9
    };
    default {
        // Code de la mission, compilé une fois ; une erreur ou un résultat non booléen vaut « inactif ».
        private _codes = missionNamespace getVariable "COMSPEC_ATAK_RelayCondCode";
        if (isNil "_codes") then { _codes = createHashMap; missionNamespace setVariable ["COMSPEC_ATAK_RelayCondCode", _codes]; };
        private _code = _codes getOrDefault [_c, {}];
        if (_code isEqualTo {}) then { _code = compile _c; _codes set [_c, _code]; };
        _def = "condition non remplie";
        private _r = _o call _code;
        !isNil "_r" && {_r isEqualType true} && {_r}
    };
};
[_ok, ["", [_def, _why] select (_why isNotEqualTo "")] select !_ok]
