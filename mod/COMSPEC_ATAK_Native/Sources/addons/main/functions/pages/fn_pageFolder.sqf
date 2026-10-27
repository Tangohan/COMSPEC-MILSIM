/*
    Contenu d'un groupe d'apps (page FOLDER).
    uiNamespace COMSPEC_ATAK_FolderId = id du groupe ouvert.
*/
disableSerialization;
private _id = uiNamespace getVariable ["COMSPEC_ATAK_FolderId", ""];
private _folder = ["get", _id] call comspec_atak_native_fnc_appFolders;
if ((count _folder) isEqualTo 0) exitWith {
    ["LAUNCHER"] call comspec_atak_native_fnc_navigate;
    false
};
_folder params ["_fid", "_fname", "_pages"];
private _l = [] call comspec_atak_native_fnc_layoutGet;
(_l get "body") params ["", "", "_bw", "_bh"];
private _byPage = createHashMap;
{ _byPage set [_x get "page", _x]; } forEach (([] call comspec_atak_native_fnc_appList) select { [_x] call comspec_atak_native_fnc_appVisible });
private _apps = (_pages apply { _byPage getOrDefault [_x, createHashMap] }) select { (count _x) > 0 };

private _rows = [
    ["hero", "\z\comspec_atak_native\addons\main\data\app_folder.paa", format [
        "<t size='1.35' font='COMSPEC_BarlowBold'>%1</t><br/><t color='#8a9a93'>%2 application%3</t>",
        _fname,
        count _apps,
        ["", "s"] select ((count _apps) isNotEqualTo 1)
    ]],
    ["section", "Applications", "Touchez une app pour l'ouvrir. Retirez-la du groupe ci-dessous."],
    ["field", "folderName", "Nom du groupe", _fname, "Renommer", {
        private _n = ["folderName"] call comspec_atak_native_fnc_formValue;
        private _id = uiNamespace getVariable ["COMSPEC_ATAK_FolderId", ""];
        if (["rename", _id, _n] call comspec_atak_native_fnc_appFolders) then {
            ["Groupe renommé", 2] call comspec_atak_native_fnc_toast;
            [{ ["FOLDER"] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;
        } else {
            ["Nom invalide", 2] call comspec_atak_native_fnc_toast;
        };
    }, true]
];

if ((count _apps) isEqualTo 0) then {
    _rows pushBack ["text", "<t color='#8a9a93'>Ce groupe est vide. Sur l'accueil, clic droit sur une app puis « Ajouter au groupe ».</t>"];
} else {
    {
        private _app = _x;
        private _page = _app get "page";
        private _nm = (_app get "name") call comspec_atak_native_fnc_tr;
        private _desc = (_app getOrDefault ["desc", ""]) call comspec_atak_native_fnc_tr;
        _rows pushBack ["person", _app get "icon", format ["<t font='COMSPEC_BarlowBold'>%1</t><br/><t size='0.8' color='#8a9a93'>%2</t>", _nm, _desc], [
            ["Ouvrir", compile format ["['%1'] call comspec_atak_native_fnc_navigate;", _page], true],
            ["Retirer", compile format [
                "private _id = uiNamespace getVariable ['COMSPEC_ATAK_FolderId', '']; ['remove', _id, '%1'] call comspec_atak_native_fnc_appFolders; ['Retirée du groupe', 2] call comspec_atak_native_fnc_toast; [{ ['FOLDER'] call comspec_atak_native_fnc_pageRender; }] call CBA_fnc_execNextFrame;",
                _page
            ], false]
        ]];
    } forEach _apps;
};

_rows pushBack ["section", "Gestion", ""];
_rows pushBack ["buttons", [
    ["Accueil", { ["LAUNCHER"] call comspec_atak_native_fnc_navigate; }, false],
    ["Supprimer le groupe", {
        private _id = uiNamespace getVariable ["COMSPEC_ATAK_FolderId", ""];
        ["delete", _id] call comspec_atak_native_fnc_appFolders;
        uiNamespace setVariable ["COMSPEC_ATAK_FolderId", ""];
        ["Groupe supprimé", 2] call comspec_atak_native_fnc_toast;
        ["LAUNCHER"] call comspec_atak_native_fnc_navigate;
    }, false, true, "Les apps restent installées, seul le groupe disparaît"]
]];

[_rows, [0, 0, _bw, _bh]] call comspec_atak_native_fnc_formRender;
true
