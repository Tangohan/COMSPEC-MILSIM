#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif

#ifndef COMSPEC_ATHENA_PHONE_W
    #define COMSPEC_ATHENA_PHONE_W (safezoneW * 0.8)
#endif
#ifndef COMSPEC_ATHENA_PHONE_H
    #define COMSPEC_ATHENA_PHONE_H (COMSPEC_ATHENA_PHONE_W * 4/3)
#endif
#ifndef COMSPEC_ATHENA_ROW_H
    #define COMSPEC_ATHENA_ROW_H ((((626) - (60) - (0))) / 2048 * COMSPEC_ATHENA_PHONE_H * 0.55)
#endif
#ifndef COMSPEC_ATHENA_COL_W
    #define COMSPEC_ATHENA_COL_W ((((626) - (60) - (0))) / 2048 * COMSPEC_ATHENA_PHONE_H * 0.32)
#endif
#ifndef COMSPEC_ATHENA_W
    #define COMSPEC_ATHENA_W(n) ((n) * COMSPEC_ATHENA_COL_W)
#endif
#ifndef COMSPEC_ATHENA_H
    #define COMSPEC_ATHENA_H(n) ((n) * COMSPEC_ATHENA_ROW_H)
#endif

class ATAK_Message;
class BCE_RscButtonMenu;
class RscStructuredText;
class RscListBox;

class COMSPEC_ATAK_Athena: ATAK_Message
{
    class controls
    {
        class Title: BCE_RscButtonMenu
        {
            idc = 9700;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_ATHENA_W(3));
            h = QUOTE(COMSPEC_ATHENA_H(0.55));
            size = QUOTE(COMSPEC_ATHENA_H(0.48));
            text = "Athena";
            colorBackground[] = {0, 0, 0, 0.55};
            colorBackground2[] = {0, 0, 0, 0.55};
            colorBackgroundFocused[] = {0, 0, 0, 0.8};
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            class Attributes { align = "center"; valign = "Bottom"; };
        };

        class Status: RscStructuredText
        {
            idc = 9701;
            x = QUOTE(COMSPEC_ATHENA_W(0.08));
            y = QUOTE(COMSPEC_ATHENA_H(0.58));
            w = QUOTE(COMSPEC_ATHENA_W(2.84));
            h = QUOTE(COMSPEC_ATHENA_H(0.48));
            text = "";
            colorBackground[] = {0, 0, 0, 0.24};
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#E6EEF0";
                align = "center";
                valign = "middle";
                shadow = 1;
                size = "0.8";
            };
        };

        class TabAll: BCE_RscButtonMenu
        {
            idc = 9740;
            x = QUOTE(COMSPEC_ATHENA_W(0.08));
            y = QUOTE(COMSPEC_ATHENA_H(1.1));
            w = QUOTE(COMSPEC_ATHENA_W(0.7));
            h = QUOTE(COMSPEC_ATHENA_H(0.42));
            text = "Tout";
            colorBackground[] = {0.18, 0.22, 0.28, 0.9};
            onButtonClick = "['all'] call comspec_overwatch_atak_athena_fnc_athena_selectTab";
            class Attributes { align = "center"; valign = "middle"; };
        };
        class TabBda: BCE_RscButtonMenu
        {
            idc = 9741;
            x = QUOTE(COMSPEC_ATHENA_W(0.82));
            y = QUOTE(COMSPEC_ATHENA_H(1.1));
            w = QUOTE(COMSPEC_ATHENA_W(0.7));
            h = QUOTE(COMSPEC_ATHENA_H(0.42));
            text = "BDA";
            colorBackground[] = {0.4, 0.22, 0.1, 0.9};
            onButtonClick = "['bda'] call comspec_overwatch_atak_athena_fnc_athena_selectTab";
            class Attributes { align = "center"; valign = "middle"; };
        };
        class TabPhoto: BCE_RscButtonMenu
        {
            idc = 9742;
            x = QUOTE(COMSPEC_ATHENA_W(1.56));
            y = QUOTE(COMSPEC_ATHENA_H(1.1));
            w = QUOTE(COMSPEC_ATHENA_W(0.7));
            h = QUOTE(COMSPEC_ATHENA_H(0.42));
            text = "Photos";
            colorBackground[] = {0.15, 0.3, 0.45, 0.9};
            onButtonClick = "['photo'] call comspec_overwatch_atak_athena_fnc_athena_selectTab";
            class Attributes { align = "center"; valign = "middle"; };
        };
        class TabOrder: BCE_RscButtonMenu
        {
            idc = 9743;
            x = QUOTE(COMSPEC_ATHENA_W(2.3));
            y = QUOTE(COMSPEC_ATHENA_H(1.1));
            w = QUOTE(COMSPEC_ATHENA_W(0.62));
            h = QUOTE(COMSPEC_ATHENA_H(0.42));
            text = "Ordres";
            colorBackground[] = {0.12, 0.28, 0.4, 0.9};
            onButtonClick = "['order'] call comspec_overwatch_atak_athena_fnc_athena_selectTab";
            class Attributes { align = "center"; valign = "middle"; };
        };

        class Inbox: RscListBox
        {
            idc = 9710;
            x = QUOTE(COMSPEC_ATHENA_W(0.08));
            y = QUOTE(COMSPEC_ATHENA_H(1.58));
            w = QUOTE(COMSPEC_ATHENA_W(2.84));
            h = QUOTE(COMSPEC_ATHENA_H(2.05));
            colorBackground[] = {0, 0, 0, 0.35};
            sizeEx = 0.026;
            onLBSelChanged = "_this call comspec_overwatch_atak_athena_fnc_athena_selectInbox";
        };

        class Detail: RscStructuredText
        {
            idc = 9711;
            x = QUOTE(COMSPEC_ATHENA_W(0.08));
            y = QUOTE(COMSPEC_ATHENA_H(3.7));
            w = QUOTE(COMSPEC_ATHENA_W(2.84));
            h = QUOTE(COMSPEC_ATHENA_H(1.15));
            text = "";
            colorBackground[] = {0, 0, 0, 0.28};
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#DDE6EA";
                align = "left";
                valign = "top";
                shadow = 1;
                size = "0.8";
            };
        };

        class BtnTic: BCE_RscButtonMenu
        {
            idc = 9720;
            x = QUOTE(COMSPEC_ATHENA_W(0.08));
            y = QUOTE(COMSPEC_ATHENA_H(4.95));
            w = QUOTE(COMSPEC_ATHENA_W(1.38));
            h = QUOTE(COMSPEC_ATHENA_H(0.48));
            text = "Contact";
            colorBackground[] = {0.55, 0.18, 0.12, 0.85};
            onButtonClick = "['TIC'] call comspec_overwatch_atak_athena_fnc_athena_sendQuick";
            class Attributes { align = "center"; valign = "middle"; };
        };
        class BtnFrago: BCE_RscButtonMenu
        {
            idc = 9721;
            x = QUOTE(COMSPEC_ATHENA_W(1.54));
            y = QUOTE(COMSPEC_ATHENA_H(4.95));
            w = QUOTE(COMSPEC_ATHENA_W(1.38));
            h = QUOTE(COMSPEC_ATHENA_H(0.48));
            text = "FRAGO";
            colorBackground[] = {0.2, 0.35, 0.45, 0.85};
            onButtonClick = "['FRAGO'] call comspec_overwatch_atak_athena_fnc_athena_sendQuick";
            class Attributes { align = "center"; valign = "middle"; };
        };
        class BtnSalute: BCE_RscButtonMenu
        {
            idc = 9722;
            x = QUOTE(COMSPEC_ATHENA_W(0.08));
            y = QUOTE(COMSPEC_ATHENA_H(5.48));
            w = QUOTE(COMSPEC_ATHENA_W(1.38));
            h = QUOTE(COMSPEC_ATHENA_H(0.48));
            text = "SALUTE";
            colorBackground[] = {0.25, 0.4, 0.28, 0.85};
            onButtonClick = "['SALUTE'] call comspec_overwatch_atak_athena_fnc_athena_sendQuick";
            class Attributes { align = "center"; valign = "middle"; };
        };
        class BtnEagle: BCE_RscButtonMenu
        {
            idc = 9723;
            x = QUOTE(COMSPEC_ATHENA_W(1.54));
            y = QUOTE(COMSPEC_ATHENA_H(5.48));
            w = QUOTE(COMSPEC_ATHENA_W(1.38));
            h = QUOTE(COMSPEC_ATHENA_H(0.48));
            text = "Op. à terre";
            colorBackground[] = {0.65, 0.12, 0.12, 0.9};
            onButtonClick = "['EAGLE_DOWN'] call comspec_overwatch_atak_athena_fnc_athena_sendQuick";
            class Attributes { align = "center"; valign = "middle"; };
        };

        class BtnPhoto: BCE_RscButtonMenu
        {
            idc = 9732;
            x = QUOTE(COMSPEC_ATHENA_W(0.08));
            y = QUOTE(COMSPEC_ATHENA_H(6.02));
            w = QUOTE(COMSPEC_ATHENA_W(1.38));
            h = QUOTE(COMSPEC_ATHENA_H(0.48));
            text = "Photo → Athena";
            colorBackground[] = {0.15, 0.35, 0.55, 0.9};
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_sendPhoto";
            class Attributes { align = "center"; valign = "middle"; };
        };
        class BtnRefresh: BCE_RscButtonMenu
        {
            idc = 9731;
            x = QUOTE(COMSPEC_ATHENA_W(1.54));
            y = QUOTE(COMSPEC_ATHENA_H(6.02));
            w = QUOTE(COMSPEC_ATHENA_W(1.38));
            h = QUOTE(COMSPEC_ATHENA_H(0.48));
            text = "Actualiser";
            colorBackground[] = {0.15, 0.15, 0.18, 0.85};
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_refresh";
            class Attributes { align = "center"; valign = "middle"; };
        };

        class BtnTablet: BCE_RscButtonMenu
        {
            idc = 9730;
            x = QUOTE(COMSPEC_ATHENA_W(0.08));
            y = QUOTE(COMSPEC_ATHENA_H(6.55));
            w = QUOTE(COMSPEC_ATHENA_W(2.84));
            h = QUOTE(COMSPEC_ATHENA_H(0.48));
            text = "Tablette complète Athena";
            colorBackground[] = {0.12, 0.45, 0.35, 0.9};
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_openTablet";
            class Attributes { align = "center"; valign = "middle"; };
        };
    };
};
