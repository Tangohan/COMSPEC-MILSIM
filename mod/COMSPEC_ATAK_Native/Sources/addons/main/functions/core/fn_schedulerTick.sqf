disableSerialization;
private _perfT = diag_tickTime;
private _state = uiNamespace getVariable ["COMSPEC_ATAK_State",createHashMap];
private _display = _state getOrDefault ["display",displayNull];
if (isNull _display) exitWith { [] call comspec_atak_native_fnc_schedulerStop };
private _now = diag_tickTime;
// Données locales (unités amies, ennemis repérés) : 4 fois par seconde sur la carte et le BFT, sinon toutes les 2 s
// (balayage de toutes les unités du camp : coûteux dans les grosses missions).
// Téléphone porté (HUD, petite carte) : une fois par seconde suffit, les symboles bougent peu à cette échelle.
private _fastEvery = [2, [1, 0.25] select (_state getOrDefault ["interactive", false])] select ((_state getOrDefault ["activePage", ""]) in ["MAP", "BFT"]);
if ((_now - (_state getOrDefault ["lastFast",0])) >= _fastEvery) then { _state set ["lastFast",_now]; private _t = diag_tickTime; [] call comspec_atak_native_fnc_localDataRefresh; ["unités et marqueurs (carte)", _t] call comspec_atak_native_fnc_perfAcc; };
// Animation d'extinction en cours (batterie vide, casse) : c'est fn_powerFx qui range le téléphone à la fin.
private _why = if ((_now - (_state getOrDefault ["lastSecond",0])) >= 1 && {((uiNamespace getVariable ["COMSPEC_ATAK_PowerFx", []]) param [1, -1]) <= _now}) then { [true] call comspec_atak_native_fnc_canUse } else { "" };
if (_why isNotEqualTo "") exitWith {
    // Téléphone perdu, retiré, batterie vide ou détruit : on range tout.
    uiNamespace setVariable ["COMSPEC_ATAK_HudWanted", false];
    [] call comspec_atak_native_fnc_close;
    [_why] call comspec_atak_native_fnc_deviceDenied;
};
// 4.2.1 : une seule tâche lourde par passage (0,2 s). Avant, barre d'état (1 s), sondages Athena (3 s), état de la
// liaison (5 s) et COMSPEC Link (10 s) tombaient ensemble dans la même image toutes les 30 s, et les quatre sondages des
// 3 s (cinq appels à la DLL) toujours ensemble. Ordre de priorité : barre d'état, sondages, liaison, COMSPEC Link ; une
// tâche due et doublée attend le passage suivant (0,2 s plus tard), aucune ne se perd.
private _busy = false;
if ((_now - (_state getOrDefault ["lastSecond",0])) >= 1) then { _state set ["lastSecond",_now]; [] call comspec_atak_native_fnc_statusUpdate; [] call comspec_atak_native_fnc_notificationsRender; _busy = true; };
private _refresh = profileNamespace getVariable ["COMSPEC_ATAK_BftRefresh",3];
// Sondages Athena répartis sur quatre passages : unités, marqueurs, ordres, messages (fn_remoteSync [étape]).
private _rStep = _state getOrDefault ["remoteStep", -1];
if (!_busy && {_rStep < 0} && {(_now - (_state getOrDefault ["lastRemote",0])) >= _refresh}) then { _state set ["lastRemote",_now]; _rStep = 0; };
if (!_busy && {_rStep >= 0}) then {
    private _t = diag_tickTime;
    [_rStep] call comspec_atak_native_fnc_remoteSync;
    [["Athena : sondage des unités", "Athena : sondage des marqueurs", "Athena : sondage des ordres", "Athena : sondage des messages"] select (_rStep min 3), _t] call comspec_atak_native_fnc_perfAcc;
    _state set ["remoteStep", [_rStep + 1, -1] select (_rStep >= 3)];
    _busy = true;
};
// Réponses d'Athena (positions, télémétrie) : lues en mémoire dans COMSPEC Link pour l'app Réseau et la barre d'état.
if (!_busy && {(_now - (_state getOrDefault ["lastPulse",0])) >= 5}) then { _state set ["lastPulse",_now]; private _t = diag_tickTime; [] call comspec_atak_native_fnc_athenaPulse; ["Athena : état de la liaison", _t] call comspec_atak_native_fnc_perfAcc; _busy = true; };
if (!_busy && {(_now - (_state getOrDefault ["lastSlow",0])) >= 10}) then { _state set ["lastSlow",_now]; private _t = diag_tickTime; [] call comspec_atak_native_fnc_importLegacyData; ["COMSPEC Link : ordres, alertes, intel", _t] call comspec_atak_native_fnc_perfAcc; };
["téléphone : passage 0,2 s", _perfT] call comspec_atak_native_fnc_perfAcc;
