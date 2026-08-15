#ifndef ICEMAN_AWARE_PHONE_MOD
    #define ICEMAN_AWARE_PHONE_MOD 1134
#endif
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif
#ifndef ICEMAN_AWARE_PHONE_W
    #define ICEMAN_AWARE_PHONE_W (safezoneW * 0.8)
#endif
#ifndef ICEMAN_AWARE_PHONE_H
    #define ICEMAN_AWARE_PHONE_H (ICEMAN_AWARE_PHONE_W * 4/3)
#endif
#ifndef ICEMAN_AWARE_SIZE_H
    #define ICEMAN_AWARE_SIZE_H ((((626) - (60) - (0))) / 2048 * ICEMAN_AWARE_PHONE_H)
#endif
#ifndef ICEMAN_AWARE_POS_H
    #define ICEMAN_AWARE_POS_H (((60)) / 2048 * ICEMAN_AWARE_PHONE_H)
#endif
#ifndef ICEMAN_AWARE_POS_W
    #define ICEMAN_AWARE_POS_W (((ICEMAN_AWARE_SIZE_H * 0.56)/3))
#endif
#ifndef ICEMAN_AWARE_CONTAINER_W
    #define ICEMAN_AWARE_CONTAINER_W(AxisX) AxisX * ICEMAN_AWARE_POS_W
#endif

class ATAK_Message;
class BCE_RscButtonMenu;
class RscStructuredText;
class RscPicture;

class Iceman_ATAK_Aware: ATAK_Message
{
    class controls
    {
        class Title: BCE_RscButtonMenu
        {
            idc = 9200;
            x = 0;
            y = 0;
            w = QUOTE(ICEMAN_AWARE_CONTAINER_W(3));
            h = QUOTE(0.74 * ICEMAN_AWARE_POS_H);
            size = QUOTE(0.64 * ICEMAN_AWARE_POS_H);
            text = "Aware";
            colorBackground[] = {0,0,0,0.55};
            colorBackground2[] = {0,0,0,0.55};
            colorBackgroundFocused[] = {0,0,0,0.8};
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            class Attributes
            {
                align = "center";
                valign = "Bottom";
            };
        };
        class Icon: RscPicture
        {
            idc = 9205;
            x = QUOTE(ICEMAN_AWARE_CONTAINER_W(0.18));
            y = QUOTE(0.94 * ICEMAN_AWARE_POS_H);
            w = QUOTE(ICEMAN_AWARE_CONTAINER_W(0.72));
            h = QUOTE(1.04 * ICEMAN_AWARE_POS_H);
            text = "\ATAK_Aware\data\aware_icon_ca.paa";
            colorText[] = {1,1,1,0.95};
        };
        class Status: RscStructuredText
        {
            idc = 9201;
            x = QUOTE(ICEMAN_AWARE_CONTAINER_W(0.98));
            y = QUOTE(0.96 * ICEMAN_AWARE_POS_H);
            w = QUOTE(ICEMAN_AWARE_CONTAINER_W(1.84));
            h = QUOTE(0.58 * ICEMAN_AWARE_POS_H);
            text = "";
            colorBackground[] = {0,0,0,0.30};
            class Attributes
            {
                font = "RobotoCondensed_BCE";
                color = "#E6EEF0";
                align = "center";
                valign = "middle";
                shadow = 1;
            };
        };
        class ModeIndividual: BCE_RscButtonMenu
        {
            idc = 9210;
            x = QUOTE(ICEMAN_AWARE_CONTAINER_W(0.98));
            y = QUOTE(1.66 * ICEMAN_AWARE_POS_H);
            w = QUOTE(ICEMAN_AWARE_CONTAINER_W(0.88));
            h = QUOTE(0.54 * ICEMAN_AWARE_POS_H);
            size = QUOTE(0.30 * ICEMAN_AWARE_POS_H);
            text = "IND";
            tooltip = "Show individual friendly ATAK users";
            onButtonClick = "['individual'] call Iceman_fnc_aware_setMode";
            colorBackground[] = {0.08,0.12,0.14,0.88};
            colorBackground2[] = {0.08,0.12,0.14,0.88};
            colorBackgroundFocused[] = {0.10,0.42,0.50,0.95};
            class Attributes
            {
                align = "center";
                valign = "middle";
            };
        };
        class ModeStandard: ModeIndividual
        {
            idc = 9211;
            x = QUOTE(ICEMAN_AWARE_CONTAINER_W(1.94));
            w = QUOTE(ICEMAN_AWARE_CONTAINER_W(0.88));
            text = "STD";
            tooltip = "Restore normal cTab/BCE friendly marker detail";
            onButtonClick = "['default'] call Iceman_fnc_aware_setMode";
        };
        class ModeGroup: ModeIndividual
        {
            idc = 9212;
            x = QUOTE(ICEMAN_AWARE_CONTAINER_W(2.24));
            text = "GRP";
            tooltip = "Collapse friendly ATAK users to group markers";
            onButtonClick = "['group'] call Iceman_fnc_aware_setMode";
            show = 0;
        };
        class Detail: RscStructuredText
        {
            idc = 9220;
            x = QUOTE(ICEMAN_AWARE_CONTAINER_W(0.18));
            y = QUOTE(2.54 * ICEMAN_AWARE_POS_H);
            w = QUOTE(ICEMAN_AWARE_CONTAINER_W(2.64));
            h = QUOTE(2.46 * ICEMAN_AWARE_POS_H);
            text = "";
            colorBackground[] = {0,0,0,0.24};
            class Attributes
            {
                font = "RobotoCondensed_BCE";
                color = "#DDE7EA";
                align = "left";
                valign = "top";
                shadow = 1;
            };
        };
        class Summary: Detail
        {
            idc = 9230;
            y = QUOTE(5.20 * ICEMAN_AWARE_POS_H);
            h = QUOTE(1.36 * ICEMAN_AWARE_POS_H);
            colorBackground[] = {0,0,0,0.20};
        };
    };
};
