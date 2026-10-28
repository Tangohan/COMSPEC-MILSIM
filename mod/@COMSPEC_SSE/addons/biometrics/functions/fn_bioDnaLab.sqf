/*
    Analyse ADN au laboratoire (serveur). Le résultat revient à l'opérateur après
    le délai réglé : correspondance directe, parenté au 1er degré avec une
    personne fichée, ou profil inconnu versé à la base.

    Params: [_unit, _key, _quality, _requester, _labRef]
*/
params [["_unit", objNull, [objNull]], ["_key", "", [""]], ["_quality", 80, [0]], ["_requester", objNull, [objNull]], ["_labRef", "", [""]]];
if (!isServer) exitWith {};

private _delay = missionNamespace getVariable ["comspec_sse_bio_dnaDelay", 900];
[{
    params ["_unit", "_key", "_quality", "_requester", "_labRef"];
    private _lines = [];
    private _title = format ["Laboratoire : résultat ADN %1", _labRef];
    if (_quality < 40) then {
        _lines pushBack "Échantillon dégradé : profil incomplet, aucune comparaison possible.";
    } else {
        private _rec = [_unit, _key] call comspec_sse_fnc_bioRecord;
        if ((count _rec) > 0 && {_rec getOrDefault ["dna", false]}) then {
            _lines pushBack format ["CORRESPONDANCE ADN : %1 (%2)", _rec get "name", _rec get "id"];
            if ((_rec getOrDefault ["tier", 0]) > 0) then { _lines pushBack format ["Watchlist tier %1 · %2", _rec get "tier", _rec getOrDefault ["reason", ""]]; };
        } else {
            // Recherche familiale : un parent ou un frère fiché partage la moitié des allèles.
            private _hit = [];
            private _profile = [_unit, _key] call comspec_sse_fnc_bioProfile;
            private _relKey = _profile getOrDefault ["relativeKey", ""];
            {
                private _k = [_x] call comspec_sse_fnc_bioPersonKey;
                private _p = [_x, _k] call comspec_sse_fnc_bioProfile;
                if (_k isEqualTo _relKey || {(_p getOrDefault ["relativeKey", ""]) isEqualTo _key}) then {
                    private _r = [_x, _k] call comspec_sse_fnc_bioRecord;
                    if ((count _r) > 0) exitWith {
                        private _rel = if (_k isEqualTo _relKey) then { _profile getOrDefault ["relation", "frere"] } else {
                            switch (_p getOrDefault ["relation", "frere"]) do { case "parent": { "enfant" }; case "enfant": { "parent" }; default { "frere" }; }
                        };
                        _hit = [_r get "name", _r get "id", _rel, _r getOrDefault ["tier", 0]];
                    };
                };
            } forEach ((allUnits + allDeadMen) select { _x isKindOf "CAManBase" && {_x != _unit} });
            if (_hit isNotEqualTo []) then {
                _hit params ["_n", "_id", "_rel", "_t"];
                _lines pushBack format ["PARENTÉ AU 1ER DEGRÉ PROBABLE avec %1 (%2)", _n, _id];
                _lines pushBack format ["Lien estimé : %1 du sujet. Le sujet lui-même n'est pas fiché.", switch (_rel) do { case "parent": { "parent" }; case "enfant": { "enfant" }; default { "frère ou sœur" }; }];
                if (_t > 0) then { _lines pushBack format ["Le parent est en watchlist (tier %1).", _t]; };
            } else {
                _lines pushBack "Aucune correspondance ADN. Profil inconnu versé à la base.";
            };
        };
        // L'ADN rejoint le dossier de rencontre.
        [createHashMapFromArray [["unit", _unit], ["key", _key], ["dna", _quality], ["by", "Laboratoire"]]] call comspec_sse_fnc_bioEnroll;
    };
    if (!isNull _requester) then {
        ["comspec_sse_bioNotify", [_title, _lines], _requester] call CBA_fnc_targetEvent;
    };
}, [_unit, _key, _quality, _requester, _labRef], _delay max 0] call CBA_fnc_waitAndExecute;
