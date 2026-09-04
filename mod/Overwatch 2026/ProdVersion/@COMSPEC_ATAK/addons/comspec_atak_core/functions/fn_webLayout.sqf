params [["_display", displayNull]];

if (isNull _display) then
{
    _display = uiNamespace getVariable [
        "COMSPEC_ATAK_Display",
        displayNull
    ];
};

if (isNull _display) exitWith {false};

// =====================================================================
// TEXTURE / DEVICE GEOMETRY
// =====================================================================
//
// device_frame_source_2048x1024.png is a PADDED texture.
// The original artwork was not resized into 2048x1024: it was placed in
// a power-of-two canvas.
//
// Therefore the PAA control itself MUST keep the 2048/1024 = 2.0 aspect.
// Using 1749/966 on this padded texture would compress the artwork.
//
// Verified transparent glass on the padded source:
//   x = 393
//   y = 109
//   w = 1269
//   h = 621
//
// =====================================================================

private _res = getResolution;
private _pxW = (_res param [0, 1920]) max 1;
private _pxH = (_res param [1, 1080]) max 1;

private _textureAspect = 2048 / 1024;

// Keep comfortable margins around the physical terminal.
private _maxPxW = 0.92 * _pxW;
private _maxPxH = 0.84 * _pxH;

private _framePxH = _maxPxH;
private _framePxW = _framePxH * _textureAspect;

if (_framePxW > _maxPxW) then
{
    _framePxW = _maxPxW;
    _framePxH = _framePxW / _textureAspect;
};

// Pixel fraction -> safeZone coordinates.
// This keeps the texture aspect independent from Arma UI scale.
private _uiPerPxX = safeZoneW / _pxW;
private _uiPerPxY = safeZoneH / _pxH;

private _w = _framePxW * _uiPerPxX;
private _h = _framePxH * _uiPerPxY;

private _fx = safeZoneX + ((safeZoneW - _w) * 0.5);
private _fy = safeZoneY + ((safeZoneH - _h) * 0.5);

private _offset = missionNamespace getVariable [
    "COMSPEC_ATAK_Offset",
    profileNamespace getVariable [
        "COMSPEC_ATAK_Offset",
        [0,0]
    ]
];

private _offsetX = _offset param [
    0,
    0,
    [0]
];

private _offsetY = _offset param [
    1,
    0,
    [0]
];


_fx = _fx + _offsetX;
_fy = _fy + _offsetY;

// =====================================================================
// GLASS
// =====================================================================

private _sx = _fx + ((393 / 2048) * _w);
private _sy = _fy + ((109 / 1024) * _h);
private _sw = (1269 / 2048) * _w;
private _sh = (629 / 1024) * _h; // +8 px source overlap to eliminate bottom seam against PAA bezel

// =====================================================================
// RIGHT BEZEL BUTTONS
// =====================================================================

private _hx = _fx + ((1668 / 2048) * _w);
private _hy = _fy + ((286 / 1024) * _h);
private _hw = (30 / 2048) * _w;
private _hh = (420 / 1024) * _h;
private _btnH = _hh / 3;

// =====================================================================
// STORE
// =====================================================================

private _layout = [
    _fx, _fy, _w, _h,
    _sx, _sy, _sw, _sh
];

uiNamespace setVariable [
    "COMSPEC_ATAK_Layout",
    _layout
];

missionNamespace setVariable [
    "COMSPEC_ATAK_FramePos",
    [_fx, _fy, _w, _h],
    false
];

missionNamespace setVariable [
    "COMSPEC_ATAK_HolePos",
    [_sx, _sy, _sw, _sh],
    false
];

// IMPORTANT:
// The browser is now ONLY the glass.
// The HTML no longer renders the hardware frame in-game.
missionNamespace setVariable [
    "COMSPEC_ATAK_BrowserPos",
    [_sx, _sy, _sw, _sh],
    false
];

private _liveMapRect = missionNamespace getVariable ["COMSPEC_ATAK_LiveMapViewport", []];
if ((count _liveMapRect) < 4) then
{
    _liveMapRect = [
        _sx + (_sw * 0.072),
        _sy + (_sh * 0.105),
        _sw * 0.643,
        _sh * 0.770
    ];
};

// =====================================================================
// PAA + BACKGROUND
// =====================================================================

private _frame = _display displayCtrl 1001;

if (!isNull _frame) then
{
    _frame ctrlSetPosition [
        _fx,
        _fy,
        _w,
        _h
    ];

    _frame ctrlCommit 0;
};

private _back = _display displayCtrl 1000;

if (!isNull _back) then
{
    _back ctrlSetPosition [
        _sx,
        _sy,
        _sw,
        _sh
    ];

    _back ctrlCommit 0;
};

// =====================================================================
// WEB / MAP / NATIVE OVERLAYS
// =====================================================================

{
    _x params [
        "_idc",
        "_xPos",
        "_yPos",
        "_wPos",
        "_hPos"
    ];

    private _ctrl = _display displayCtrl _idc;

    if (!isNull _ctrl) then
    {
        _ctrl ctrlSetPosition [
            _xPos,
            _yPos,
            _wPos,
            _hPos
        ];

        _ctrl ctrlCommit 0;
    };
}
forEach
[
    // Invisible drag area on the top-left hardware/header.
    [1190,
        _fx + (0.13 * _w),
        _fy + (0.025 * _h),
        0.25 * _w,
        0.055 * _h
    ],

    // Browser: GLASS ONLY.
    [1100, _sx, _sy, _sw, _sh],

    // Fallback / help.
    [
        9430,
        _sx + (0.05 * _sw),
        _sy + (0.18 * _sh),
        0.90 * _sw,
        0.62 * _sh
    ],

    // Native tactical maps: keep the last reported hole, not the full glass.
    // Full-glass maps would cover the HTML chrome if they sit above Chromium.
    [2201,
        (_liveMapRect param [0, _sx]),
        (_liveMapRect param [1, _sy]),
        (_liveMapRect param [2, _sw]),
        (_liveMapRect param [3, _sh])
    ],
    [2202,
        (_liveMapRect param [0, _sx]),
        (_liveMapRect param [1, _sy]),
        (_liveMapRect param [2, _sw]),
        (_liveMapRect param [3, _sh])
    ],

    // Native LiveMap toolbar: compact ATAK strip, not a desktop menu.
    [2209, _sx + 0.010 * _sw, _sy + 0.010 * _sh, 0.510 * _sw, 0.055 * _sh],

    [2210, _sx + 0.016 * _sw, _sy + 0.016 * _sh, 0.064 * _sw, 0.040 * _sh],
    [2211, _sx + 0.084 * _sw, _sy + 0.016 * _sh, 0.064 * _sw, 0.040 * _sh],
    [2212, _sx + 0.152 * _sw, _sy + 0.016 * _sh, 0.060 * _sw, 0.040 * _sh],
    [2213, _sx + 0.216 * _sw, _sy + 0.016 * _sh, 0.060 * _sw, 0.040 * _sh],
    [2214, _sx + 0.280 * _sw, _sy + 0.016 * _sh, 0.060 * _sw, 0.040 * _sh],
    [2215, _sx + 0.344 * _sw, _sy + 0.016 * _sh, 0.060 * _sw, 0.040 * _sh],
    [2216, _sx + 0.408 * _sw, _sy + 0.016 * _sh, 0.096 * _sw, 0.040 * _sh],

    // Compact bottom telemetry; leave the map itself unobstructed.
    [2220, _sx + 0.012 * _sw, _sy + _sh - 0.072 * _sh, 0.300 * _sw, 0.055 * _sh],
    [2221, _sx + 0.318 * _sw, _sy + _sh - 0.072 * _sh, 0.270 * _sw, 0.055 * _sh],

    // Toast / fallback.
    [1090, _sx + 0.22 * _sw, _sy + 0.08 * _sh, 0.56 * _sw, 0.12 * _sh],

    // Native bezel buttons.
    [1150, _hx, _hy, _hw, _btnH],
    [1151, _hx, _hy + _btnH, _hw, _btnH],
    [1152, _hx, _hy + (2 * _btnH), _hw, _btnH],
    [1153, _hx, _hy + _hh + (0.012 * _h), _hw, _btnH]
];

true
