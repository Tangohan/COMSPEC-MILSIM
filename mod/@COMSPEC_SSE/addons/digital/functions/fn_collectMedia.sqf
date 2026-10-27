/*
    Collecte le support numérique (clé USB ou carte SD) d'un appareil dans l'inventaire du joueur.
    [_target, _player] call comspec_sse_fnc_collectMedia

    Une seule fois par appareil (variable publique comspec_sse_mediaTaken) : chaque appel ajoutait auparavant le
    même support, à l'infini. Place vérifiée (canAdd) avant et après la barre : l'objet n'est plus perdu en silence.
    Le contenu du support est inscrit sur la fiche de l'appareil (section collectedMedia, par le serveur) au lieu
    d'une variable du joueur que rien ne relisait.
*/
params [
    ["_target", objNull, [objNull]],
    ["_player", player, [objNull]]
];

if (isNull _target) exitWith { false };
if !([_player, "evidence_bag"] call comspec_sse_fnc_hasEquipment) exitWith {
    ["Sachet de preuve SSE (ou kit médical ACE compatible) requis pour collecter un support.", "WARNING"] call comspec_sse_fnc_notify;
    false
};
if ((_target getVariable ["comspec_sse_mediaTaken", ""]) isNotEqualTo "") exitWith {
    [format ["Support déjà retiré de cet appareil (%1).", _target getVariable ["comspec_sse_mediaTaken", ""]], "WARNING"] call comspec_sse_fnc_notify;
    false
};

// Nature du support : déterministe (graine de l'appareil), connue avant la barre pour vérifier la place.
private _seed = [_target] call comspec_sse_fnc_getSeed;
private _kind = ["USB", "SDCARD"] select (([_seed, "medkind"] call comspec_sse_fnc_hash) mod 2);
private _item = ["COMSPEC_SSE_SDCard", "COMSPEC_SSE_USB"] select (_kind isEqualTo "USB");
if !(_player canAdd _item) exitWith {
    ["Inventaire plein : faites de la place pour le support.", "WARNING"] call comspec_sse_fnc_notify;
    false
};

[
    10,
    "Collecte support numérique...",
    {
        params ["_target", "_player", "_kind", "_item", "_seed"];
        // Deux joueurs sur le même appareil : le second arrive après le premier.
        if ((_target getVariable ["comspec_sse_mediaTaken", ""]) isNotEqualTo "") exitWith {
            ["Support déjà retiré de cet appareil.", "WARNING"] call comspec_sse_fnc_notify;
        };
        if !(_player canAdd _item) exitWith {
            ["Inventaire plein : support laissé dans l'appareil.", "WARNING"] call comspec_sse_fnc_notify;
        };
        [_target] call comspec_sse_fnc_ensureGenerated;

        private _data = [_target] call comspec_sse_fnc_getData;
        private _clusterId = if (isNil "_data") then {""} else {[_data, "clusterId", ""] call BIS_fnc_getFromPairs};

        // Récupérer cluster approximatif
        private _cluster = createHashMapFromArray [
            ["clusterId", _clusterId],
            ["primaryName", ""],
            ["theme", _target getVariable ["comspec_sse_theme", "fuel_delivery"]],
            ["region", _target getVariable ["comspec_sse_region", "IRAQ"]]
        ];
        private _id = [_target, "identity"] call comspec_sse_fnc_getSection;
        if (!isNil "_id" && {_id isEqualType createHashMap}) then {
            _cluster set ["primaryName", _id getOrDefault ["name", ""]];
        };

        private _media = [_seed + 50, _kind, _cluster] call comspec_sse_fnc_generateUSB;
        _target setVariable ["comspec_sse_mediaTaken", _media get "uid", true];
        _player addItem _item;

        private _quality = [70, true, 1, 1, 1, _player, _target, "digital"] call comspec_sse_fnc_calcQuality;
        _media set ["quality", _quality];
        _media set ["collector", name _player];
        // Contenu rattaché à la fiche de l'appareil (ajout concurrent sûr, sans doublon d'UID).
        // Ajouté avec la chaîne de possession ci-dessous en un seul commit (une diffusion de la fiche).

        private _coc = createHashMapFromArray [
            ["evidenceId", _media get "uid"],
            ["type", _kind],
            ["collector", name _player],
            ["time", time],
            ["missionTime", [daytime, "HH:MM:SS"] call BIS_fnc_timeToString],
            ["position", getPosATL _target],
            ["grid", mapGridPosition (getPosATL _target)],
            ["action", "COLLECT_MEDIA"],
            ["quality", _quality],
            ["container", [_player, "evidence_bag"] call comspec_sse_fnc_resolveEquipment]
        ];
        // Chaîne de possession seulement : l'appareil lui-même n'est pas collecté (il reste exploitable et saisissable).
        ["batch", _target, [
            ["append", ["collectedMedia", [_media], "uid"]],
            ["append", ["chainOfCustody", [_coc], "evidenceId"]]
        ]] call comspec_sse_fnc_recordOp;

        [format ["Support %1 collecté\n%2\nFichiers : %3", _kind, _media get "uid", count (_media getOrDefault ["files", []])], "SUCCESS"] call comspec_sse_fnc_notify;
        [_media get "uid", "collect_media", _kind, _media getOrDefault ["summary", ""], _quality, "QUEUED"] call comspec_sse_fnc_addJournalEntry;
    },
    [_target, _player, _kind, _item, _seed]
] call comspec_sse_fnc_progressAction;
true
