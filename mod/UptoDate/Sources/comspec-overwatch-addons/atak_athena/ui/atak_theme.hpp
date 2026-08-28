// Charte visuelle des apps COMSPEC dans le tiroir ATAK (Iceman / BCE).
// Objectif : panneau type SkyFi / Attack Profile — charbon, tuiles, cyan, GO/critique.
// Ne pas restyler le chrome carte / barre d’outils Iceman.

#ifndef COMSPEC_ATAK_THEME_HPP
#define COMSPEC_ATAK_THEME_HPP

// Fonds
#define ATAK_BG_PANEL {0.071, 0.071, 0.071, 0.98}
#define ATAK_BG_TITLE {0.055, 0.055, 0.055, 1}
#define ATAK_BG_TILE {0.145, 0.145, 0.145, 0.98}
#define ATAK_BG_TILE_F {0.22, 0.22, 0.22, 1}
#define ATAK_BG_STRIP {0.10, 0.10, 0.10, 0.94}
#define ATAK_BG_LIST {0.09, 0.09, 0.09, 0.96}
#define ATAK_BG_DETAIL {0.10, 0.10, 0.10, 0.92}
#define ATAK_BG_EDIT {0.09, 0.09, 0.09, 1}

// Accents
#define ATAK_ACCENT {0.37, 0.78, 0.95, 1}
#define ATAK_TAB_IDLE {0.145, 0.145, 0.145, 1}
#define ATAK_TAB_ACTIVE {0.06, 0.22, 0.12, 1}

// Actions
#define ATAK_BTN ATAK_BG_TILE
#define ATAK_BTN_F ATAK_BG_TILE_F
#define ATAK_GO {0.05, 0.16, 0.09, 0.98}
#define ATAK_GO_F {0.08, 0.26, 0.13, 1}
#define ATAK_WARN {0.18, 0.11, 0.04, 0.98}
#define ATAK_WARN_F {0.28, 0.17, 0.06, 1}
#define ATAK_DANGER {0.18, 0.05, 0.05, 0.98}
#define ATAK_DANGER_F {0.28, 0.08, 0.08, 1}

// Listes
#define ATAK_LIST_TEXT {0.94, 0.95, 0.96, 1}
#define ATAK_LIST_SEL {0.04, 0.05, 0.06, 1}
#define ATAK_LIST_SEL_BG {0.18, 0.42, 0.52, 0.90}

// Bouton tuile : texture blanche opaque × colorBackground.
// Alpha 0 sur animTexture = boutons invisibles (seul le texte restait).
class COMSPEC_ATAK_Btn: BCE_RscButtonMenu
{
    style = 2;
    shadow = 0;
    period = 0;
    periodFocus = 0;
    periodOver = 0;
    colorBackground[] = ATAK_BG_TILE;
    colorBackground2[] = ATAK_BG_TILE_F;
    colorBackgroundFocused[] = ATAK_BG_TILE_F;
    colorBackgroundDisabled[] = {0.08, 0.08, 0.08, 0.55};
    color[] = {1, 1, 1, 1};
    color2[] = {1, 1, 1, 1};
    colorText[] = {1, 1, 1, 1};
    colorFocused[] = {1, 1, 1, 1};
    colorFocusedSecondary[] = {1, 1, 1, 1};
    colorDisabled[] = {0.55, 0.55, 0.55, 1};
    animTextureNormal = "#(argb,8,8,3)color(1,1,1,1)";
    animTextureDisabled = "#(argb,8,8,3)color(1,1,1,0.35)";
    animTextureOver = "#(argb,8,8,3)color(1,1,1,1)";
    animTextureFocused = "#(argb,8,8,3)color(1,1,1,1)";
    animTexturePressed = "#(argb,8,8,3)color(1,1,1,1)";
    animTextureDefault = "#(argb,8,8,3)color(1,1,1,1)";
    size = 0.04;
    class Attributes
    {
        font = "RobotoCondensed";
        color = "#FFFFFF";
        align = "center";
        valign = "middle";
        shadow = "false";
    };
};

class COMSPEC_ATAK_Title: COMSPEC_ATAK_Btn
{
    colorBackground[] = ATAK_BG_TITLE;
    colorBackground2[] = ATAK_BG_TITLE;
    colorBackgroundFocused[] = ATAK_BG_TITLE;
    class Attributes
    {
        font = "RobotoCondensed";
        color = "#FFFFFF";
        align = "left";
        valign = "middle";
        shadow = "false";
    };
};

class COMSPEC_ATAK_BtnGo: COMSPEC_ATAK_Btn
{
    colorBackground[] = ATAK_GO;
    colorBackground2[] = ATAK_GO;
    colorBackgroundFocused[] = ATAK_GO_F;
    class Attributes
    {
        font = "RobotoCondensed";
        color = "#7CFF9A";
        align = "center";
        valign = "middle";
        shadow = "false";
    };
};

class COMSPEC_ATAK_BtnDanger: COMSPEC_ATAK_Btn
{
    colorBackground[] = ATAK_DANGER;
    colorBackground2[] = ATAK_DANGER;
    colorBackgroundFocused[] = ATAK_DANGER_F;
    class Attributes
    {
        font = "RobotoCondensed";
        color = "#FF8A7A";
        align = "center";
        valign = "middle";
        shadow = "false";
    };
};

class COMSPEC_ATAK_BtnWarn: COMSPEC_ATAK_Btn
{
    colorBackground[] = ATAK_WARN;
    colorBackground2[] = ATAK_WARN;
    colorBackgroundFocused[] = ATAK_WARN_F;
    class Attributes
    {
        font = "RobotoCondensed";
        color = "#FFD080";
        align = "center";
        valign = "middle";
        shadow = "false";
    };
};

#endif
