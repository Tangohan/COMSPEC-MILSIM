/*
    Tirage reproductible dans [0, 1[ pour une personne et un usage.

    Même graine de campagne + même clé + même sel = même valeur partout et à chaque
    appel : un sujet garde ses empreintes, une recapture donne un nouveau résultat
    (le numéro de tentative fait partie du sel) mais toujours le même.

    [_key, _salt] call comspec_sse_fnc_bioRand -> Number
*/
params [["_key", "", [""]], ["_salt", "", ["", 0]]];

private _campaign = missionNamespace getVariable ["comspec_sse_bio_campaignSeed", ""];
if (!(_campaign isEqualType "") || {(trim _campaign) isEqualTo ""}) then { _campaign = missionName; };

([format ["%1|%2|%3", _campaign, _key, _salt]] call comspec_sse_fnc_bioHash) / 16777216
