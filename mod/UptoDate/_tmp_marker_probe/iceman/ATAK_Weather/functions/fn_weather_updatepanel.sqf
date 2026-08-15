private _group = uiNamespace getVariable ["Iceman_ATAK_Weather_group", controlNull];
if (isNull _group) exitWith {false};

private _conditionCtrl = _group controlsGroupCtrl 9301;
private _iconCtrl = _group controlsGroupCtrl 9310;
private _currentCtrl = _group controlsGroupCtrl 9311;
private _metricsCtrl = _group controlsGroupCtrl 9312;
private _forecastCtrl = _group controlsGroupCtrl 9321;
private _transitionCtrl = _group controlsGroupCtrl 9330;

private _cloud = overcast max 0 min 1;
private _forecastCloud = overcastForecast max 0 min 1;
private _rain = rain max 0 min 1;
private _fog = fog max 0 min 1;
private _forecastFog = fogForecast max 0 min 1;
private _humidity = humidity max 0 min 1;
private _temperature = round (ambientTemperature # 0);
private _windSpeed = vectorMagnitude wind;
private _windKph = round (_windSpeed * 3.6);
private _windOctant = if (isNil "cTab_fnc_degreeToOctant") then {
    format ["%1 deg", round windDir]
} else {
    [windDir] call cTab_fnc_degreeToOctant
};
private _location = player call BIS_fnc_locationDescription;
private _currentWeather = [_cloud, _rain, _fog] call Iceman_fnc_weather_describe;
_currentWeather params ["_condition", "_icon"];

if (!isNull _conditionCtrl) then {
    _conditionCtrl ctrlSetStructuredText parseText format [
        "<t align='center' color='#b8e8ef'>%1</t><br/><t align='center' size='0.72'>%2</t>",
        _condition,
        _location
    ];
};
if (!isNull _iconCtrl) then {
    _iconCtrl ctrlSetText _icon;
};
if (!isNull _currentCtrl) then {
    _currentCtrl ctrlSetStructuredText parseText format [
        "<t size='1.55' color='#ffffff'>%1&#176;C</t><br/><t size='0.78' color='#b8e8ef'>%2</t>",
        _temperature,
        _condition
    ];
};
if (!isNull _metricsCtrl) then {
    _metricsCtrl ctrlSetStructuredText parseText format [
        "<t size='0.78'>Wind %1 %2 m/s (%3 km/h)<br/>Humidity %4%5 | Cloud %6%5 | Fog %7%5</t>",
        _windOctant,
        _windSpeed toFixed 1,
        _windKph,
        round (_humidity * 100),
        "%",
        round (_cloud * 100),
        round (_fog * 100)
    ];
};

private _formatClock = {
    params ["_hours"];
    _hours = _hours % 24;
    if (_hours < 0) then {_hours = _hours + 24};
    private _hour = floor _hours;
    private _minute = floor (((_hours - _hour) * 60) + 0.5);
    if (_minute >= 60) then {
        _minute = 0;
        _hour = (_hour + 1) % 24;
    };
    format [
        "%1%2:%3%4",
        ["", "0"] select (_hour < 10),
        _hour,
        ["", "0"] select (_minute < 10),
        _minute
    ]
};

private _transition = nextWeatherChange max 1;
if (!isNull _forecastCtrl) then {
    lbClear _forecastCtrl;
    {
        private _offset = _x;
        private _blend = (_offset / _transition) min 1;
        private _cloudAt = (_cloud + ((_forecastCloud - _cloud) * _blend)) max 0 min 1;
        private _fogAt = (_fog + ((_forecastFog - _fog) * _blend)) max 0 min 1;
        private _rainChance = ((((_cloudAt - 0.45) / 0.55) max 0 min 1) * (0.55 + (0.45 * _humidity))) max 0 min 1;
        if (_offset == 0) then {
            _rainChance = _rainChance max _rain;
        };
        private _rainAt = if (_offset == 0) then {_rain} else {
            switch (true) do {
                case (_rainChance >= 0.75): {0.65};
                case (_rainChance >= 0.45): {0.25};
                case (_rainChance >= 0.15): {0.07};
                default {0};
            }
        };
        private _weather = [_cloudAt, _rainAt, _fogAt] call Iceman_fnc_weather_describe;
        _weather params ["_label", "_rowIcon"];
        private _clock = [dayTime + (_offset / 3600)] call _formatClock;
        private _prefix = [format ["+%1h", round (_offset / 3600)], "NOW"] select (_offset == 0);
        private _row = _forecastCtrl lbAdd format [
            "%1 %2   %3   Rain %4%5 | Fog %6%5",
            _prefix,
            _clock,
            _label,
            round (_rainChance * 100),
            "%",
            round (_fogAt * 100)
        ];
        _forecastCtrl lbSetPicture [_row, _rowIcon];
        _forecastCtrl lbSetTooltip [_row, format ["Cloud cover %1%2", round (_cloudAt * 100), "%"]];
    } forEach [0, 3600, 7200, 10800];
    _forecastCtrl lbSetCurSel 0;
};

if (!isNull _transitionCtrl) then {
    private _transitionText = if (_transition >= 86400) then {
        format ["%1d %2h", floor (_transition / 86400), floor ((_transition % 86400) / 3600)]
    } else {
        if (_transition >= 3600) then {
            format ["%1h %2m", floor (_transition / 3600), floor ((_transition % 3600) / 60)]
        } else {
            format ["%1m", ceil (_transition / 60)]
        }
    };
    _transitionCtrl ctrlSetStructuredText parseText format [
        "<t align='center' size='0.78'>Next weather target in <t color='#b8e8ef'>%1</t> | Updated %2</t>",
        _transitionText,
        [dayTime] call _formatClock
    ];
};

true
