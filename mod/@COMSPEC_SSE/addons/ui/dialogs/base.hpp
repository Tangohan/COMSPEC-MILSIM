/*
    COMSPEC SSE — classes de contrôles partagées.

    Déclarées une seule fois à la racine de la config (addon comspec_sse_ui).
    Les autres addons (zeus…) les réutilisent par simple déclaration avancée :
        class COMSPEC_SSE_RscButton;
    à condition d'avoir "comspec_sse_ui" dans requiredAddons.

    Les classes vanilla parentes (RscText, RscButton…) sont déclarées dans
    config.cpp avant l'inclusion de ce fichier.
*/

class COMSPEC_SSE_RscText: RscText {
    idc = -1;
    font = SSE_FONT;
    sizeEx = SSE_TXT_M;
    colorText[] = SSE_C_TEXT;
    colorBackground[] = SSE_C_NONE;
    shadow = 0;
};

class COMSPEC_SSE_RscLabel: COMSPEC_SSE_RscText {
    sizeEx = SSE_TXT_S;
    colorText[] = SSE_C_TEXT_MUTED;
};

class COMSPEC_SSE_RscSection: COMSPEC_SSE_RscText {
    font = SSE_FONT_BOLD;
    sizeEx = SSE_TXT_S;
    colorText[] = SSE_C_ACCENT;
};

class COMSPEC_SSE_RscBackground: COMSPEC_SSE_RscText {
    colorBackground[] = SSE_C_BG;
};

class COMSPEC_SSE_RscDim: COMSPEC_SSE_RscText {
    colorBackground[] = SSE_C_DIM;
};

class COMSPEC_SSE_RscPanel: COMSPEC_SSE_RscText {
    colorBackground[] = SSE_C_PANEL;
};

class COMSPEC_SSE_RscHeader: COMSPEC_SSE_RscText {
    font = SSE_FONT_TITLE;
    sizeEx = SSE_TXT_L;
    colorText[] = SSE_C_ACCENT;
    colorBackground[] = SSE_C_HEADER;
};

class COMSPEC_SSE_RscHeaderZeus: COMSPEC_SSE_RscHeader {
    colorText[] = SSE_C_ZEUS;
    colorBackground[] = SSE_C_HEADER_ZEUS;
};

class COMSPEC_SSE_RscSubHeader: COMSPEC_SSE_RscText {
    sizeEx = SSE_TXT_S;
    colorText[] = SSE_C_TEXT_MUTED;
    colorBackground[] = SSE_C_PANEL_ALT;
};

class COMSPEC_SSE_RscAccentLine: COMSPEC_SSE_RscText {
    colorBackground[] = SSE_C_ACCENT_LINE;
};

class COMSPEC_SSE_RscAccentLineZeus: COMSPEC_SSE_RscText {
    colorBackground[] = SSE_C_ZEUS_LINE;
};

class COMSPEC_SSE_RscStructuredText: RscStructuredText {
    idc = -1;
    size = SSE_TXT_M;
    colorText[] = SSE_C_TEXT;
    colorBackground[] = SSE_C_PANEL;
    shadow = 0;
    class Attributes {
        font = SSE_FONT;
        color = "#E6EDE6";
        colorLink = "#73CC80";
        align = "left";
        shadow = 0;
    };
};

class COMSPEC_SSE_RscListBox: RscListBox {
    idc = -1;
    font = SSE_FONT;
    sizeEx = SSE_TXT_M;
    rowHeight = SSE_Q(SSE_H(1));
    shadow = 0;
    colorText[] = SSE_C_TEXT;
    colorBackground[] = SSE_C_PANEL;
    colorSelect[] = {1,1,1,1};
    colorSelect2[] = {1,1,1,1};
    colorSelectBackground[] = {0.22,0.43,0.27,0.85};
    colorSelectBackground2[] = {0.22,0.43,0.27,0.6};
};

class COMSPEC_SSE_RscButton: RscButton {
    idc = -1;
    font = SSE_FONT_BOLD;
    sizeEx = SSE_TXT_M;
    shadow = 0;
    colorText[] = SSE_C_TEXT;
    colorDisabled[] = SSE_C_TEXT_MUTED;
    colorBackground[] = SSE_C_BTN;
    colorBackgroundActive[] = SSE_C_BTN_HOVER;
    colorFocused[] = SSE_C_BTN_HOVER;
    colorBackgroundDisabled[] = SSE_C_BTN_DISABLED;
    colorShadow[] = SSE_C_NONE;
    colorBorder[] = SSE_C_NONE;
    offsetX = 0;
    offsetY = 0;
    offsetPressedX = 0;
    offsetPressedY = 0;
};

class COMSPEC_SSE_RscButtonNav: COMSPEC_SSE_RscButton {
    font = SSE_FONT;
    colorBackground[] = SSE_C_BTN2;
    colorBackgroundActive[] = SSE_C_BTN2_HOVER;
    colorFocused[] = SSE_C_BTN2_HOVER;
};

class COMSPEC_SSE_RscButtonZeus: COMSPEC_SSE_RscButton {
    colorBackground[] = SSE_C_BTN_ZEUS;
    colorBackgroundActive[] = SSE_C_BTN_ZEUS_HOV;
    colorFocused[] = SSE_C_BTN_ZEUS_HOV;
};

class COMSPEC_SSE_RscButtonClose: COMSPEC_SSE_RscButton {
    font = SSE_FONT;
    colorBackground[] = SSE_C_BTN_CLOSE;
    colorBackgroundActive[] = SSE_C_BTN_CLOSE_HOV;
    colorFocused[] = SSE_C_BTN_CLOSE_HOV;
};

class COMSPEC_SSE_RscEdit: RscEdit {
    idc = -1;
    font = SSE_FONT;
    sizeEx = SSE_TXT_M;
    shadow = 0;
    colorText[] = SSE_C_TEXT;
    colorBackground[] = {0.03,0.04,0.045,0.95};
    colorSelection[] = {0.45,0.8,0.5,0.5};
};

class COMSPEC_SSE_RscCombo: RscCombo {
    idc = -1;
    font = SSE_FONT;
    sizeEx = SSE_TXT_M;
    shadow = 0;
    colorText[] = SSE_C_TEXT;
    colorBackground[] = {0.03,0.04,0.045,0.98};
    colorSelect[] = {1,1,1,1};
    colorSelectBackground[] = {0.22,0.43,0.27,1};
};

class COMSPEC_SSE_RscCheckBox: RscCheckBox {
    idc = -1;
};

class COMSPEC_SSE_RscSlider: RscXSliderH {
    idc = -1;
};

class COMSPEC_SSE_RscControlsGroup: RscControlsGroup {
    idc = -1;
    class controls {};
};
