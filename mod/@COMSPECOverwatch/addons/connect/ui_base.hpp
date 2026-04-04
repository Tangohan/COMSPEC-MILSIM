// Classes UI de base pour compilation addon (CfgConvert ne charge pas A3\ui_f\config.cpp)
class RscText {
    access = 0;
    type = 0;
    idc = -1;
    style = 0;
    linespacing = 1;
    colorBackground[] = {0, 0, 0, 0};
    colorText[] = {1, 1, 1, 1};
    text = "";
    shadow = 1;
    font = "RobotoCondensed";
    SizeEx = 0.04;
};

class RscButton {
    access = 0;
    type = 1;
    text = "";
    colorText[] = {1, 1, 1, 1};
    colorDisabled[] = {0.4, 0.4, 0.4, 1};
    colorBackground[] = {0, 0, 0, 0.8};
    colorBackgroundActive[] = {0, 0, 0, 1};
    font = "RobotoCondensed";
    sizeEx = 0.03921;
};

class RscEdit {
    access = 0;
    type = 2;
    idc = -1;
    style = 0;
    x = 0; y = 0; w = 0.2; h = 0.04;
    colorBackground[] = {0, 0, 0, 0.5};
    colorText[] = {1, 1, 1, 1};
    text = "";
    font = "RobotoCondensed";
    sizeEx = 0.04;
    autocomplete = "";
};

class RscStructuredText {
    access = 0;
    type = 13;
    idc = -1;
    style = 0;
    x = 0; y = 0; w = 0.1; h = 0.05;
    colorBackground[] = {0, 0, 0, 0};
    colorText[] = {1, 1, 1, 1};
    text = "";
    font = "RobotoCondensed";
    size = 0.04;
    class Attributes {
        font = "RobotoCondensed";
        color = "#ffffff";
        align = "left";
        valign = "middle";
        shadow = 1;
        size = "1";
    };
};
