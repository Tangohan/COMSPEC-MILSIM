/*
    Tableau de bord en jeu : Liste tous les relais avec statut et erreurs
    Accessible via action ACE sur objets spéciaux ou via scroll menu
    
    Usage: call ATHENA_fnc_openRelayDashboard
*/

if (!hasInterface) exitWith {};

// Fermer les dialogs existants
closeDialog 0;

// Créer le display
private _display = findDisplay 46 createDisplay "RscDisplayEmpty";

// Background semi-transparent
private _bg = _display ctrlCreate ["RscText", 1000];
_bg ctrlSetPosition [0, 0, 1, 1];
_bg ctrlSetBackgroundColor [0, 0, 0, 0.8];
_bg ctrlCommit 0;

// Header
private _header = _display ctrlCreate ["RscStructuredText", 1001];
_header ctrlSetPosition [0.3, 0.15, 0.4, 0.08];
_header ctrlSetStructuredText parseText format [
    "<t size='1.5' color='#FFD700' align='center' font='PuristaBold'>TABLEAU DE BORD RELAIS ATAK</t><br/>" +
    "<t size='0.8' color='#AAAAAA' align='center'>Réseau radio tactique — Statut temps réel</t>"
];
_header ctrlSetBackgroundColor [0.1, 0.1, 0.1, 0.9];
_header ctrlCommit 0;

// Trouver tous les relais
private _allRelays = [];
{
    if (_x getVariable ["COMSPEC_AtakRelayUid", ""] != "") then {
        _allRelays pushBack _x;
    };
} forEach (entities "All");

// Trier par distance
_allRelays = [_allRelays, [], {player distance2D _x}, "ASCEND"] call BIS_fnc_sortBy;

// Stats globales
private _totalRelays = count _allRelays;
private _activeRelays = 0;
private _deadRelays = 0;
private _errorsCount = 0;

{
    if (alive _x && damage _x < 0.95) then {
        _activeRelays = _activeRelays + 1;
    } else {
        _deadRelays = _deadRelays + 1;
        _errorsCount = _errorsCount + 1;
    };
} forEach _allRelays;

// Stats bar
private _statsBar = _display ctrlCreate ["RscStructuredText", 1002];
_statsBar ctrlSetPosition [0.3, 0.24, 0.4, 0.05];
private _statsText = format [
    "<t size='0.9' color='#00FF00'>● %1 ACTIFS</t>  " +
    "<t size='0.9' color='#FF0000'>● %2 HORS LIGNE</t>  " +
    "<t size='0.9' color='#FFAA00'>⚠ %3 ERREURS</t>  " +
    "<t size='0.9' color='#AAAAAA'>TOTAL: %4</t>",
    _activeRelays,
    _deadRelays,
    _errorsCount,
    _totalRelays
];
_statsBar ctrlSetStructuredText parseText _statsText;
_statsBar ctrlSetBackgroundColor [0.05, 0.05, 0.05, 0.9];
_statsBar ctrlCommit 0;

// Liste scrollable
private _listBox = _display ctrlCreate ["RscListBox", 1003];
_listBox ctrlSetPosition [0.3, 0.3, 0.4, 0.45];
_listBox ctrlSetBackgroundColor [0.05, 0.05, 0.05, 0.9];
_listBox ctrlSetFont "EtelkaMonospacePro";
_listBox ctrlSetFontHeight 0.03;

// Remplir la liste
{
    private _relay = _x;
    private _uid = _relay getVariable ["COMSPEC_AtakRelayUid", "?"];
    private _name = _relay getVariable ["COMSPEC_AtakRelayName", "Relais"];
    private _range = _relay getVariable ["COMSPEC_AtakRelayRange", 2000];
    private _slots = _relay getVariable ["COMSPEC_AtakRelaySlots", 8];
    private _alive = alive _relay && damage _relay < 0.95;
    private _dist = round (player distance2D _relay);
    
    // Calculer slots utilisés
    private _used = 0;
    {
        if (isPlayer _x && {alive _x} && {(_x distance2D _relay) <= _range}) then {
            _used = _used + 1;
        };
    } forEach allPlayers;
    
    // Détection erreurs
    private _errorTag = "";
    private _colorCode = [0, 1, 0, 1]; // Vert par défaut
    
    if (!_alive) then {
        _errorTag = " [HORS LIGNE]";
        _colorCode = [1, 0, 0, 1]; // Rouge
    } else if (damage _relay > 0.5) then {
        _errorTag = " [ENDOMMAGÉ]";
        _colorCode = [1, 0.65, 0, 1]; // Orange
    } else if (_used >= _slots) then {
        _errorTag = " [SATURÉ]";
        _colorCode = [1, 1, 0, 1]; // Jaune
    };
    
    // Dernière sync
    private _lastSync = _relay getVariable ["COMSPEC_AtakRelayLastSync", 0];
    private _syncStatus = "JAMAIS";
    if (_lastSync > 0) then {
        private _timeSince = round ((time - _lastSync) / 60);
        if (_timeSince < 5) then {
            _syncStatus = "● EN DIRECT";
        } else {
            _syncStatus = "Il y a " + str _timeSince + " min";
        };
    };
    
    // Format: Nom | Portée | Connexions | Distance | Erreur
    private _lineText = format [
        "%1 | %2m | %3/%4 | %5m | %6 | %7",
        _name,
        _range,
        _used,
        _slots,
        _dist,
        _syncStatus,
        _errorTag
    ];
    
    private _index = _listBox lbAdd _lineText;
    _listBox lbSetColor [_index, _colorCode];
    _listBox lbSetData [_index, netId _relay];
    
} forEach _allRelays;

// Bouton "Resync tous"
private _btnResync = _display ctrlCreate ["RscButton", 1004];
_btnResync ctrlSetPosition [0.32, 0.77, 0.15, 0.04];
_btnResync ctrlSetText "🔄 RESYNC TOUS";
_btnResync ctrlSetBackgroundColor [0.2, 0.4, 0.8, 0.9];
_btnResync ctrlSetTextColor [1, 1, 1, 1];
_btnResync ctrlAddEventHandler ["ButtonClick", {
    params ["_ctrl"];
    
    // Resync tous les relais
    private _count = 0;
    {
        if (_x getVariable ["COMSPEC_AtakRelayUid", ""] != "") then {
            [_x, true] call ATHENA_fnc_syncAtakRelay;
            _x setVariable ["COMSPEC_AtakRelayLastSync", time, true];
            _count = _count + 1;
        };
    } forEach (entities "All");
    
    hint format ["✅ %1 relais resynchronisés", _count];
    
    // Fermer et rouvrir pour refresh
    closeDialog 0;
    [] spawn {
        sleep 0.5;
        call ATHENA_fnc_openRelayDashboard;
    };
}];

// Bouton "Téléporter"
private _btnTeleport = _display ctrlCreate ["RscButton", 1005];
_btnTeleport ctrlSetPosition [0.48, 0.77, 0.15, 0.04];
_btnTeleport ctrlSetText "📍 TÉLÉPORTER";
_btnTeleport ctrlSetBackgroundColor [0.8, 0.4, 0.2, 0.9];
_btnTeleport ctrlSetTextColor [1, 1, 1, 1];
_btnTeleport ctrlAddEventHandler ["ButtonClick", {
    params ["_ctrl"];
    private _display = ctrlParent _ctrl;
    private _listBox = _display displayCtrl 1003;
    private _selectedIndex = lbCurSel _listBox;
    
    if (_selectedIndex >= 0) then {
        private _netId = _listBox lbData _selectedIndex;
        private _relay = objectFromNetId _netId;
        
        if (!isNull _relay) then {
            player setPos ((getPosATL _relay) vectorAdd [0, 5, 0]);
            hint "✈️ Téléporté au relais";
        };
    } else {
        hint "⚠️ Sélectionnez un relais dans la liste";
    };
}];

// Bouton "Fermer"
private _btnClose = _display ctrlCreate ["RscButton", 1006];
_btnClose ctrlSetPosition [0.64, 0.77, 0.05, 0.04];
_btnClose ctrlSetText "✖";
_btnClose ctrlSetBackgroundColor [0.6, 0.1, 0.1, 0.9];
_btnClose ctrlSetTextColor [1, 1, 1, 1];
_btnClose ctrlAddEventHandler ["ButtonClick", {
    closeDialog 0;
}];

// Info footer
private _footer = _display ctrlCreate ["RscStructuredText", 1007];
_footer ctrlSetPosition [0.3, 0.82, 0.4, 0.03];
_footer ctrlSetStructuredText parseText "<t size='0.7' color='#666666' align='center'>Double-cliquez sur un relais pour plus de détails | ESC pour fermer</t>";
_footer ctrlCommit 0;

// Double-clic pour détails
_listBox ctrlAddEventHandler ["LBDblClick", {
    params ["_ctrl", "_selectedIndex"];
    
    private _netId = _ctrl lbData _selectedIndex;
    private _relay = objectFromNetId _netId;
    
    if (!isNull _relay) then {
        private _name = _relay getVariable ["COMSPEC_AtakRelayName", "Relais"];
        private _uid = _relay getVariable ["COMSPEC_AtakRelayUid", "?"];
        private _range = _relay getVariable ["COMSPEC_AtakRelayRange", 2000];
        private _pos = getPosATL _relay;
        private _ip = _relay getVariable ["COMSPEC_AtakRelayIp", "N/A"];
        private _power = _relay getVariable ["COMSPEC_AtakRelayPowerW", 25];
        private _throughput = _relay getVariable ["COMSPEC_AtakRelayThroughput", 12];
        private _reliability = _relay getVariable ["COMSPEC_AtakRelayReliability", 92];
        
        private _detailsText = format [
            "═══════════════════════════════════\n" +
            "   DÉTAILS RELAIS : %1\n" +
            "═══════════════════════════════════\n" +
            "UID         : %2\n" +
            "Position    : [%3, %4, %5]\n" +
            "Portée      : %6 m\n" +
            "IP          : %7\n" +
            "Puissance   : %8 W\n" +
            "Débit       : %9 Mbps\n" +
            "Fiabilité   : %10 %%\n" +
            "État        : %11\n" +
            "Dégâts      : %12 %%\n" +
            "═══════════════════════════════════",
            _name,
            _uid,
            round (_pos select 0),
            round (_pos select 1),
            round (_pos select 2),
            _range,
            _ip,
            _power,
            _throughput,
            _reliability,
            if (alive _relay) then {"✅ EN LIGNE"} else {"❌ HORS LIGNE"},
            round ((damage _relay) * 100)
        ];
        
        hint _detailsText;
    };
}];

// ESC pour fermer
_display displayAddEventHandler ["KeyDown", {
    params ["_display", "_key"];
    if (_key == 1) then { // ESC
        closeDialog 0;
        true
    };
    false
}];

diag_log format ["[COMSPEC ATAK][Dashboard] Ouvert : %1 relais affichés", count _allRelays];

true
