/*
    « Photographier » le document du contrôle en cours (client).
    ["shoot"]  visionneuse ouverte : boutons masqués, cliché du document (capture d'écran), joint à la fiche FRM en
               préparation (pièces jointes de l'app FRS : envoyées après la fiche, comme une capture de l'app) ;
               photo de preuve SSE sur la personne contrôlée si elle a un dossier SSE (fn_evidencePhoto : section
               photos, journal de la pièce). Le cliché est noté sur la fiche du contrôle (FRM pré-remplie).
    ["app"]    depuis l'app Contrôle ID : ouvre la visionneuse sur le document en cours, le cliché part tout seul, puis
               retour à l'app.
*/
params [["_op", "shoot", [""]]];
if (!hasInterface) exitWith { false };
private _rec = ["current"] call comspec_sse_fnc_idHistory;
if ((count _rec) isEqualTo 0) exitWith { ["Aucun contrôle en cours : rien à photographier.", "WARNING", "", "IDCHECK"] call comspec_sse_fnc_notify; false };
if (_op isEqualTo "app") exitWith { [_rec get "doc", false, true] call comspec_sse_fnc_idViewerOpen };
if (isNull (uiNamespace getVariable ["comspec_sse_idViewerDisplay", displayNull])) exitWith { [_rec get "doc", false, true] call comspec_sse_fnc_idViewerOpen };
if (missionNamespace getVariable ["comspec_sse_idShooting", false]) exitWith { false };
missionNamespace setVariable ["comspec_sse_idShooting", true];
[_rec] spawn {
    params ["_rec"];
    disableSerialization;
    private _disp = uiNamespace getVariable ["comspec_sse_idViewerDisplay", displayNull];
    private _hidden = [];
    { private _c = _disp displayCtrl _x; if (!isNull _c && {ctrlShown _c}) then { _c ctrlShow false; _hidden pushBack _c; }; } forEach [93705, 93710, 93711, 93712, 93713, 93714];
    uiSleep 0.15;
    private _name = format ["COMSPEC_ID_%1.png", (floor (diag_tickTime * 1000)) toFixed 0];
    private _ok = screenshot _name;
    uiSleep 0.3;
    { if (!isNull _x) then { _x ctrlShow true; }; } forEach _hidden;
    missionNamespace setVariable ["comspec_sse_idShooting", false];
    if (!_ok) exitWith { ["Cliché impossible (dossier des captures plein ?).", "WARNING", "", "IDCHECK"] call comspec_sse_fnc_notify; };
    private _d = _rec get "doc";
    private _who = format ["%1 %2", toUpper (_d getOrDefault ["last", ""]), _d getOrDefault ["first", ""]];
    private _clock = [dayTime, "HH:MM"] call BIS_fnc_timeToString;
    _rec set ["photo", _name];
    _rec set ["photoClock", _clock];
    // Pièce jointe de la fiche FRM (4 au plus), même chaîne que la capture de l'app FRS.
    private _pieces = uiNamespace getVariable ["COMSPEC_ATAK_FrsPieces", []];
    uiNamespace setVariable ["COMSPEC_ATAK_FrsPieces", _pieces];
    private _grid = if (isNil "comspec_atak_native_fnc_gridRef") then { mapGridPosition player } else { [player, 8] call comspec_atak_native_fnc_gridRef };
    private _joined = false;
    if ((count _pieces) < 4) then {
        _pieces pushBack ["capture", _name, format ["Document %1", _who], _grid, "", format ["Contrôle d'identité %1 : %2 %3 n° %4", _clock, _d getOrDefault ["type", ""], _d getOrDefault ["country", ""], _d getOrDefault ["number", ""]]];
        _joined = true;
    };
    // Dossier SSE de la personne contrôlée : photo de preuve (section photos, journal de la pièce).
    private _subj = _rec getOrDefault ["subj", objNull];
    if (_subj isEqualType objNull && {!isNull _subj} && {_subj getVariable ["comspec_sse_enabled", false]} && {!isNil "comspec_sse_fnc_evidencePhoto"}) then {
        [[[_subj, player distance _subj]], 1] call comspec_sse_fnc_evidencePhoto;
    };
    [[format ["Photo du document jointe à la fiche FRM (%1/4).", count _pieces], "Photo prise, mais la fiche FRM a déjà 4 pièces jointes : retirez-en une dans l'app FRS."] select !_joined,
        ["SUCCESS", "WARNING"] select !_joined, "", "IDCHECK"] call comspec_sse_fnc_notify;
    // Cliché demandé depuis l'app : retour à l'app Contrôle ID.
    if ((uiNamespace getVariable ["comspec_sse_idView", createHashMap]) getOrDefault ["auto", false]) then {
        uiSleep 0.4;
        closeDialog 2;
        uiSleep 0.1;
        if (!isNil "comspec_atak_native_fnc_open" && {[player] call comspec_atak_native_fnc_hasDevice}) then {
            [true] call comspec_atak_native_fnc_open;
            [{ ["IDCHECK"] call comspec_atak_native_fnc_navigate; }] call CBA_fnc_execNextFrame;
        };
    };
};
true
