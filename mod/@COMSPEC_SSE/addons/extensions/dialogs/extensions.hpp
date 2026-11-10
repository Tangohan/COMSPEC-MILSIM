// Fenêtre « SSE ROLEPLAY · Extensions » (fn_docExtWindow) : extensions de documents détectées, à gauche ; description,
// modèles, aperçu et bouton Activer / Désactiver, à droite. Aucune alerte Arma : l'état est dit sur la ligne du bas.
// RscText, RscButton, RscStructuredText, RscPicture, RscListBox : déclarés dans config.cpp.

#define EXT_C_BG     {0.055,0.065,0.07,0.97}
#define EXT_C_PANEL  {0.085,0.1,0.105,0.94}
#define EXT_C_TEXT   {0.9,0.93,0.9,1}
#define EXT_C_MUTED  {0.62,0.68,0.64,1}
#define EXT_C_ACCENT {0.45,0.8,0.5,1}

class COMSPEC_SSE_ExtText: RscText {
    idc = -1;
    shadow = 0;
    font = "RobotoCondensed";
    sizeEx = 0.032;
    colorText[] = EXT_C_TEXT;
    colorBackground[] = {0, 0, 0, 0};
};
class COMSPEC_SSE_ExtMuted: COMSPEC_SSE_ExtText {
    colorText[] = EXT_C_MUTED;
    sizeEx = 0.028;
};
class COMSPEC_SSE_ExtRich: RscStructuredText {
    idc = -1;
    shadow = 0;
    size = 0.028;
    colorBackground[] = {0, 0, 0, 0};
    class Attributes {
        font = "RobotoCondensed";
        color = "#e6ede6";
        align = "left";
        shadow = 0;
    };
};
class COMSPEC_SSE_ExtButton: RscButton {
    idc = -1;
    shadow = 0;
    font = "PuristaMedium";
    sizeEx = 0.03;
    colorText[] = EXT_C_TEXT;
    colorDisabled[] = {0.45, 0.48, 0.46, 1};
    colorBackground[] = {0.16, 0.19, 0.2, 1};
    colorBackgroundActive[] = {0.24, 0.28, 0.29, 1};
    colorBackgroundDisabled[] = {0.12, 0.13, 0.14, 0.8};
    colorFocused[] = {0.16, 0.19, 0.2, 1};
    colorShadow[] = {0, 0, 0, 0};
    colorBorder[] = {0, 0, 0, 0};
    offsetPressedX = 0;
    offsetPressedY = 0;
};
class COMSPEC_SSE_ExtList: RscListBox {
    idc = -1;
    shadow = 0;
    font = "RobotoCondensed";
    sizeEx = 0.03;
    rowHeight = 0.045;
    colorText[] = EXT_C_TEXT;
    colorSelect[] = {0.03, 0.06, 0.04, 1};
    colorSelect2[] = {0.03, 0.06, 0.04, 1};
    colorSelectBackground[] = EXT_C_ACCENT;
    colorSelectBackground2[] = EXT_C_ACCENT;
    colorBackground[] = EXT_C_PANEL;
};

class COMSPEC_SSE_DocExtensions {
    idd = 93900;
    movingEnable = 0;
    enableSimulation = 1;
    onLoad = "(_this select 0) call comspec_sse_fnc_trDisplay; ['load', _this select 0] call comspec_sse_fnc_docExtWindow; if (!isNil 'comspec_sse_fnc_guideButton') then { [_this select 0, 'Extensions'] call comspec_sse_fnc_guideButton; };";
    onUnload = "['unload'] call comspec_sse_fnc_docExtWindow";

    class controlsBackground {
        class Dim: RscText {
            idc = -1;
            x = "safezoneX"; y = "safezoneY"; w = "safezoneW"; h = "safezoneH";
            colorBackground[] = {0, 0, 0, 0.72};
        };
        class Panel: RscText {
            idc = -1;
            x = 0.12; y = 0.12; w = 0.76; h = 0.72;
            colorBackground[] = EXT_C_BG;
        };
        class RightPanel: RscText {
            idc = -1;
            x = 0.46; y = 0.23; w = 0.40; h = 0.50;
            colorBackground[] = EXT_C_PANEL;
        };
    };

    class controls {
        class Title: COMSPEC_SSE_ExtText {
            idc = 93901;
            text = "$STR_COMSPEC_SSE_EXT_Title";
            font = "PuristaMedium";
            sizeEx = 0.046;
            x = 0.14; y = 0.14; w = 0.72; h = 0.05;
        };
        class Summary: COMSPEC_SSE_ExtMuted {
            idc = 93902;
            x = 0.14; y = 0.19; w = 0.72; h = 0.03;
        };
        class List: COMSPEC_SSE_ExtList {
            idc = 93910;
            x = 0.14; y = 0.23; w = 0.30; h = 0.50;
            onLBSelChanged = "['select'] call comspec_sse_fnc_docExtWindow";
        };
        class Name: COMSPEC_SSE_ExtText {
            idc = 93920;
            font = "PuristaMedium";
            sizeEx = 0.036;
            x = 0.47; y = 0.24; w = 0.38; h = 0.04;
        };
        class Description: COMSPEC_SSE_ExtRich {
            idc = 93921;
            x = 0.47; y = 0.28; w = 0.38; h = 0.09;
        };
        class DocsLabel: COMSPEC_SSE_ExtMuted {
            idc = 93925;
            text = "$STR_COMSPEC_SSE_EXT_Models";
            x = 0.47; y = 0.37; w = 0.38; h = 0.03;
        };
        class Docs: COMSPEC_SSE_ExtRich {
            idc = 93922;
            x = 0.47; y = 0.40; w = 0.38; h = 0.10;
        };
        class Preview: RscPicture {
            idc = 93923;
            style = 2096;
            text = "";
            x = 0.47; y = 0.50; w = 0.38; h = 0.22;
        };
        class State: COMSPEC_SSE_ExtRich {
            idc = 93924;
            x = 0.14; y = 0.74; w = 0.72; h = 0.04;
        };
        class BtnToggle: COMSPEC_SSE_ExtButton {
            idc = 93930;
            text = "$STR_COMSPEC_SSE_EXT_Enable";
            x = 0.46; y = 0.785; w = 0.19; h = 0.04;
            action = "['toggle'] call comspec_sse_fnc_docExtWindow";
            colorBackground[] = {0.15, 0.3, 0.19, 1};
            colorBackgroundActive[] = {0.22, 0.43, 0.27, 1};
            colorFocused[] = {0.15, 0.3, 0.19, 1};
        };
        class BtnClose: COMSPEC_SSE_ExtButton {
            idc = 93931;
            text = "$STR_COMSPEC_SSE_EXT_Close";
            x = 0.67; y = 0.785; w = 0.19; h = 0.04;
            action = "closeDialog 2";
            colorBackground[] = {0.24, 0.12, 0.11, 1};
            colorBackgroundActive[] = {0.36, 0.16, 0.14, 1};
            colorFocused[] = {0.24, 0.12, 0.11, 1};
        };
    };
};
