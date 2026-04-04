// COMSPEC CAS 9-Line dialog (idd 9980)
class RscText;
class RscButton;
class RscStructuredText;
class RscEdit;

class COMSPEC_CAS_Dialog {
    idd = 9980;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_CAS_Display', _this select 0]; (_this select 0) displayCtrl 8001 ctrlSetText 'CAS 9-Line';";
    onUnload = "uiNamespace setVariable ['COMSPEC_CAS_Display', displayNull];";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.2 * safezoneW + safezoneX;
            y = 0.15 * safezoneH + safezoneY;
            w = 0.6 * safezoneW;
            h = 0.7 * safezoneH;
            colorBackground[] = {0.02, 0.05, 0.1, 0.92};
        };
        class Title: RscStructuredText {
            idc = 8001;
            text = "<t font='RobotoCondensedBold' size='0.9'>CAS 9-Line</t>";
            x = 0.21 * safezoneW + safezoneX;
            y = 0.16 * safezoneH + safezoneY;
            w = 0.58 * safezoneW;
            h = 0.04 * safezoneH;
        };
        class AssignedAircraft: RscText {
            idc = 8002;
            text = "Assigned: —";
            x = 0.21 * safezoneW + safezoneX;
            y = 0.205 * safezoneH + safezoneY;
            w = 0.3 * safezoneW;
            h = 0.025 * safezoneH;
            sizeEx = 0.032;
            colorText[] = {0.7, 0.85, 1, 1};
        };
        class StatusLabel: RscText {
            idc = 8003;
            text = "Status: —";
            x = 0.52 * safezoneW + safezoneX;
            y = 0.205 * safezoneH + safezoneY;
            w = 0.27 * safezoneW;
            h = 0.025 * safezoneH;
            sizeEx = 0.032;
            colorText[] = {0.9, 0.8, 0.4, 1};
        };
        class LinesLabel: RscText {
            idc = -1;
            text = "9-Line:";
            x = 0.21 * safezoneW + safezoneX;
            y = 0.24 * safezoneH + safezoneY;
            w = 0.58 * safezoneW;
            h = 0.02 * safezoneH;
            sizeEx = 0.028;
        };
        class LinesContent: RscStructuredText {
            idc = 8010;
            text = "";
            x = 0.21 * safezoneW + safezoneX;
            y = 0.265 * safezoneH + safezoneY;
            w = 0.58 * safezoneW;
            h = 0.35 * safezoneH;
            sizeEx = 0.03;
            colorText[] = {0.9, 0.9, 0.9, 1};
            colorBackground[] = {0, 0, 0, 0.4};
        };
        class LaserLabel: RscText {
            idc = 8011;
            text = "Laser: —";
            x = 0.21 * safezoneW + safezoneX;
            y = 0.62 * safezoneH + safezoneY;
            w = 0.58 * safezoneW;
            h = 0.02 * safezoneH;
            sizeEx = 0.028;
        };
        class BtnRoger: RscButton {
            idc = 8020;
            text = "Roger";
            x = 0.21 * safezoneW + safezoneX;
            y = 0.66 * safezoneH + safezoneY;
            w = 0.12 * safezoneW;
            h = 0.04 * safezoneH;
            action = "[] call comspec_overwatch_connect_fnc_sendCASAck;";
        };
        class BtnTargetAcquired: RscButton {
            idc = 8021;
            text = "Target Acquired";
            x = 0.34 * safezoneW + safezoneX;
            y = 0.66 * safezoneH + safezoneY;
            w = 0.12 * safezoneW;
            h = 0.04 * safezoneH;
            action = "['TARGET_ACQUIRED'] call comspec_overwatch_connect_fnc_sendCASStatus;";
        };
        class BtnInbound: RscButton {
            idc = 8022;
            text = "Inbound";
            x = 0.47 * safezoneW + safezoneX;
            y = 0.66 * safezoneH + safezoneY;
            w = 0.1 * safezoneW;
            h = 0.04 * safezoneH;
            action = "['INBOUND'] call comspec_overwatch_connect_fnc_sendCASStatus;";
        };
        class BtnEngaged: RscButton {
            idc = 8023;
            text = "Engaged";
            x = 0.58 * safezoneW + safezoneX;
            y = 0.66 * safezoneH + safezoneY;
            w = 0.1 * safezoneW;
            h = 0.04 * safezoneH;
            action = "['ENGAGED'] call comspec_overwatch_connect_fnc_sendCASStatus;";
        };
        class BtnAbort: RscButton {
            idc = 8024;
            text = "Abort";
            x = 0.69 * safezoneW + safezoneX;
            y = 0.66 * safezoneH + safezoneY;
            w = 0.1 * safezoneW;
            h = 0.04 * safezoneH;
            colorBackground[] = {0.5, 0.1, 0.1, 0.9};
            action = "['ABORTED'] call comspec_overwatch_connect_fnc_sendCASStatus;";
        };
    };
};
