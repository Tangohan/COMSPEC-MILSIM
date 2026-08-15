#ifndef ICEMAN_WR_PHONE_MOD
    #define ICEMAN_WR_PHONE_MOD 1134
#endif
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif
#ifndef ICEMAN_WR_PHONE_W
    #define ICEMAN_WR_PHONE_W (safezoneW * 0.8)
#endif
#ifndef ICEMAN_WR_PHONE_H
    #define ICEMAN_WR_PHONE_H (ICEMAN_WR_PHONE_W * 4/3)
#endif
#ifndef ICEMAN_WR_SIZE_H
    #define ICEMAN_WR_SIZE_H ((((626) - (60) - (0))) / 2048 * ICEMAN_WR_PHONE_H)
#endif
#ifndef ICEMAN_WR_POS_H
    #define ICEMAN_WR_POS_H (((60)) / 2048 * ICEMAN_WR_PHONE_H)
#endif
#ifndef ICEMAN_WR_POS_W
    #define ICEMAN_WR_POS_W (((ICEMAN_WR_SIZE_H * 0.56)/3))
#endif
#ifndef ICEMAN_WR_CONTAINER_W
    #define ICEMAN_WR_CONTAINER_W(AxisX) AxisX * ICEMAN_WR_POS_W
#endif

class ATAK_Message;
class BCE_RscButtonMenu;
class RscStructuredText;
class RscListBox;
class RscEdit;

class Iceman_ATAK_WaveRelay: ATAK_Message
{
    class controls
    {
        class Title: BCE_RscButtonMenu
        {
            idc = 9000;
            x = 0;
            y = 0;
            w = QUOTE(ICEMAN_WR_CONTAINER_W(3));
            h = QUOTE(0.70 * ICEMAN_WR_POS_H);
            size = QUOTE(0.62 * ICEMAN_WR_POS_H);
            text = "Wave Relay";
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
        class Status: RscStructuredText
        {
            idc = 9001;
            x = QUOTE(ICEMAN_WR_CONTAINER_W(0.08));
            y = QUOTE(0.78 * ICEMAN_WR_POS_H);
            w = QUOTE(ICEMAN_WR_CONTAINER_W(2.84));
            h = QUOTE(0.58 * ICEMAN_WR_POS_H);
            text = "";
            colorBackground[] = {0,0,0,0.26};
            class Attributes
            {
                font = "RobotoCondensed_BCE";
                color = "#E6EEF0";
                align = "center";
                valign = "middle";
                shadow = 1;
            };
        };
        class TabHome: BCE_RscButtonMenu
        {
            idc = 9010;
            x = QUOTE(ICEMAN_WR_CONTAINER_W(0.08));
            y = QUOTE(2.08 * ICEMAN_WR_POS_H);
            w = QUOTE(ICEMAN_WR_CONTAINER_W(0.46));
            h = QUOTE(0.42 * ICEMAN_WR_POS_H);
            size = QUOTE(0.30 * ICEMAN_WR_POS_H);
            text = "NET";
            tooltip = "Network status";
            onButtonClick = "['home'] call Iceman_fnc_wr_selectTab";
            colorBackground[] = {0.08,0.12,0.14,0.85};
            colorBackground2[] = {0.08,0.12,0.14,0.85};
            colorBackgroundFocused[] = {0.10,0.38,0.45,0.95};
            class Attributes
            {
                align = "center";
                valign = "middle";
            };
        };
        class TabTalkgroups: TabHome
        {
            idc = 9011;
            x = QUOTE(ICEMAN_WR_CONTAINER_W(0.56));
            text = "TG";
            tooltip = "Talkgroups";
            onButtonClick = "['talkgroups'] call Iceman_fnc_wr_selectTab";
        };
        class TabFeeds: TabHome
        {
            idc = 9012;
            x = QUOTE(ICEMAN_WR_CONTAINER_W(1.04));
            text = "VID";
            tooltip = "Video subscriptions";
            onButtonClick = "['feeds'] call Iceman_fnc_wr_selectTab";
        };
        class TabGateways: TabHome
        {
            idc = 9013;
            x = QUOTE(ICEMAN_WR_CONTAINER_W(1.52));
            text = "GW";
            tooltip = "Radio/IP gateways";
            onButtonClick = "['gateways'] call Iceman_fnc_wr_selectTab";
        };
        class TabPLI: TabHome
        {
            idc = 9014;
            x = QUOTE(ICEMAN_WR_CONTAINER_W(2.00));
            text = "PLI";
            tooltip = "Position location information";
            onButtonClick = "['pli'] call Iceman_fnc_wr_selectTab";
        };
        class TabDiag: TabHome
        {
            idc = 9015;
            x = QUOTE(ICEMAN_WR_CONTAINER_W(2.48));
            text = "CFG";
            tooltip = "Profiles and setup";
            onButtonClick = "['diag'] call Iceman_fnc_wr_selectTab";
        };
        class MainList: RscListBox
        {
            idc = 9020;
            x = QUOTE(ICEMAN_WR_CONTAINER_W(0.08));
            y = QUOTE(2.60 * ICEMAN_WR_POS_H);
            w = QUOTE(ICEMAN_WR_CONTAINER_W(2.84));
            h = QUOTE(2.10 * ICEMAN_WR_POS_H);
            sizeEx = QUOTE(0.31 * ICEMAN_WR_POS_H);
            rowHeight = QUOTE(0.39 * ICEMAN_WR_POS_H);
            colorBackground[] = {0.03,0.06,0.07,0.82};
            colorSelect[] = {0,0,0,1};
            colorSelect2[] = {0,0,0,1};
            colorSelectBackground[] = {0.62,0.80,0.86,0.95};
            colorSelectBackground2[] = {0.62,0.80,0.86,0.95};
            onLBSelChanged = "_this call Iceman_fnc_wr_onListSelect";
        };
        class Detail: RscStructuredText
        {
            idc = 9021;
            x = QUOTE(ICEMAN_WR_CONTAINER_W(0.08));
            y = QUOTE(4.82 * ICEMAN_WR_POS_H);
            w = QUOTE(ICEMAN_WR_CONTAINER_W(2.84));
            h = QUOTE(ICEMAN_WR_SIZE_H - 7.48 * ICEMAN_WR_POS_H);
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
        class ActionOne: TabHome
        {
            idc = 9030;
            x = QUOTE(ICEMAN_WR_CONTAINER_W(0.08));
            y = QUOTE(ICEMAN_WR_SIZE_H - 1.88 * ICEMAN_WR_POS_H);
            w = QUOTE(ICEMAN_WR_CONTAINER_W(0.67));
            h = QUOTE(0.58 * ICEMAN_WR_POS_H);
            size = QUOTE(0.32 * ICEMAN_WR_POS_H);
            text = "Refresh";
            tooltip = "";
            onButtonClick = "[0] call Iceman_fnc_wr_action";
        };
        class ActionTwo: ActionOne
        {
            idc = 9031;
            x = QUOTE(ICEMAN_WR_CONTAINER_W(0.80));
            text = "Gateway";
            onButtonClick = "[1] call Iceman_fnc_wr_action";
        };
        class ActionThree: ActionOne
        {
            idc = 9032;
            x = QUOTE(ICEMAN_WR_CONTAINER_W(1.52));
            text = "Locate";
            onButtonClick = "[2] call Iceman_fnc_wr_action";
        };
        class ActionFour: ActionOne
        {
            idc = 9033;
            x = QUOTE(ICEMAN_WR_CONTAINER_W(2.24));
            text = "";
            onButtonClick = "[3] call Iceman_fnc_wr_action";
        };
        class ActionFive: ActionOne
        {
            idc = 9034;
            x = QUOTE(ICEMAN_WR_CONTAINER_W(0.08));
            y = QUOTE(ICEMAN_WR_SIZE_H - 2.50 * ICEMAN_WR_POS_H);
            text = "";
            onButtonClick = "[4] call Iceman_fnc_wr_action";
        };
        class ActionSix: ActionFive
        {
            idc = 9035;
            x = QUOTE(ICEMAN_WR_CONTAINER_W(0.80));
            text = "";
            onButtonClick = "[5] call Iceman_fnc_wr_action";
        };
        class FreqLabel: Status
        {
            idc = 9043;
            x = QUOTE(ICEMAN_WR_CONTAINER_W(0.08));
            y = QUOTE(1.46 * ICEMAN_WR_POS_H);
            w = QUOTE(ICEMAN_WR_CONTAINER_W(0.34));
            h = QUOTE(0.42 * ICEMAN_WR_POS_H);
            text = "F";
        };
        class FreqEdit: RscEdit
        {
            idc = 9040;
            x = QUOTE(ICEMAN_WR_CONTAINER_W(0.44));
            y = QUOTE(1.46 * ICEMAN_WR_POS_H);
            w = QUOTE(ICEMAN_WR_CONTAINER_W(0.76));
            h = QUOTE(0.42 * ICEMAN_WR_POS_H);
            sizeEx = QUOTE(0.30 * ICEMAN_WR_POS_H);
            text = "32.0";
            tooltip = "Wave Relay frequency bank";
            colorBackground[] = {0.03,0.06,0.07,0.95};
        };
        class ProfileLabel: FreqLabel
        {
            idc = 9044;
            x = QUOTE(ICEMAN_WR_CONTAINER_W(1.24));
            text = "P";
        };
        class ProfileEdit: FreqEdit
        {
            idc = 9041;
            x = QUOTE(ICEMAN_WR_CONTAINER_W(1.60));
            w = QUOTE(ICEMAN_WR_CONTAINER_W(1.32));
            text = "Default";
            tooltip = "Profile name for save/load";
        };
    };
};
