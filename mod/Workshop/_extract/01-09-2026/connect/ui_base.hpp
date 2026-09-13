// Déclarations anticipées A3 + boutons Overwatch (inspirés cTab_RscButton).
// Ne pas redéfinir RscText/RscButton complets → « Member already defined ».
class RscText;
class RscButton;
class RscEdit;
class RscStructuredText;
class RscPicture;
class RscPictureKeepAspect;
class RscListBox;
class RscCombo;
class RscWebBrowser;
class RscMapControl;
class RscHTML;
class RscControlsGroup;

class COMSPEC_RscButton: RscButton {
    access = 0;
    type = 1;
    style = 2;
    shadow = 2;
    font = "PuristaMedium";
    sizeEx = "(((((safezoneW / safezoneH) min 1.2) / 1.2) / 25) * 1)";
    colorText[] = {0.92, 0.95, 0.97, 1};
    colorDisabled[] = {0.4, 0.4, 0.4, 1};
    colorBackground[] = {0.05, 0.12, 0.16, 0.95};
    colorBackgroundDisabled[] = {0.15, 0.15, 0.15, 0.6};
    colorBackgroundActive[] = {0.12, 0.3, 0.32, 1};
    colorFocused[] = {0.12, 0.3, 0.32, 1};
    colorShadow[] = {0, 0, 0, 0.6};
    colorBorder[] = {0, 0, 0, 0};
    offsetX = 0;
    offsetY = 0;
    offsetPressedX = 0.001;
    offsetPressedY = 0.001;
    borderSize = 0;
    soundEnter[] = {"\A3\ui_f\data\sound\RscButton\soundEnter", 0.09, 1};
    soundPush[] = {"\A3\ui_f\data\sound\RscButton\soundPush", 0.09, 1};
    soundClick[] = {"\A3\ui_f\data\sound\RscButton\soundClick", 0.09, 1};
    soundEscape[] = {"\A3\ui_f\data\sound\RscButton\soundEscape", 0.09, 1};
};

class COMSPEC_RscButtonAccent: COMSPEC_RscButton {
    colorBackground[] = {0.08, 0.22, 0.2, 0.95};
    colorBackgroundActive[] = {0.12, 0.35, 0.3, 1};
    colorFocused[] = {0.12, 0.35, 0.3, 1};
    colorText[] = {0.85, 0.95, 0.92, 1};
};

class COMSPEC_RscButtonDanger: COMSPEC_RscButton {
    colorBackground[] = {0.28, 0.08, 0.08, 0.95};
    colorBackgroundActive[] = {0.4, 0.12, 0.12, 1};
    colorFocused[] = {0.4, 0.12, 0.12, 1};
    colorText[] = {0.95, 0.88, 0.88, 1};
};

class COMSPEC_RscInvisibleButton: COMSPEC_RscButton {
    shadow = 0;
    colorText[] = {0, 0, 0, 0};
    colorDisabled[] = {0, 0, 0, 0};
    colorBackground[] = {0, 0, 0, 0};
    colorBackgroundDisabled[] = {0, 0, 0, 0};
    colorBackgroundActive[] = {1, 1, 1, 0.08};
    colorFocused[] = {0, 0, 0, 0};
    colorShadow[] = {0, 0, 0, 0};
    colorBorder[] = {0, 0, 0, 0};
};
