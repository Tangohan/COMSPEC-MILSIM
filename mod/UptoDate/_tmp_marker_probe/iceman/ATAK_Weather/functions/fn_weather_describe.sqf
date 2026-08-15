params [
    ["_cloud", 0],
    ["_rain", 0],
    ["_fog", 0]
];

switch (true) do {
    case (_fog >= 0.45): {["Dense Fog", "\BCE_Core\data\mist.paa"]};
    case (_fog >= 0.15): {["Mist", "\BCE_Core\data\mist.paa"]};
    case (_rain >= 0.60): {["Heavy Rain", "\BCE_Core\data\Heavy_Rain.paa"]};
    case (_rain >= 0.20): {["Rain", "\BCE_Core\data\Moderate_rain.paa"]};
    case (_rain >= 0.05): {["Light Rain", "\BCE_Core\data\Moderate_rain.paa"]};
    case (_cloud >= 0.82): {["Overcast", "\BCE_Core\data\overcast.paa"]};
    case (_cloud >= 0.55): {["Cloudy", "\BCE_Core\data\overcast.paa"]};
    case (_cloud >= 0.25): {["Partly Cloudy", "\BCE_Core\data\clouds_sun.paa"]};
    default {["Clear", "\BCE_Core\data\sunny.paa"]};
}
