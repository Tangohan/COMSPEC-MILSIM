/*
    « Relever » le document du contrôle en cours (client) : ses champs imprimés et sa bande MRZ vont dans la fiche FRM en
    préparation (app FRS), dans le texte de la fiche.
    La commande screenshot d'Arma n'enregistre que la scène 3D, jamais l'interface : la « photo du document » d'avant montrait
    le décor derrière la visionneuse, sans le document (autotest du 8/11). Une image du document n'est possible qu'en la
    dessinant hors du jeu (DLL ou site, à partir de ces mêmes champs) : le relevé reste la source.
    Les défauts physiques (hologramme, police, photo rapportée) ne sont pas relevés : c'est au contrôleur de les voir.
    Photo de preuve SSE de la personne contrôlée (fn_evidencePhoto) : inchangée, elle photographie la scène, ce qu'elle doit faire.
    ["shoot"]  visionneuse ouverte : relevé du document, noté sur le contrôle (FRM pré-remplie, fn_idFrm)
    ["app"]    depuis l'app Contrôle ID : ouvre la visionneuse sur le document en cours, relevé fait tout seul, puis
               retour à l'app.
*/
params [["_op", "shoot", [""]]];
if (!hasInterface) exitWith { false };
private _rec = ["current"] call comspec_sse_fnc_idHistory;
if ((count _rec) isEqualTo 0) exitWith { ["Aucun contrôle en cours : rien à relever.", "WARNING", "", "IDCHECK"] call comspec_sse_fnc_notify; false };
if (_op isEqualTo "app") exitWith { [_rec get "doc", false, true] call comspec_sse_fnc_idViewerOpen };
if (isNull (uiNamespace getVariable ["comspec_sse_idViewerDisplay", displayNull])) exitWith { [_rec get "doc", false, true] call comspec_sse_fnc_idViewerOpen };
private _d = _rec get "doc";
private _clock = [dayTime, "HH:MM"] call BIS_fnc_timeToString;
private _type = ["Carte d'identité", "Passeport", "Permis de conduire", "Carte militaire"] select ((["id_card", "passport", "driving_license", "military_id"] find (_d getOrDefault ["type", ""])) max 0);
private _mrz = _d getOrDefault ["mrz", []];
if !(_mrz isEqualType []) then { _mrz = [_mrz]; };
private _lines = [
    format [("Relevé du document à %1 : %2 %3 n° %4." call comspec_sse_fnc_tr), _clock, _type call comspec_sse_fnc_tr, _d getOrDefault ["country", ""], _d getOrDefault ["number", ""]],
    format [("Titulaire : %1 %2, %3, né(e) le %4 à %5 ; délivré le %6, expire le %7." call comspec_sse_fnc_tr), toUpper (_d getOrDefault ["last", ""]), _d getOrDefault ["first", ""],
        _d getOrDefault ["sex", ""], _d getOrDefault ["dob", ""], _d getOrDefault ["pob", ""], _d getOrDefault ["issued", ""], _d getOrDefault ["expires", ""]]
];
if ((_d getOrDefault ["rank", ""]) isNotEqualTo "") then { _lines pushBack format [("Grade et force : %1, %2." call comspec_sse_fnc_tr), _d get "rank", _d getOrDefault ["force", ""]]; };
if (_mrz isNotEqualTo []) then { _lines pushBack format ["MRZ : %1", _mrz joinString " / "]; };
_rec set ["releve", _lines];
_rec set ["photo", "releve"];
_rec set ["photoClock", _clock];
// Dossier SSE de la personne contrôlée : photo de preuve (section photos, journal de la pièce).
private _subj = _rec getOrDefault ["subj", objNull];
if (_subj isEqualType objNull && {!isNull _subj} && {_subj getVariable ["comspec_sse_enabled", false]} && {!isNil "comspec_sse_fnc_evidencePhoto"}) then {
    [[[_subj, player distance _subj]], 1] call comspec_sse_fnc_evidencePhoto;
};
["Document relevé : champs et bande MRZ ajoutés à la fiche FRM.", "SUCCESS", "", "IDCHECK"] call comspec_sse_fnc_notify;
// Relevé demandé depuis l'app : retour à l'app Contrôle ID.
if ((uiNamespace getVariable ["comspec_sse_idView", createHashMap]) getOrDefault ["auto", false]) then {
    [] spawn {
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
