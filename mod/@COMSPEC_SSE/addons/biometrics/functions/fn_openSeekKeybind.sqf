/*
    Raccourci Ctrl+Shift+S — ouvrir l'outil SSE (biométrie, fiche du sujet).

    Le SEEK II est l'outil de vérification terrain : s'il est porté, c'est lui qui s'ouvre.
    Priorité :
      1. SEEK II porté (ou accès sans objet, comspec_sse_require_item = false) : SEEK sur la personne
         visée (6 m au plus), directement sur le CONTRÔLE RAPIDE ; personne visée : accueil ;
      2. téléphone ATAK porté (sans SEEK) : app SSE du téléphone (consultation des fichiers) ;
      3. BII-10 Identifi en inventaire, si la fenêtre s'ouvre vraiment.
    La fiche de renseignement (RENS) a son propre raccourci (Ctrl+Shift+R, comspec_sse_fnc_openIntelNoteKeybind).
    Échec : RPT seulement (pas d'alerte de l'interface).
*/
if (!hasInterface) exitWith { false };

private _target = cursorObject;
if (isNull _target) then { _target = cursorTarget; };
if (isNull _target || {!(_target isKindOf "CAManBase")} || {(player distance _target) > 6} || {_target isEqualTo player}) then { _target = objNull; };

// 1. SEEK II.
private _all = (items player) + (assignedItems player);
private _hasSeek = ("COMSPEC_Item_SeekTerminal" in _all) || {"COMSPEC_SSE_SEEKII" in _all}
    || {!(missionNamespace getVariable ["comspec_sse_require_item", true])};
if (_hasSeek && {!isNil "comspec_sse_fnc_openSeek"} && {[_target] call comspec_sse_fnc_openSeek}) exitWith { true };

// 2. App SSE du téléphone ATAK natif : « SSE terrain » du pack (compat_atak, page SSEFIELD), sinon l'app SSE de l'ATAK.
if (!isNil "comspec_atak_native_fnc_hasDevice" && {[player] call comspec_atak_native_fnc_hasDevice}) exitWith {
    if (isNull ([] call comspec_atak_native_fnc_display) || {!((uiNamespace getVariable ["COMSPEC_ATAK_State", createHashMap]) getOrDefault ["interactive", false])}) then {
        [true] call comspec_atak_native_fnc_open;
    };
    private _page = ["SSE", "SSEFIELD"] select (isClass (configFile >> "COMSPEC_ATAK_Apps" >> "SseField"));
    [{ [_this] call comspec_atak_native_fnc_navigate; }, _page] call CBA_fnc_execNextFrame;
    true
};

// 3. BII-10 Identifi.
private _opened = false;
if (
    missionNamespace getVariable ["comspec_sse_biiBridgeEnabled", true]
    && {!isNil "comspec_sse_fnc_biiIsPresent"}
    && {[] call comspec_sse_fnc_biiIsPresent}
    && {!isNil "comspec_sse_fnc_biiOpen"}
    && {!isNil "BII_fnc_identifi_hasDevice"}
    && {[player] call BII_fnc_identifi_hasDevice}
) then {
    private _ok = ["scan"] call comspec_sse_fnc_biiOpen;
    private _disp = uiNamespace getVariable ["BII_Identifi_Dialog", displayNull];
    if (isNull _disp) then { _disp = findDisplay 861010; };
    _opened = _ok && {!isNull _disp};
};
if (_opened) exitWith { true };

// Dernier recours : terminal Overwatch (il vérifie lui-même la possession de l'appareil).
if (!isNil "comspec_overwatch_connect_fnc_sseOpenTerminal") exitWith {
    [_target, -1] call comspec_overwatch_connect_fnc_sseOpenTerminal
};

diag_log text "[COMSPEC SSE] Ctrl+Shift+S : aucun SEEK II, téléphone ATAK ou BII-10 en inventaire.";
false
