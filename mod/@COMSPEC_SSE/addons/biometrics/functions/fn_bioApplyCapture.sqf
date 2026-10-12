/*
    Actions ACE du pack SSE : relevé par le moteur et écriture dans la section
    « biometrics » du sujet (lue par l'identification et la fiche).

    [_target, _player, _modality] call comspec_sse_fnc_bioApplyCapture -> Bool
      _modality "empreintes" | "iris" | "visage" | "adn"
*/
params [["_target", objNull, [objNull]], ["_player", player, [objNull]], ["_modality", "empreintes", [""]]];

private _device = if ([_player, "seek"] call comspec_sse_fnc_hasEquipment) then { "seek" } else { "kit" };
private _cap = [_target, _player, _modality, _device] call comspec_sse_fnc_bioCapture;
private _label = switch (_modality) do { case "iris": { "Iris" }; case "visage": { "Photo faciale" }; case "adn": { "ADN" }; default { "Empreintes" }; };
if !(_cap getOrDefault ["ok", false]) exitWith {
    hint format ["%1 : relevé impossible.\n%2", _label, _cap getOrDefault ["blocked", ""]];
    false
};

private _bio = [_target, "biometrics"] call comspec_sse_fnc_getSection;
if (isNil "_bio" || {!(_bio isEqualType createHashMap)}) then { _bio = createHashMap; };
private _q = _cap get "quality";
private _seed = [_target] call comspec_sse_fnc_getSeed;
switch (_modality) do {
    case "iris": {
        _bio set ["irisId", format ["IR-%1", [_seed, "ir", 8] call comspec_sse_fnc_idToken]];
        _bio set ["irisQuality", _q];
        _bio set ["irisEyes", _cap get "eyes"];
    };
    case "visage": { _bio set ["facePhoto", true]; _bio set ["faceQuality", _q]; };
    case "adn": {
        _bio set ["dnaId", format ["DNA-%1", [_seed, "dna", 8] call comspec_sse_fnc_idToken]];
        _bio set ["dnaQuality", _q];
        _bio set ["dnaRef", _cap get "labRef"];
    };
    default {
        _bio set ["fingerprintId", format ["FP-%1", [_seed, "fp", 8] call comspec_sse_fnc_idToken]];
        _bio set ["fingerprintQuality", _q];
        _bio set ["fingerprintNfiqs", _cap get "nfiqs"];
    };
};
_bio set [format ["%1Attempt", _modality], _cap get "attempt"];
[_target, "biometrics", _bio, true] call comspec_sse_fnc_setSection;

private _lines = [format ["%1 : qualité %2%% (tentative %3) · réf. %4", _label, _q, _cap get "attempt", _cap get "labRef"]];
if (_modality isEqualTo "empreintes") then {
    private _n = _cap get "nfiqs";
    _lines pushBack format ["NFIQ D : %1  ·  G : %2", ((_n select [0, 5]) apply { [str _x, "X"] select (_x < 0) }) joinString " ", ((_n select [5, 5]) apply { [str _x, "X"] select (_x < 0) }) joinString " "];
};
if (_modality isEqualTo "iris") then {
    _lines pushBack format ["OD %1 · OG %2", (_cap get "eyes") select 0, (_cap get "eyes") select 1];
};
{ _lines pushBack ("· " + _x); } forEach (_cap getOrDefault ["factors", []]);
hint (_lines joinString endl);
true
