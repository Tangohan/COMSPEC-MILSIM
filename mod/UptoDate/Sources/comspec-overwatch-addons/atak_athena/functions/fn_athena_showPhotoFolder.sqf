/*
    Indique le dossier unique où Overwatch enregistre les photos,
    et le copie dans le presse-papiers.
*/
if (!hasInterface) exitWith {};

private _primary = "";
private _raw = ["COMSPECExtension" callExtension ["GetPhotoSaveDir", []]] call comspec_overwatch_connect_fnc_extResult;
if (_raw isEqualType "" && {(_raw select [0, 3]) isEqualTo "OK|"}) then {
    _primary = trim (_raw select [3, (count _raw) - 3]);
};

if (_primary isEqualTo "") then {
    private _dirsRaw = ["COMSPECExtension" callExtension ["GetScreenshotDirs", []]] call comspec_overwatch_connect_fnc_extResult;
    if (_dirsRaw isEqualType "" && {(_dirsRaw select [0, 3]) isEqualTo "OK|"}) then {
        private _body = (_dirsRaw select [3, (count _dirsRaw) - 3]);
        _body = (_body splitString (toString [13])) joinString "";
        private _lines = [];
        {
            private _d = trim _x;
            if (_d isNotEqualTo "") then { _lines pushBack _d; };
        } forEach (_body splitString toString [10]);
        if ((count _lines) > 0) then { _primary = _lines select 0; };
    };
};

if (_primary isNotEqualTo "") then {
    copyToClipboard _primary;
};

private _label = _primary;
private _i = _primary find "\Documents\";
if (_i >= 0) then {
    _label = _primary select [_i + 1, count _primary];
};

private _html = if (_primary isEqualTo "") then {
    "<t color='#ffd27a'>Dossier introuvable</t><br/><br/><t color='#c8d0d6'>Les photos COMSPEC vont dans Documents\Arma 3 - COMSPEC\Captures. Le dossier sera créé dès la première capture réussie.</t>"
} else {
    format [
        "<t color='#7eb8ff' size='1.05'>Où sont vos photos</t><br/><br/><t color='#7dffb0'>Toutes les photos envoyées au poste sont copiées ici :</t><br/><br/><t color='#e8f4f0'>%1</t><br/><br/><t color='#8aa0b4'>Ce chemin a été copié. Collez-le dans l’explorateur de fichiers pour ouvrir le dossier. Le jeu peut aussi écrire ailleurs : Overwatch recopie toujours ici.</t>",
        _label
    ]
};

private _group = [] call comspec_overwatch_atak_athena_fnc_athena_resolveAthenaGroup;
if (!isNull _group) then {
    private _detail = _group controlsGroupCtrl 9711;
    if (!isNull _detail) then {
        _detail ctrlSetStructuredText parseText _html;
    };
};

if (_primary isNotEqualTo "") then {
    [
        "Dossier des photos copié — Documents\Arma 3 - COMSPEC\Captures",
        "ok",
        8
    ] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
} else {
    [
        "Dossier des photos introuvable pour le moment. Il sera créé à la prochaine capture.",
        "warn",
        6
    ] call comspec_overwatch_atak_athena_fnc_athena_setPanelFeedback;
};
