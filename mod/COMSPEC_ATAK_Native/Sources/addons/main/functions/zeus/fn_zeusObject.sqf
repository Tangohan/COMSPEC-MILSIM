/*
    Fenêtre « Configurer l'objet » du Zeus : une seule fenêtre (fn_zeusForm, sans ZEN) pour les écrans, les preuves et les caisses.
    Elle ne contient aucune logique d'objet : chaque choix rouvre le formulaire Zeus existant du module correspondant,
    appelé sur l'objet avec une logique locale temporaire (le module la supprime lui-même).
    Entrées : double-clic sur l'objet (fn_zeusCentre « hook »), clic droit ZEN, bouton CONFIGURER du Centre Zeus.
    Droits : l'ouverture passe par le serveur (fn_adminAllowed). Les réglages suivent ensuite le chemin de chaque module.
    Actions :
      "kind" [objet, large]  -> "screen" | "projector" | "digital" | "paper" | "box" | ""
                        large (clic droit, bouton CONFIGURER) : aussi les écrans du jeu ou d'autres mods déclarés dans
                        CfgComspecTocScreens ; sans lui (double-clic), seulement les écrans COMSPEC et les objets déjà
                        désignés, pour ne pas prendre la place de la fenêtre d'attributs d'une TV du jeu
    Écran : la fenêtre « Configurer l'écran » (fn_tocConfig « zeus ») s'ouvre directement. Serveur ancien ou sans le
    mod : il ne répondrait pas à la demande, le Zeus ouvre la fenêtre lui-même.
      "request" [objet] demande au serveur          "open" [objet] (réponse du serveur)
      "double" [objet]  double-clic : referme la fenêtre d'attributs ouverte en même temps, puis "request"
      "run" [id, objet] applique un choix           "initServer" (postInit serveur)
*/
params [["_act", ""], ["_arg", []]];
disableSerialization;
switch (_act) do {
    case "kind": {
        _arg params [["_o", objNull], ["_wide", false]];
        if (isNull _o) exitWith { "" };
        private _cls = typeOf _o;
        private _cfg = configOf _o;
        if ((getNumber (_cfg >> "comspec_projector")) > 0) exitWith { "projector" };
        if ((getNumber (_cfg >> "comspec_screen")) > 0 || {(_cls find "COMSPEC_ATAK_Screen_") isEqualTo 0} || {(_cls find "COMSPEC_ATAK_ProjScreen") isEqualTo 0}
            || {(count (_o getVariable ["COMSPEC_ATAK_TocScr", []])) >= 8} || {_wide && {["isScreen", _o] call comspec_atak_native_fnc_tocConfig}}) exitWith { "screen" };
        if (_cls in ["COMSPEC_SSE_Obj_Smartphone", "COMSPEC_SSE_Obj_SatPhone", "COMSPEC_SSE_Obj_Radio", "COMSPEC_SSE_Obj_Laptop", "COMSPEC_SSE_Obj_Tablet", "COMSPEC_SSE_Obj_PCTower", "COMSPEC_SSE_Obj_HardDrive", "COMSPEC_SSE_Obj_USB", "COMSPEC_SSE_Obj_SDCard"]) exitWith { "digital" };
        if ((_cls find "COMSPEC_SSE_Obj_") isEqualTo 0) exitWith { "paper" };
        if ((_cls find "COMSPEC_SSE_Box_") isEqualTo 0 || {_cls isEqualTo "COMSPEC_SSE_DomexCase"}) exitWith { "box" };
        ""
    };
    case "initServer": {
        if (!isServer) exitWith {};
        ["comspec_atak_native_zeusObject", {
            params [["_caller", objNull], ["_o", objNull]];
            if (!(_caller isEqualType objNull) || {!(_o isEqualType objNull)}) exitWith {};
            if !([_caller] call comspec_atak_native_fnc_adminAllowed) exitWith {
                diag_log format ["[COMSPEC ATAK NATIVE][WARN][ZEUS] Configuration d'objet refusée (ni Zeus ni admin) : %1", name _caller];
            };
            ["comspec_atak_native_zeusObjectOpen", [_o], _caller] call CBA_fnc_targetEvent;
        }] call CBA_fnc_addEventHandler;
    };
    case "request": {
        _arg params [["_o", objNull]];
        if (isNull _o || {(["kind", [_o, true]] call comspec_atak_native_fnc_zeusObject) isEqualTo ""}) exitWith { ["feedback", ["ATAK : sélectionnez un écran, une preuve ou une caisse."]] call comspec_atak_native_fnc_zeusMenu; };
        // Serveur ancien ou sans le mod (fn_tocConfig « mode ») : pas de réponse à attendre, le Zeus ouvre lui-même.
        if ((((["mode"] call comspec_atak_native_fnc_tocConfig) select 0) isEqualTo "fallback") && {!isNull (getAssignedCuratorLogic player)}) exitWith { ["open", [_o]] call comspec_atak_native_fnc_zeusObject; };
        ["comspec_atak_native_zeusObject", [player, _o]] call CBA_fnc_serverEvent;
    };
    case "double": {
        _arg params [["_o", objNull]];
        if ((["kind", [_o]] call comspec_atak_native_fnc_zeusObject) isEqualTo "") exitWith {};
        if !(missionNamespace getVariable ["comspec_atak_native_zeus_centre_dbl", true]) exitWith {};
        // Zeus ouvre aussi sa fenêtre d'attributs (vanilla ou ZEN) : elle laisse la place.
        [{
            params ["_o"];
            private _top = allDisplays select -1;
            if !(ctrlIDD _top in [46, 312]) then { _top closeDisplay 2; };
            [{ ["request", _this] call comspec_atak_native_fnc_zeusObject; }, [_o], 0.1] call comspec_atak_native_fnc_waitAndExec;
        }, [_o], 0.2] call comspec_atak_native_fnc_waitAndExec;
    };
    case "open": {
        _arg params [["_o", objNull]];
        if (isNull _o || {isNull (findDisplay 312)}) exitWith {};
        private _kind = ["kind", [_o, true]] call comspec_atak_native_fnc_zeusObject;
        // Écran : fenêtre « Configurer l'écran », mêmes réglages que les attributs Eden (fn_tocConfig).
        if (_kind isEqualTo "screen") exitWith { ["zeus", [_o]] call comspec_atak_native_fnc_tocConfig; };
        private _has = { !isNil _this };
        private _state = [];
        private _opts = [];
        switch (_kind) do {
            case "screen";
            case "projector": {
                if (_kind isEqualTo "projector") then {
                    private _on = (_o getVariable ["COMSPEC_ATAK_Proj", []]) param [0, false];
                    _state pushBack format [("Projecteur : %1") call comspec_atak_native_fnc_tr, (["éteint", "allumé"] select (_on isEqualTo true)) call comspec_atak_native_fnc_tr];
                    _opts pushBack [["Allumer le projecteur", "Éteindre le projecteur"] select (_on isEqualTo true), ["projOn", "projOff"] select (_on isEqualTo true)];
                };
                private _cast = _o getVariable ["COMSPEC_ATAK_Screen", []];
                private _toc = _o getVariable ["COMSPEC_ATAK_TocScr", []];
                _state pushBack format [("Écran COMSPEC : %1") call comspec_atak_native_fnc_tr, ([("non") call comspec_atak_native_fnc_tr, _cast param [0, ""]] select (_cast isNotEqualTo []))];
                _state pushBack format [("Écran TOC : %1") call comspec_atak_native_fnc_tr, ([("non") call comspec_atak_native_fnc_tr, _toc param [0, ""]] select (_toc isNotEqualTo []))];
                _opts pushBack ["Écran COMSPEC : nom, portée, camp", "screen"];
                _opts pushBack ["Écran TOC : nom, camp, affichage", "toc"];
            };
            case "digital";
            case "paper": {
                private _ok = _o getVariable ["comspec_sse_enabled", false];
                _state pushBack format [("Exploitable SSE : %1") call comspec_atak_native_fnc_tr, (["non", "oui"] select (_ok isEqualTo true)) call comspec_atak_native_fnc_tr];
                if ("comspec_sse_fnc_moduleInitTarget" call _has) then { _opts pushBack ["Contenu : nature, profil, richesse", "sseInit"]; };
                if (_kind isEqualTo "digital") then {
                    if ("comspec_sse_fnc_moduleDomexMark" call _has) then { _opts pushBack ["Numérique : support, sécurité, réseau, accès", "sseDomex"]; };
                    if ("comspec_sse_fnc_moduleDomexAddIntel" call _has) then { _opts pushBack ["Ajouter un renseignement", "sseIntel"]; };
                    if ("comspec_sse_fnc_moduleDomexSetStage" call _has) then { _opts pushBack ["Fixer le palier d'accès", "sseStage"]; };
                    if ("comspec_sse_fnc_moduleDomexMapPoint" call _has) then { _opts pushBack ["Poser un point carte", "ssePoint"]; };
                    _state pushBack format [("Câble : %1") call comspec_atak_native_fnc_tr, (["non préparé", "préparé"] select !(isNil { _o getVariable "COMSPEC_ATAK_Data" })) call comspec_atak_native_fnc_tr];
                    _opts pushBack ["Câble : données à extraire, chiffrement", "dataNode"];
                    _opts pushBack ["Câble : ajouter un fichier", "dataFile"];
                };
                if ("comspec_sse_fnc_editor" call _has) then { _opts pushBack ["Dossier et bibliothèque : ouvrir l'éditeur", "sseEditor"]; };
                if ("comspec_sse_fnc_moduleApplyModel" call _has) then { _opts pushBack ["Appliquer un modèle", "sseModel"]; };
                if ("comspec_sse_fnc_moduleViewData" call _has) then { _opts pushBack ["Consulter les données", "sseView"]; };
                if ("comspec_sse_fnc_moduleClearData" call _has) then { _opts pushBack ["Effacer les données", "sseClear"]; };
            };
            case "box": {
                _state pushBack format [("Contenu : %1 objet(s)") call comspec_atak_native_fnc_tr, count (itemCargo _o + magazineCargo _o + weaponCargo _o + backpackCargo _o)];
                if ("zen_inventory_fnc_configure" call _has) then { _opts pushBack ["Modifier le contenu", "inv"]; };
                if ((typeOf _o) isEqualTo "COMSPEC_SSE_DomexCase" && {"comspec_sse_fnc_moduleInitTarget" call _has}) then { _opts pushBack ["Contenu SSE : nature, profil, richesse", "sseInit"]; };
            };
        };
        if (_opts isEqualTo []) exitWith {
            ["feedback", ["Modifier le contenu d'une caisse demande ZEN."]] call comspec_atak_native_fnc_zeusMenu;
        };
        [getText (configOf _o >> "displayName"), [["COMBO", "Régler", _opts, (_opts select 0) select 1]], {
            params ["_v", "_o"];
            [{ ["run", _this] call comspec_atak_native_fnc_zeusObject; }, [_v select 0, _o], 0.15] call comspec_atak_native_fnc_waitAndExec;
        }, _o, _state joinString " · "] call comspec_atak_native_fnc_zeusForm;
    };
    case "run": {
        _arg params [["_id", ""], ["_o", objNull]];
        if (isNull _o) exitWith {};
        private _mods = createHashMapFromArray [
            ["screen", "comspec_atak_native_fnc_moduleScreen"], ["toc", "comspec_atak_native_fnc_moduleTocScreen"],
            ["dataNode", "comspec_atak_native_fnc_moduleDataNode"], ["dataFile", "comspec_atak_native_fnc_moduleDataFile"],
            ["sseInit", "comspec_sse_fnc_moduleInitTarget"], ["sseDomex", "comspec_sse_fnc_moduleDomexMark"], ["sseIntel", "comspec_sse_fnc_moduleDomexAddIntel"],
            ["sseStage", "comspec_sse_fnc_moduleDomexSetStage"], ["ssePoint", "comspec_sse_fnc_moduleDomexMapPoint"], ["sseModel", "comspec_sse_fnc_moduleApplyModel"],
            ["sseView", "comspec_sse_fnc_moduleViewData"], ["sseClear", "comspec_sse_fnc_moduleClearData"]
        ];
        switch (_id) do {
            case "projOn": { ["power", [_o, true]] call comspec_atak_native_fnc_projector; };
            case "projOff": { ["power", [_o, false]] call comspec_atak_native_fnc_projector; };
            case "inv": { [_o] call zen_inventory_fnc_configure; };
            case "sseEditor": { ["open", [getPosATL _o, _o]] call comspec_sse_fnc_editor; };
            default {
                private _fn = missionNamespace getVariable [_mods getOrDefault [_id, ""], {}];
                private _logic = "Logic" createVehicleLocal (getPosATL _o);
                if (isNull _logic) exitWith { ["feedback", ["ATAK : fenêtre indisponible."]] call comspec_atak_native_fnc_zeusMenu; };
                _logic setVariable ["bis_fnc_curatorAttachObject_object", _o];
                [_logic, [_o], true] call _fn;
                // Module qui garde sa logique (forme non standard) : rien ne reste derrière.
                [{ if (!isNull _this) then { deleteVehicle _this; }; }, _logic, 30] call comspec_atak_native_fnc_waitAndExec;
            };
        };
    };
};
