#ifndef ICEMAN_ROUTE_PHONE_MOD
    #define ICEMAN_ROUTE_PHONE_MOD 1134
#endif
#ifndef ICEMAN_ROUTE_PHONE_W
    #define ICEMAN_ROUTE_PHONE_W (safezoneW * 0.8)
#endif
#ifndef ICEMAN_ROUTE_PHONE_H
    #define ICEMAN_ROUTE_PHONE_H (ICEMAN_ROUTE_PHONE_W * 4/3)
#endif
#ifndef ICEMAN_ROUTE_SIZE_W
    #define ICEMAN_ROUTE_SIZE_W ((((ICEMAN_ROUTE_PHONE_MOD))) / 2048 * ICEMAN_ROUTE_PHONE_W)
#endif
#ifndef ICEMAN_ROUTE_SIZE_H
    #define ICEMAN_ROUTE_SIZE_H ((((626) - (60) - (0))) / 2048 * ICEMAN_ROUTE_PHONE_H)
#endif
#ifndef ICEMAN_ROUTE_POS_H
    #define ICEMAN_ROUTE_POS_H (((60)) / 2048 * ICEMAN_ROUTE_PHONE_H)
#endif
#ifndef ICEMAN_ROUTE_POS_W
    #define ICEMAN_ROUTE_POS_W ((ICEMAN_ROUTE_SIZE_W * 2/5)/3)
#endif
#ifndef ICEMAN_ROUTE_CONTAINER_W
    #define ICEMAN_ROUTE_CONTAINER_W(AxisX) AxisX * ICEMAN_ROUTE_POS_W
#endif

class ATAK_Message;
class BCE_RscButtonMenu;
class RscStructuredText;
class RscEdit;
class RscCombo;

class Iceman_ATAK_Route: ATAK_Message
{
    class controls
    {
        class Title: BCE_RscButtonMenu
        {
            idc = 5;
            x = 0;
            y = 0;
            w = QUOTE(ICEMAN_ROUTE_CONTAINER_W(3));
            h = QUOTE(0.8 * ICEMAN_ROUTE_POS_H);
            size = QUOTE(0.7 * ICEMAN_ROUTE_POS_H);
            text = "Route";
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
        class RouteTab: BCE_RscButtonMenu
        {
            idc = 110;
            x = QUOTE(ICEMAN_ROUTE_CONTAINER_W(0.14));
            y = QUOTE(0.95 * ICEMAN_ROUTE_POS_H);
            w = QUOTE(ICEMAN_ROUTE_CONTAINER_W(1.28));
            h = QUOTE(0.58 * ICEMAN_ROUTE_POS_H);
            size = QUOTE(0.34 * ICEMAN_ROUTE_POS_H);
            text = "Route";
            onButtonClick = "['route'] call Iceman_fnc_route_selectTab";
            colorBackground[] = {0,0,0,0.45};
            colorBackground2[] = {0,0,0,0.45};
            colorBackgroundFocused[] = {0.05,0.45,0.6,0.75};
            class Attributes
            {
                align = "center";
                valign = "middle";
            };
        };
        class WaypointTab: RouteTab
        {
            idc = 111;
            x = QUOTE(ICEMAN_ROUTE_CONTAINER_W(1.56));
            text = "Waypoints";
            onButtonClick = "['waypoints'] call Iceman_fnc_route_selectTab";
        };
        class StartLabel: RscStructuredText
        {
            idc = 120;
            x = QUOTE(ICEMAN_ROUTE_CONTAINER_W(0.14));
            y = QUOTE(1.85 * ICEMAN_ROUTE_POS_H);
            w = QUOTE(ICEMAN_ROUTE_CONTAINER_W(0.95));
            h = QUOTE(0.42 * ICEMAN_ROUTE_POS_H);
            size = QUOTE(0.42 * ICEMAN_ROUTE_POS_H);
            text = "Start Point";
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
        class StartEdit: RscEdit
        {
            idc = 121;
            x = QUOTE(ICEMAN_ROUTE_CONTAINER_W(1.05));
            y = QUOTE(1.75 * ICEMAN_ROUTE_POS_H);
            w = QUOTE(ICEMAN_ROUTE_CONTAINER_W(1.36));
            h = QUOTE(0.58 * ICEMAN_ROUTE_POS_H);
            sizeEx = QUOTE(0.42 * ICEMAN_ROUTE_POS_H);
            text = "";
            tooltip = "6, 8, or 10 digit grid";
            colorBackground[] = {0.10,0.13,0.15,0.95};
        };
        class StartPick: BCE_RscButtonMenu
        {
            idc = 126;
            x = QUOTE(ICEMAN_ROUTE_CONTAINER_W(2.47));
            y = QUOTE(1.75 * ICEMAN_ROUTE_POS_H);
            w = QUOTE(ICEMAN_ROUTE_CONTAINER_W(0.36));
            h = QUOTE(0.58 * ICEMAN_ROUTE_POS_H);
            size = QUOTE(0.36 * ICEMAN_ROUTE_POS_H);
            text = "MAP";
            tooltip = "Pick start point on ATAK map";
            onButtonClick = "['start'] call Iceman_fnc_route_selectMode";
            colorBackground[] = {0,0,0,0.55};
            colorBackground2[] = {0,0,0,0.55};
            colorBackgroundFocused[] = {0.15,0.55,0.65,0.75};
            class Attributes
            {
                align = "center";
                valign = "middle";
            };
        };
        class EndLabel: StartLabel
        {
            idc = 122;
            y = QUOTE(2.58 * ICEMAN_ROUTE_POS_H);
            text = "End Point";
        };
        class EndEdit: StartEdit
        {
            idc = 123;
            y = QUOTE(2.48 * ICEMAN_ROUTE_POS_H);
        };
        class EndPick: StartPick
        {
            idc = 127;
            y = QUOTE(2.48 * ICEMAN_ROUTE_POS_H);
            tooltip = "Pick end point on ATAK map";
            onButtonClick = "['end'] call Iceman_fnc_route_selectMode";
        };
        class MotLabel: StartLabel
        {
            idc = 124;
            y = QUOTE(3.31 * ICEMAN_ROUTE_POS_H);
            text = "MoT";
        };
        class MotCombo: RscCombo
        {
            idc = 125;
            x = QUOTE(ICEMAN_ROUTE_CONTAINER_W(1.05));
            y = QUOTE(3.21 * ICEMAN_ROUTE_POS_H);
            w = QUOTE(ICEMAN_ROUTE_CONTAINER_W(1.78));
            h = QUOTE(0.58 * ICEMAN_ROUTE_POS_H);
            sizeEx = QUOTE(0.42 * ICEMAN_ROUTE_POS_H);
            colorBackground[] = {0.10,0.13,0.15,0.95};
            onLBSelChanged = "_this call Iceman_fnc_route_setMot";
        };
        class WaypointLabel: StartLabel
        {
            idc = 130;
            y = QUOTE(1.85 * ICEMAN_ROUTE_POS_H);
            text = "Waypoint";
        };
        class WaypointEdit: StartEdit
        {
            idc = 131;
            y = QUOTE(1.75 * ICEMAN_ROUTE_POS_H);
            tooltip = "6, 8, or 10 digit grid";
        };
        class WaypointPick: StartPick
        {
            idc = 132;
            y = QUOTE(1.75 * ICEMAN_ROUTE_POS_H);
            tooltip = "Pick waypoint on ATAK map";
            onButtonClick = "['waypoint'] call Iceman_fnc_route_selectMode";
        };
        class WaypointAdd: RouteTab
        {
            idc = 133;
            x = QUOTE(ICEMAN_ROUTE_CONTAINER_W(0.14));
            y = QUOTE(2.48 * ICEMAN_ROUTE_POS_H);
            w = QUOTE(ICEMAN_ROUTE_CONTAINER_W(1.28));
            text = "Add Grid";
            tooltip = "Add waypoint from grid";
            onButtonClick = "call Iceman_fnc_route_addWaypointFromInput";
        };
        class WaypointUndo: WaypointAdd
        {
            idc = 134;
            x = QUOTE(ICEMAN_ROUTE_CONTAINER_W(1.56));
            w = QUOTE(ICEMAN_ROUTE_CONTAINER_W(0.61));
            text = "Undo";
            tooltip = "Remove last waypoint";
            onButtonClick = "[-1] call Iceman_fnc_route_removeWaypoint";
        };
        class WaypointClear: WaypointUndo
        {
            idc = 135;
            x = QUOTE(ICEMAN_ROUTE_CONTAINER_W(2.22));
            text = "Clear";
            tooltip = "Clear waypoints";
            onButtonClick = "call Iceman_fnc_route_clearWaypoints";
        };
        class StatusText: StartLabel
        {
            idc = 30;
            x = QUOTE(ICEMAN_ROUTE_CONTAINER_W(0.14));
            y = QUOTE(4.12 * ICEMAN_ROUTE_POS_H);
            w = QUOTE(ICEMAN_ROUTE_CONTAINER_W(2.7));
            h = QUOTE(0.72 * ICEMAN_ROUTE_POS_H);
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
            y = QUOTE(4.98 * ICEMAN_ROUTE_POS_H);
            h = QUOTE(ICEMAN_ROUTE_SIZE_H - 6.45 * ICEMAN_ROUTE_POS_H);
            colorBackground[] = {0,0,0,0.22};
            class Attributes
            {
                align = "left";
                valign = "top";
            };
        };
    };
};
