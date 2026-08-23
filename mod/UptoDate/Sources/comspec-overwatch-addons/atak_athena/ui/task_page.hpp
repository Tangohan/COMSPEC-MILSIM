// App ATAK « TASK » — ordres C2 Athena (liste + détail + réponses).
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif

#ifndef COMSPEC_TASK_PHONE_W
    #define COMSPEC_TASK_PHONE_W (safezoneW * 0.8)
#endif
#ifndef COMSPEC_TASK_PHONE_H
    #define COMSPEC_TASK_PHONE_H (COMSPEC_TASK_PHONE_W * 4/3)
#endif
#ifndef COMSPEC_TASK_SIZE_H
    #define COMSPEC_TASK_SIZE_H ((((626) - (60) - (0))) / 2048 * COMSPEC_TASK_PHONE_H)
#endif
#ifndef COMSPEC_TASK_POS_H
    #define COMSPEC_TASK_POS_H (((60)) / 2048 * COMSPEC_TASK_PHONE_H)
#endif
#ifndef COMSPEC_TASK_POS_W
    #define COMSPEC_TASK_POS_W (((COMSPEC_TASK_SIZE_H * 0.56)/3))
#endif
#ifndef COMSPEC_TASK_W
    #define COMSPEC_TASK_W(n) ((n) * COMSPEC_TASK_POS_W)
#endif
#ifndef COMSPEC_TASK_H
    #define COMSPEC_TASK_H(n) ((n) * COMSPEC_TASK_POS_H)
#endif

#define TASK_BG_TITLE {0.02, 0.05, 0.07, 0.92}
#define TASK_BG_STRIP {0.03, 0.07, 0.09, 0.88}
#define TASK_BG_BODY {0.02, 0.05, 0.06, 0.9}
#define TASK_BTN {0.08, 0.22, 0.28, 0.95}
#define TASK_BTN_F {0.12, 0.36, 0.42, 1}
#define TASK_OK {0.12, 0.42, 0.28, 0.95}
#define TASK_OK_F {0.18, 0.55, 0.35, 1}
#define TASK_WARN {0.45, 0.22, 0.12, 0.95}
#define TASK_WARN_F {0.58, 0.30, 0.16, 1}
#define TASK_ACCENT {0.95, 0.72, 0.28, 0.95}

class COMSPEC_ATAK_Task: ATAK_Message
{
    class controls
    {
        class Title: BCE_RscButtonMenu
        {
            idc = 9900;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_TASK_W(3));
            h = QUOTE(COMSPEC_TASK_H(0.62));
            size = QUOTE(COMSPEC_TASK_H(0.44));
            text = "TASK — Ordres C2";
            colorBackground[] = TASK_BG_TITLE;
            colorBackground2[] = TASK_BG_TITLE;
            colorBackgroundFocused[] = {0.04, 0.1, 0.12, 0.95};
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            class Attributes { align = "center"; valign = "middle"; };
        };

        class AccentBar: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_TASK_H(0.62));
            w = QUOTE(COMSPEC_TASK_W(3));
            h = QUOTE(COMSPEC_TASK_H(0.06));
            colorBackground[] = TASK_ACCENT;
        };

        class Summary: RscStructuredText
        {
            idc = 9901;
            x = QUOTE(COMSPEC_TASK_W(0.08));
            y = QUOTE(COMSPEC_TASK_H(0.78));
            w = QUOTE(COMSPEC_TASK_W(2.84));
            h = QUOTE(COMSPEC_TASK_H(0.52));
            text = "";
            colorBackground[] = TASK_BG_STRIP;
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#E6EEF0";
                align = "center";
                valign = "middle";
                shadow = 1;
                size = "0.72";
            };
        };

        class SecList: RscStructuredText
        {
            idc = -1;
            x = QUOTE(COMSPEC_TASK_W(0.08));
            y = QUOTE(COMSPEC_TASK_H(1.40));
            w = QUOTE(COMSPEC_TASK_W(2.84));
            h = QUOTE(COMSPEC_TASK_H(0.28));
            text = "<t size='0.62' color='#5a9e88'>ORDRES REÇUS</t>";
            colorBackground[] = {0, 0, 0, 0};
            class Attributes { font = "RobotoCondensed"; align = "left"; valign = "middle"; shadow = 0; };
        };

        class OrderList: RscListBox
        {
            idc = 9902;
            x = QUOTE(COMSPEC_TASK_W(0.08));
            y = QUOTE(COMSPEC_TASK_H(1.72));
            w = QUOTE(COMSPEC_TASK_W(2.84));
            h = QUOTE(COMSPEC_TASK_H(3.40));
            colorBackground[] = {0.025, 0.06, 0.08, 0.9};
            colorSelect[] = {0.02, 0.04, 0.05, 1};
            colorSelect2[] = {0.02, 0.04, 0.05, 1};
            colorSelectBackground[] = {0.55, 0.42, 0.18, 0.88};
            colorSelectBackground2[] = {0.55, 0.42, 0.18, 0.88};
            sizeEx = QUOTE(COMSPEC_TASK_H(0.38));
            rowHeight = QUOTE(COMSPEC_TASK_H(0.58));
            onLBSelChanged = "_this call comspec_overwatch_atak_athena_fnc_athena_taskSelect";
        };

        class Detail: RscStructuredText
        {
            idc = 9903;
            x = QUOTE(COMSPEC_TASK_W(0.08));
            y = QUOTE(COMSPEC_TASK_H(5.24));
            w = QUOTE(COMSPEC_TASK_W(2.84));
            h = QUOTE(COMSPEC_TASK_H(3.20));
            text = "";
            colorBackground[] = TASK_BG_BODY;
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#E6EEF0";
                align = "left";
                valign = "top";
                shadow = 1;
                size = "0.68";
            };
        };

        class BtnAccept: BCE_RscButtonMenu
        {
            idc = 9904;
            x = QUOTE(COMSPEC_TASK_W(0.08));
            y = QUOTE(COMSPEC_TASK_H(8.56));
            w = QUOTE(COMSPEC_TASK_W(1.38));
            h = QUOTE(COMSPEC_TASK_H(0.50));
            size = QUOTE(COMSPEC_TASK_H(0.26));
            text = "Accepter";
            show = 0;
            colorBackground[] = TASK_OK;
            colorBackground2[] = TASK_OK;
            colorBackgroundFocused[] = TASK_OK_F;
            onButtonClick = "['ACCEPT'] call comspec_overwatch_atak_athena_fnc_athena_taskRespond";
            class Attributes { align = "center"; valign = "middle"; };
        };
        class BtnExec: BtnAccept
        {
            idc = 9905;
            text = "En cours";
            colorBackground[] = TASK_BTN;
            colorBackground2[] = TASK_BTN;
            colorBackgroundFocused[] = TASK_BTN_F;
            onButtonClick = "['EXEC'] call comspec_overwatch_atak_athena_fnc_athena_taskRespond";
        };
        class BtnRefuse: BtnAccept
        {
            idc = 9906;
            x = QUOTE(COMSPEC_TASK_W(1.54));
            text = "Refuser";
            colorBackground[] = TASK_WARN;
            colorBackground2[] = TASK_WARN;
            colorBackgroundFocused[] = TASK_WARN_F;
            onButtonClick = "['REFUSE'] call comspec_overwatch_atak_athena_fnc_athena_taskRespond";
        };
        class BtnAbort: BtnRefuse
        {
            idc = 9908;
            text = "Abort";
            onButtonClick = "['ABORT'] call comspec_overwatch_atak_athena_fnc_athena_taskRespond";
        };

        class BtnRefresh: BCE_RscButtonMenu
        {
            idc = 9907;
            x = QUOTE(COMSPEC_TASK_W(0.08));
            y = QUOTE(COMSPEC_TASK_H(9.18));
            w = QUOTE(COMSPEC_TASK_W(2.84));
            h = QUOTE(COMSPEC_TASK_H(0.46));
            size = QUOTE(COMSPEC_TASK_H(0.26));
            text = "Actualiser les ordres";
            colorBackground[] = TASK_BTN;
            colorBackground2[] = TASK_BTN;
            colorBackgroundFocused[] = TASK_BTN_F;
            onButtonClick = "if (!isNil 'comspec_overwatch_connect_fnc_pollOrders') then { [] call comspec_overwatch_connect_fnc_pollOrders; }; [] call comspec_overwatch_atak_athena_fnc_athena_updateTask";
            class Attributes { align = "center"; valign = "middle"; };
        };
    };
};
