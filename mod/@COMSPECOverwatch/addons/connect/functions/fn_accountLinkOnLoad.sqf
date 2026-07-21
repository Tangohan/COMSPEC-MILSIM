/*
    Préremplit le dialog de liaison compte (URL Athena depuis réglages / profil).
*/
if (!hasInterface) exitWith {};

private _display = uiNamespace getVariable ["COMSPEC_AccountLink_Display", displayNull];
if (isNull _display) exitWith {};

private _url = missionNamespace getVariable ["comspec_overwatch_api_url", ""];
if (_url isEqualTo "") then {
    _url = profileNamespace getVariable ["comspec_overwatch_saved_api_url", "https://athena.ttrd.fr/public"];
};
if (_url isEqualTo "") then { _url = "https://athena.ttrd.fr/public"; };

private _urlCtrl = _display displayCtrl 9201;
if (!isNull _urlCtrl) then { _urlCtrl ctrlSetText _url; };

private _status = _display displayCtrl 9203;
if (!isNull _status) then {
    _status ctrlSetStructuredText parseText "<t align='center' size='0.58' color='#6a7c90'>En attente du code…</t>";
};
