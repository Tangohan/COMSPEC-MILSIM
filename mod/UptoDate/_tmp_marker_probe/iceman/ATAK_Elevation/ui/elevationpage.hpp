#ifndef ICEMAN_ELEV_PHONE_MOD
    #define ICEMAN_ELEV_PHONE_MOD 1134
#endif
#ifndef ICEMAN_ELEV_PHONE_W
    #define ICEMAN_ELEV_PHONE_W (safezoneW * 0.8)
#endif
#ifndef ICEMAN_ELEV_PHONE_H
    #define ICEMAN_ELEV_PHONE_H (ICEMAN_ELEV_PHONE_W * 4/3)
#endif
#ifndef ICEMAN_ELEV_SIZE_W
    #define ICEMAN_ELEV_SIZE_W ((((ICEMAN_ELEV_PHONE_MOD))) / 2048 * ICEMAN_ELEV_PHONE_W)
#endif
#ifndef ICEMAN_ELEV_SIZE_H
    #define ICEMAN_ELEV_SIZE_H ((((626) - (60) - (0))) / 2048 * ICEMAN_ELEV_PHONE_H)
#endif
#ifndef ICEMAN_ELEV_POS_H
    #define ICEMAN_ELEV_POS_H (((60)) / 2048 * ICEMAN_ELEV_PHONE_H)
#endif
#ifndef ICEMAN_ELEV_POS_W
    #define ICEMAN_ELEV_POS_W ((ICEMAN_ELEV_SIZE_W * 2/5)/3)
#endif
#ifndef ICEMAN_ELEV_CONTAINER_W
    #define ICEMAN_ELEV_CONTAINER_W(AxisX) AxisX * ICEMAN_ELEV_POS_W
#endif

class ATAK_Message;
class BCE_RscButtonMenu;
class RscStructuredText;
class RscEdit;

class Iceman_ATAK_Elevation: ATAK_Message
{
    class controls
    {
        class Title: BCE_RscButtonMenu
        {
            idc = 5;
            x = 0;
            y = 0;
            w = QUOTE(ICEMAN_ELEV_CONTAINER_W(3));
            h = QUOTE(0.8 * ICEMAN_ELEV_POS_H);
            size = QUOTE(0.7 * ICEMAN_ELEV_POS_H);
            text = "Elevation";
            colorBackground[] = {0,0,0,0.5};
            colorBackground2[] = {0,0,0,0.5};
            colorBackgroundFocused[] = {0,0,0,0.8};
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            class Attributes
            {
                align = "center";
                valign = "Bottom";
            };
        };
        class ViewShedTab: BCE_RscButtonMenu
        {
            idc = 101;
            x = QUOTE(ICEMAN_ELEV_CONTAINER_W(0.14));
            y = QUOTE(0.95 * ICEMAN_ELEV_POS_H);
            w = QUOTE(ICEMAN_ELEV_CONTAINER_W(1.28));
            h = QUOTE(0.58 * ICEMAN_ELEV_POS_H);
            size = QUOTE(0.34 * ICEMAN_ELEV_POS_H);
            text = "View Shed";
            onButtonClick = "['viewshed'] call Iceman_fnc_elev_setMode";
            colorBackground[] = {0,0,0,0.45};
            colorBackground2[] = {0,0,0,0.45};
            colorBackgroundFocused[] = {0.05,0.45,0.6,0.75};
            class Attributes
            {
                align = "center";
                valign = "middle";
            };
        };
        class HeatmapTab: BCE_RscButtonMenu
        {
            idc = 102;
            x = QUOTE(ICEMAN_ELEV_CONTAINER_W(1.56));
            y = QUOTE(0.95 * ICEMAN_ELEV_POS_H);
            w = QUOTE(ICEMAN_ELEV_CONTAINER_W(1.28));
            h = QUOTE(0.58 * ICEMAN_ELEV_POS_H);
            size = QUOTE(0.34 * ICEMAN_ELEV_POS_H);
            text = "Heatmap";
            onButtonClick = "['heatmap'] call Iceman_fnc_elev_setMode";
            colorBackground[] = {0,0,0,0.45};
            colorBackground2[] = {0,0,0,0.45};
            colorBackgroundFocused[] = {0.05,0.45,0.6,0.75};
            class Attributes
            {
                align = "center";
                valign = "middle";
            };
        };
        class PointLabel: RscStructuredText
        {
            idc = 120;
            x = QUOTE(ICEMAN_ELEV_CONTAINER_W(0.14));
            y = QUOTE(1.85 * ICEMAN_ELEV_POS_H);
            w = QUOTE(ICEMAN_ELEV_CONTAINER_W(0.95));
            h = QUOTE(0.42 * ICEMAN_ELEV_POS_H);
            size = QUOTE(0.42 * ICEMAN_ELEV_POS_H);
            text = "Point";
            colorBackground[] = {0,0,0,0};
            class Attributes
            {
                font = "RobotoCondensed_BCE";
                color = "#E5E5E5";
                align = "left";
                valign = "middle";
                shadow = 1;
            };
        };
        class PointEdit: RscEdit
        {
            idc = 121;
            x = QUOTE(ICEMAN_ELEV_CONTAINER_W(1.05));
            y = QUOTE(1.75 * ICEMAN_ELEV_POS_H);
            w = QUOTE(ICEMAN_ELEV_CONTAINER_W(1.36));
            h = QUOTE(0.58 * ICEMAN_ELEV_POS_H);
            sizeEx = QUOTE(0.42 * ICEMAN_ELEV_POS_H);
            text = "";
            tooltip = "10 digit grid from map pick";
            colorBackground[] = {0,0,0,0.45};
        };
        class PointPick: BCE_RscButtonMenu
        {
            idc = 126;
            x = QUOTE(ICEMAN_ELEV_CONTAINER_W(2.47));
            y = QUOTE(1.75 * ICEMAN_ELEV_POS_H);
            w = QUOTE(ICEMAN_ELEV_CONTAINER_W(0.36));
            h = QUOTE(0.58 * ICEMAN_ELEV_POS_H);
            size = QUOTE(0.36 * ICEMAN_ELEV_POS_H);
            text = "MAP";
            tooltip = "Pick point on ATAK map";
            onButtonClick = "['active'] call Iceman_fnc_elev_selectPoint";
            colorBackground[] = {0,0,0,0.55};
            colorBackground2[] = {0,0,0,0.55};
            colorBackgroundFocused[] = {0.15,0.55,0.65,0.75};
            class Attributes
            {
                align = "center";
                valign = "middle";
            };
        };
        class Param1Label: PointLabel
        {
            idc = 122;
            y = QUOTE(2.58 * ICEMAN_ELEV_POS_H);
            text = "AGL ft";
        };
        class Param1Edit: PointEdit
        {
            idc = 123;
            y = QUOTE(2.48 * ICEMAN_ELEV_POS_H);
            w = QUOTE(ICEMAN_ELEV_CONTAINER_W(1.78));
            text = "6";
        };
        class Param2Label: PointLabel
        {
            idc = 124;
            y = QUOTE(3.31 * ICEMAN_ELEV_POS_H);
            text = "Radius m";
        };
        class Param2Edit: Param1Edit
        {
            idc = 125;
            y = QUOTE(3.21 * ICEMAN_ELEV_POS_H);
            text = "500";
        };
        class StatusText: PointLabel
        {
            idc = 30;
            x = QUOTE(ICEMAN_ELEV_CONTAINER_W(0.14));
            y = QUOTE(4.12 * ICEMAN_ELEV_POS_H);
            w = QUOTE(ICEMAN_ELEV_CONTAINER_W(2.7));
            h = QUOTE(0.72 * ICEMAN_ELEV_POS_H);
            text = "";
            colorBackground[] = {0,0,0,0.28};
            class Attributes
            {
                align = "center";
                valign = "middle";
            };
        };
        class InfoText: StatusText
        {
            idc = 31;
            y = QUOTE(4.98 * ICEMAN_ELEV_POS_H);
            h = QUOTE(ICEMAN_ELEV_SIZE_H - 6.45 * ICEMAN_ELEV_POS_H);
            colorBackground[] = {0,0,0,0.22};
            class Attributes
            {
                align = "left";
                valign = "top";
            };
        };
    };
};
