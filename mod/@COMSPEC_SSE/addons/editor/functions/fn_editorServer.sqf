/*
    Pose d'un contenu de l'éditeur (SERVEUR). ["place", [moi, contenu, position, cible]] call comspec_sse_fnc_editorServer
    Renseignement posé : identifiant du contenu gardé sur l'objet (découverte : fn_editorFound). Identité et kit : le texte
    du contenu devient la légende du papier (fn_idLegend, « legendText »).
    Réservé au Zeus ou à l'administrateur (fn_isAuthority) ; le poste doit être celui de l'unité qu'il dit être.
      renseignement  à ramasser : pièce posée (COMSPEC_SSE_Obj_Documents) · en fouille : sur la personne ou le véhicule
                     visé · envoi : téléphones du groupe visé, sinon du camp du Zeus
      identité, papier, kit  remis à la personne visée (fn_idIssue, objet dans son inventaire) ; sinon au sol, ou dans
                     le véhicule visé ; envoi : annonce au téléphone seulement
      dossier        devient le dossier actif (fn_setCaseReference)
*/
params [["_op", "", [""]], ["_args", [], [[]]]];
if (!isServer || {!(_op in ["place", "replay"])}) exitWith { false };
// « replay » : élément gardé remis en place par le serveur au démarrage (fn_idAiConfig « restore »), jamais à distance.
private _replay = _op isEqualTo "replay";
if (_replay && {isRemoteExecuted}) exitWith { false };
if (_replay) then { _args = [objNull, _args param [0, createHashMap], _args param [1, []], objNull]; };
_args params [["_me", objNull, [objNull]], ["_item", createHashMap, [createHashMap]], ["_pos", [], [[]]], ["_target", objNull, [objNull]]];
if ((isNull _me && {!_replay}) || {(count _item) isEqualTo 0}) exitWith { false };
private _owner = if (isRemoteExecuted) then { remoteExecutedOwner } else { clientOwner };
if (isMultiplayer && {isRemoteExecuted} && {(owner _me) isNotEqualTo _owner}) exitWith { false };
if (!_replay && {!([_me] call comspec_sse_fnc_isAuthority)}) exitWith {
    diag_log text format ["[COMSPEC SSE] Éditeur : pose refusée au poste %1.", _owner];
    false
};

private _type = _item getOrDefault ["type", ""];
private _title = trim (_item getOrDefault ["title", ""]);
private _body = _item getOrDefault ["body", ""];
private _deliveryIndex = _item getOrDefault ["delivery", 0];
private _markings = ["NON PROTÉGÉ", "DIFFUSION RESTREINTE", "CONFIDENTIEL"];
private _marking = _markings param [_item getOrDefault ["marking", 0], _markings select 0];
if (_pos isEqualTo []) then { _pos = if (isNull _me) then { [0, 0, 0] } else { getPosATL _me }; };
_pos = [_pos select 0, _pos select 1, 0];
private _isMan = !isNull _target && {_target isKindOf "CAManBase"};
private _summary = "";

private _authored = {
    params ["_entity"];
    _entity setVariable ["comspec_sse_documentsMode", "CUSTOM", true];
    _entity setVariable ["comspec_sse_doc1_title", _title, true];
    _entity setVariable ["comspec_sse_doc1_summary", format ["[%1] %2", _marking, _body], true];
    _entity setVariable ["comspec_sse_editorItem", _item getOrDefault ["id", ""], true];
    _entity setVariable ["comspec_sse_editorFoundDone", false];
};
private _toPhones = {
    params ["_text"];
    private _to = if (!isNull _target) then { units group _target } else { allPlayers select { (side group _x) isEqualTo (side group _me) } };
    { ["receive", [_title, _text]] remoteExecCall ["comspec_sse_fnc_editor", _x]; } forEach (_to select { isPlayer _x });
};
// Fouille : personne ou véhicule visé ; ramassage : objet posé.
private _papers = {
    params ["_specs"];
    private _names = _title splitString " ";
    private _first = _names param [0, ""];
    private _last = (_names select [1, 9]) joinString " ";
    private _nature = ["civil", "cover", "forged"] param [_item getOrDefault ["nature", 0], "civil"];
    private _holder = objNull;
    if (!_isMan) then {
        _holder = if (!isNull _target) then { _target } else { createVehicle ["GroundWeaponHolder", _pos, [], 0, "CAN_COLLIDE"] };
    };
    {
        private _spec = createHashMapFromArray [["type", _x], ["kind", _nature], ["last", _last], ["first", _first], ["quality", 50]];
        private _id = if (_isMan) then {
            _spec set ["materialize", true];
            [_target, _spec] call comspec_sse_fnc_idIssue
        } else {
            _spec set ["holderKey", format ["ED%1", floor random 1e6]];
            [objNull, _spec] call comspec_sse_fnc_idIssue
        };
        if (!_isMan && {_id isNotEqualTo ""}) then { [_id, _holder] call comspec_sse_fnc_idMaterialize; };
        // La légende suit le papier : au sol ou dans un véhicule, elle attend celui qui le présentera.
        if (_id isNotEqualTo "" && {_type in ["identity", "kit"]} && {(trim _body) isNotEqualTo ""}) then {
            (["doc", [_id]] call comspec_sse_fnc_idRegistry) set ["legendText", _body];
        };
    } forEach _specs;
    if (_isMan) then { ["reset", [_target]] call comspec_sse_fnc_idLegend; };
};

switch (_type) do {
    case "case": {
        [toUpper _title] call comspec_sse_fnc_setCaseReference;
        _summary = format ["Dossier actif : %1.", toUpper _title];
    };
    case "intel": {
        switch (_deliveryIndex) do {
            case 2: { [_body] call _toPhones; _summary = "Envoyé aux téléphones."; };
            case 1: {
                if (isNull _target) exitWith { _summary = "Fouille : aucune personne ni véhicule sous le clic, rien de posé."; };
                [_target] call _authored;
                [_target, if (_isMan) then { "PERSON" } else { ["OBJECT", "VEHICLE"] select (_target isKindOf "AllVehicles") }] call comspec_sse_fnc_makeSearchable;
                if !(isNil { [_target] call comspec_sse_fnc_getData }) then { [_target] call comspec_sse_fnc_applyAuthoredContent; };
                _summary = format ["À trouver en fouille sur %1.", name _target];
            };
            default {
                private _o = createVehicle ["COMSPEC_SSE_Obj_Documents", _pos, [], 0, "CAN_COLLIDE"];
                [_o] call _authored;
                { _x addCuratorEditableObjects [[_o], false]; } forEach allCurators;
                _summary = format ["Pièce posée au %1.", mapGridPosition _pos];
            };
        };
    };
    default {
        if (_deliveryIndex isEqualTo 2) exitWith { [format ["Papiers au nom de %1.", _title]] call _toPhones; _summary = "Annonce envoyée aux téléphones."; };
        if (_type in ["identity", "paper", "kit"] && {_deliveryIndex isEqualTo 1} && {isNull _target}) exitWith { _summary = "Fouille : aucune personne ni véhicule sous le clic, rien de posé."; };
        private _paperTypes = ["id_card", "passport", "driving_license", "military_id"];
        private _chosen = switch (_type) do {
            case "paper": { [_paperTypes param [_item getOrDefault ["paper", 0], "id_card"]] };
            // Lot choisi dans le formulaire (avant : toujours carte et passeport, quel que soit le modèle).
            case "kit": { [["id_card", "passport"], ["military_id", "id_card"], ["id_card", "driving_license"]] param [_item getOrDefault ["paper", 0], ["id_card", "passport"]] };
            default { ["id_card"] };
        };
        [_chosen] call _papers;
        _summary = format ["%1 : %2 papier(s) posé(s).", _title, count _chosen];
    };
};
if (_replay) exitWith { true };
["placed", [_item getOrDefault ["id", ""], _summary, _pos]] remoteExecCall ["comspec_sse_fnc_editor", _owner];
true
