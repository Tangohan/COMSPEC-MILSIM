#ifndef ICEMAN_DRONE_PHONE_MOD
    #define ICEMAN_DRONE_PHONE_MOD 1134
#endif
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif
#ifndef ICEMAN_DRONE_PHONE_W
    #define ICEMAN_DRONE_PHONE_W (safezoneW * 0.8)
#endif
#ifndef ICEMAN_DRONE_PHONE_H
    #define ICEMAN_DRONE_PHONE_H (ICEMAN_DRONE_PHONE_W * 4/3)
#endif
#ifndef ICEMAN_DRONE_SIZE_H
    #define ICEMAN_DRONE_SIZE_H ((((626) - (60) - (0))) / 2048 * ICEMAN_DRONE_PHONE_H)
#endif
#ifndef ICEMAN_DRONE_POS_H
    #define ICEMAN_DRONE_POS_H (((60)) / 2048 * ICEMAN_DRONE_PHONE_H)
#endif
#ifndef ICEMAN_DRONE_POS_W
    #define ICEMAN_DRONE_POS_W (((ICEMAN_DRONE_SIZE_H * 0.56)/3))
#endif
#ifndef ICEMAN_DRONE_CONTAINER_W
    #define ICEMAN_DRONE_CONTAINER_W(AxisX) AxisX * ICEMAN_DRONE_POS_W
#endif

class ATAK_Message;
class BCE_RscButtonMenu;
class RscStructuredText;
class RscEdit;
class RscCombo;

class Iceman_ATAK_DroneOps: ATAK_Message
{
    class controls
    {
        class Title: BCE_RscButtonMenu
        {
            idc = 8800;
            x = 0;
            y = 0;
            w = QUOTE(ICEMAN_DRONE_CONTAINER_W(3));
            h = QUOTE(0.8 * ICEMAN_DRONE_POS_H);
            size = QUOTE(0.7 * ICEMAN_DRONE_POS_H);
            text = "Drone Ops";
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
        class DroneInfo: RscStructuredText
        {
            idc = 8801;
            x = QUOTE(ICEMAN_DRONE_CONTAINER_W(0.14));
            y = QUOTE(0.95 * ICEMAN_DRONE_POS_H);
            w = QUOTE(ICEMAN_DRONE_CONTAINER_W(2.7));
            h = QUOTE(0.76 * ICEMAN_DRONE_POS_H);
            text = "";
            colorBackground[] = {0,0,0,0.28};
            class Attributes
            {
                font = "RobotoCondensed_BCE";
                color = "#E5E5E5";
                align = "center";
                valign = "middle";
                shadow = 1;
            };
        };
        class TargetLabel: DroneInfo
        {
            idc = 8810;
            x = QUOTE(ICEMAN_DRONE_CONTAINER_W(0.14));
            y = QUOTE(1.9 * ICEMAN_DRONE_POS_H);
            w = QUOTE(ICEMAN_DRONE_CONTAINER_W(0.86));
            h = QUOTE(0.48 * ICEMAN_DRONE_POS_H);
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
        class TargetEdit: RscEdit
        {
            idc = 8811;
            x = QUOTE(ICEMAN_DRONE_CONTAINER_W(0.92));
            y = QUOTE(1.82 * ICEMAN_DRONE_POS_H);
            w = QUOTE(ICEMAN_DRONE_CONTAINER_W(1.45));
            h = QUOTE(0.58 * ICEMAN_DRONE_POS_H);
            sizeEx = QUOTE(0.42 * ICEMAN_DRONE_POS_H);
            text = "";
            tooltip = "6, 8, or 10 digit grid";
            colorBackground[] = {0.10,0.13,0.15,0.95};
        };
        class TargetPick: BCE_RscButtonMenu
        {
            idc = 8812;
            x = QUOTE(ICEMAN_DRONE_CONTAINER_W(2.43));
            y = QUOTE(1.82 * ICEMAN_DRONE_POS_H);
            w = QUOTE(ICEMAN_DRONE_CONTAINER_W(0.41));
            h = QUOTE(0.58 * ICEMAN_DRONE_POS_H);
            size = QUOTE(0.36 * ICEMAN_DRONE_POS_H);
            text = "MAP";
            tooltip = "Pick a point on the ATAK map";
            onButtonClick = "call Iceman_fnc_drone_selectTarget";
            colorBackground[] = {0,0,0,0.55};
            colorBackground2[] = {0,0,0,0.55};
            colorBackgroundFocused[] = {0.15,0.55,0.65,0.75};
            class Attributes
            {
                align = "center";
                valign = "middle";
            };
        };
        class AltitudeLabel: TargetLabel
        {
            idc = 8820;
            y = QUOTE(2.58 * ICEMAN_DRONE_POS_H);
            text = "Alt";
        };
        class AltitudeEdit: TargetEdit
        {
            idc = 8821;
            y = QUOTE(2.50 * ICEMAN_DRONE_POS_H);
            w = QUOTE(ICEMAN_DRONE_CONTAINER_W(1.92));
            tooltip = "Altitude AGL in meters";
            text = "60";
        };
        class FunctionLabel: TargetLabel
        {
            idc = 8830;
            y = QUOTE(3.26 * ICEMAN_DRONE_POS_H);
            text = "Function";
        };
        class FunctionCombo: RscCombo
        {
            idc = 8831;
            x = QUOTE(ICEMAN_DRONE_CONTAINER_W(0.92));
            y = QUOTE(3.18 * ICEMAN_DRONE_POS_H);
            w = QUOTE(ICEMAN_DRONE_CONTAINER_W(1.92));
            h = QUOTE(0.58 * ICEMAN_DRONE_POS_H);
            sizeEx = QUOTE(0.42 * ICEMAN_DRONE_POS_H);
            colorBackground[] = {0.10,0.13,0.15,0.95};
        };
        class RadiusLabel: TargetLabel
        {
            idc = 8840;
            y = QUOTE(3.94 * ICEMAN_DRONE_POS_H);
            text = "Radius";
        };
        class RadiusEdit: AltitudeEdit
        {
            idc = 8841;
            y = QUOTE(3.86 * ICEMAN_DRONE_POS_H);
            tooltip = "Scan or loiter radius in meters";
            text = "150";
        };
        class ConnectButton: TargetPick
        {
            idc = 8851;
            x = QUOTE(ICEMAN_DRONE_CONTAINER_W(0.14));
            y = QUOTE(4.62 * ICEMAN_DRONE_POS_H);
            w = QUOTE(ICEMAN_DRONE_CONTAINER_W(0.84));
            text = "Connect";
            tooltip = "Connect nearest supported drone";
            onButtonClick = "[objNull] call Iceman_fnc_drone_connect";
        };
        class SendButton: ConnectButton
        {
            idc = 8852;
            x = QUOTE(ICEMAN_DRONE_CONTAINER_W(1.08));
            text = "Send";
            tooltip = "Send the selected task";
            onButtonClick = "call Iceman_fnc_drone_sendTask";
        };
        class ClearButton: ConnectButton
        {
            idc = 8853;
            x = QUOTE(ICEMAN_DRONE_CONTAINER_W(2.02));
            text = "Clear";
            tooltip = "Clear the selected point";
            onButtonClick = "(call Iceman_fnc_drone_getState) set ['target', []]; (call Iceman_fnc_drone_getState) set ['selectMode', '']; call Iceman_fnc_drone_updatePanel";
        };
        class StatusText: DroneInfo
        {
            idc = 8860;
            x = QUOTE(ICEMAN_DRONE_CONTAINER_W(0.14));
            y = QUOTE(5.38 * ICEMAN_DRONE_POS_H);
            h = QUOTE(0.72 * ICEMAN_DRONE_POS_H);
            colorBackground[] = {0,0,0,0.28};
        };
        class InfoText: StatusText
        {
            idc = 8861;
            y = QUOTE(6.22 * ICEMAN_DRONE_POS_H);
            h = QUOTE(ICEMAN_DRONE_SIZE_H - 6.72 * ICEMAN_DRONE_POS_H);
            colorBackground[] = {0,0,0,0.22};
            class Attributes
            {
                font = "RobotoCondensed_BCE";
                color = "#DDE7EA";
                align = "left";
                valign = "top";
                shadow = 1;
            };
        };
    };
};
