/* Météo affichée : [température °C, vent m/s, provenance du vent (N, NE…)]. ACE Weather si présent. */
private _temp = if (!isNil "ace_weather_currentTemperature") then {
    ace_weather_currentTemperature
} else {
    18 + 7 * sin (((dayTime - 9) / 24) * 360) - overcast * 4 - rain * 3
};
private _w = wind;
private _speed = vectorMagnitude [_w select 0, _w select 1, 0];
private _from = (((_w select 0) atan2 (_w select 1)) + 180) mod 360;
private _card = ["N", "NE", "E", "SE", "S", "SO", "O", "NO"] select ((round (_from / 45)) mod 8);
[round _temp, _speed, _card]
