/*
    Ouvre le formulaire de signalement joueur. Params : [_catIndex] — catégorie choisie au départ (0 = Liaison Athena).
    Ne pas parenter au panneau HTML « gestion du mod » : createDisplay y échoue
    (fenêtre absente ou Envoyer inerte). On le ferme, puis on s’accroche au menu Échap.
*/
if (!hasInterface) exitWith {};
if (!(missionNamespace getVariable ["comspec_overwatch_enabled", true])) exitWith {
    ["Activez d’abord Overwatch pour signaler un problème.", "system", "warn"] call comspec_overwatch_connect_fnc_announce;
};

if (!isNull (uiNamespace getVariable ["COMSPEC_BugReport_Display", displayNull])) exitWith {};
if (missionNamespace getVariable ["COMSPEC_BugReportOpening", false]) exitWith {};
missionNamespace setVariable ["COMSPEC_BugReportOpening", true, false];

[_this param [0, 0]] spawn {
    private _pm = uiNamespace getVariable ["COMSPEC_PauseManager_Display", displayNull];
    if (!isNull _pm) then {
        _pm closeDisplay 2;
        uiSleep 0.12;
    };

    private _parent = findDisplay 49;
    if (isNull _parent) then { _parent = findDisplay 46; };
    if (isNull _parent) then { _parent = findDisplay 0; };

    private _ok = false;
    private _disp = displayNull;
    if (!isNull _parent) then {
        _disp = _parent createDisplay "COMSPEC_BugReport_Dialog";
        _ok = !isNull _disp;
    };

    if (!_ok || {isNull _disp}) then {
        _ok = createDialog "COMSPEC_BugReport_Dialog";
        _disp = uiNamespace getVariable ["COMSPEC_BugReport_Display", displayNull];
        if (isNull _disp) then { _disp = findDisplay 9992; };
    };

    missionNamespace setVariable ["COMSPEC_BugReportOpening", false, false];

    if (!_ok || {isNull _disp}) exitWith {
        ["Impossible d’ouvrir le formulaire de signalement. Réessayez depuis Échap → gestion du mod.", "system", "warn"] call comspec_overwatch_connect_fnc_announce;
    };

    uiNamespace setVariable ["COMSPEC_BugReport_Display", _disp];
    private _T = { _this call comspec_overwatch_connect_fnc_tr };
    (_disp displayCtrl 9802) ctrlSetText (("") call comspec_overwatch_connect_fnc_tr);
    uiNamespace setVariable ["COMSPEC_BugReportSent", false];
    missionNamespace setVariable ["COMSPEC_BugReportSending", false, false];

    (_disp displayCtrl 9806) ctrlSetStructuredText parseText ((format [
        "<t font='PuristaSemibold' size='1.25' color='#E6F0E8'>%1</t><br/><t font='PuristaMedium' size='0.75' color='#94A399'>%2</t>",
        "Signaler un problème" call _T,
        "Le rapport part à l'équipe COMSPEC qui suit le mod, pas sur Discord." call _T
    ]) call comspec_overwatch_connect_fnc_tr);

    // Qui envoie : indicatif et état de la liaison (le rapport part même sans compte lié).
    private _cs = [] call comspec_overwatch_connect_fnc_getCallsign;
    if (isNil "_cs" || {!(_cs isEqualType "")} || {_cs isEqualTo ""}) then { _cs = profileName; };
    private _linked = (missionNamespace getVariable ["COMSPEC_LinkState", "offline"]) isEqualTo "linked";
    (_disp displayCtrl 9807) ctrlSetStructuredText parseText ((format [
        "<t align='right' font='PuristaMedium' size='0.7' color='#94A399'>%1</t><br/><t align='right' font='PuristaSemibold' size='0.95' color='%2'>%3</t>",
        (["liaison hors ligne", "liaison OK"] select _linked) call _T,
        ["#F2AB33", "#5CC76B"] select _linked,
        _cs
    ]) call comspec_overwatch_connect_fnc_tr);

    (_disp displayCtrl 9808) ctrlSetStructuredText parseText ((format [
        "<t font='PuristaSemibold' size='0.88' color='#E6F0E8'>%1</t><br/><t font='PuristaLight' size='0.72' color='#C8D2CA'>%2</t>",
        "Joindre mon journal de session (recommandé)" call _T,
        "Les dernières lignes du journal COMSPEC, l'état de la liaison et les versions du mod. Clés, jetons et mots de passe sont retirés avant l'envoi." call _T
    ]) call comspec_overwatch_connect_fnc_tr);
    (_disp displayCtrl 9805) cbSetChecked true;
    (_disp displayCtrl 9820) ctrlSetStructuredText parseText (("") call comspec_overwatch_connect_fnc_tr);

    // Catégories : [libellé, code envoyé à Athena (colonne Module de Signalements mod)].
    uiNamespace setVariable ["COMSPEC_BugReportCats", [
        ["Liaison Athena", "Liaison"],
        ["Téléphone ATAK", "ATAK"],
        ["Carte / repères", "Marqueurs"],
        ["Photos / messages", "Médias"],
        ["Gel / plantage", "Perf"],
        ["Autre", "Autre"]
    ]];
    [_this param [0, 0]] call comspec_overwatch_connect_fnc_bugReportPick;
    ctrlSetFocus (_disp displayCtrl 9802);
};
