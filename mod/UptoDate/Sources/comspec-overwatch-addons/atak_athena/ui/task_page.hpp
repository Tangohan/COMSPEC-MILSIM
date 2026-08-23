// App ATAK « TASK » — ordres C2 (liste + détail + réponses).
// Hauteur max du groupe d’app : ~8,68 × TASK_POS_H (le bandeau Retour est en dessous).
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

#define TASK_BG_TITLE {0.04, 0.05, 0.08, 0.96}
#define TASK_BG_STRIP {0.06, 0.07, 0.10, 0.92}
#define TASK_BG_BODY {0.05, 0.06, 0.09, 0.94}
#define TASK_BTN {0.10, 0.28, 0.36, 0.96}
#define TASK_BTN_F {0.14, 0.38, 0.48, 1}
#define TASK_OK {0.08, 0.42, 0.32, 0.96}
#define TASK_OK_F {0.12, 0.52, 0.40, 1}
#define TASK_WARN {0.48, 0.18, 0.12, 0.96}
#define TASK_WARN_F {0.62, 0.24, 0.14, 1}
#define TASK_ACCENT {0.95, 0.72, 0.28, 1}

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
            h = QUOTE(COMSPEC_TASK_H(0.48));
            size = QUOTE(COMSPEC_TASK_H(0.32));
            text = "Ordres reçus";
            colorBackground[] = TASK_BG_TITLE;
            colorBackground2[] = TASK_BG_TITLE;
            colorBackgroundFocused[] = {0.08, 0.09, 0.14, 0.98};
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            tooltip = "Revenir au tiroir des applications.";
            class Attributes { align = "center"; valign = "middle"; };
        };

        class AccentBar: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_TASK_H(0.48));
            w = QUOTE(COMSPEC_TASK_W(3));
            h = QUOTE(COMSPEC_TASK_H(0.05));
            colorBackground[] = TASK_ACCENT;
        };

        class Summary: RscStructuredText
        {
            idc = 9901;
            x = QUOTE(COMSPEC_TASK_W(0.08));
            y = QUOTE(COMSPEC_TASK_H(0.58));
            w = QUOTE(COMSPEC_TASK_W(2.84));
            h = QUOTE(COMSPEC_TASK_H(0.40));
            text = "";
            colorBackground[] = TASK_BG_STRIP;
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#E6EEF0";
                align = "center";
                valign = "middle";
                shadow = 1;
                size = "0.78";
            };
        };

        class OrderList: RscListBox
        {
            idc = 9902;
            x = QUOTE(COMSPEC_TASK_W(0.08));
            y = QUOTE(COMSPEC_TASK_H(1.04));
            w = QUOTE(COMSPEC_TASK_W(2.84));
            h = QUOTE(COMSPEC_TASK_H(2.72));
            colorBackground[] = {0.04, 0.05, 0.08, 0.94};
            colorSelect[] = {0.05, 0.05, 0.07, 1};
            colorSelect2[] = {0.05, 0.05, 0.07, 1};
            colorSelectBackground[] = {0.90, 0.72, 0.28, 0.92};
            colorSelectBackground2[] = {0.90, 0.72, 0.28, 0.92};
            sizeEx = QUOTE(COMSPEC_TASK_H(0.32));
            rowHeight = QUOTE(COMSPEC_TASK_H(0.50));
            onLBSelChanged = "_this call comspec_overwatch_atak_athena_fnc_athena_taskSelect";
        };

        class Detail: RscStructuredText
        {
            idc = 9903;
            x = QUOTE(COMSPEC_TASK_W(0.08));
            y = QUOTE(COMSPEC_TASK_H(3.82));
            w = QUOTE(COMSPEC_TASK_W(2.84));
            h = QUOTE(COMSPEC_TASK_H(2.48));
            text = "";
            colorBackground[] = TASK_BG_BODY;
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#F2F4F7";
                align = "left";
                valign = "top";
                shadow = 1;
                size = "0.82";
            };
        };

        class BtnLeft: BCE_RscButtonMenu
        {
            idc = 9904;
            x = QUOTE(COMSPEC_TASK_W(0.08));
            y = QUOTE(COMSPEC_TASK_H(6.38));
            w = QUOTE(COMSPEC_TASK_W(1.38));
            h = QUOTE(COMSPEC_TASK_H(0.50));
            size = QUOTE(COMSPEC_TASK_H(0.28));
            text = "Accepter";
            show = 0;
            colorBackground[] = TASK_OK;
            colorBackground2[] = TASK_OK;
            colorBackgroundFocused[] = TASK_OK_F;
            onButtonClick = "[_this select 0] call comspec_overwatch_atak_athena_fnc_athena_taskClick";
            class Attributes { align = "center"; valign = "middle"; };
        };
        class BtnRight: BtnLeft
        {
            idc = 9906;
            x = QUOTE(COMSPEC_TASK_W(1.54));
            text = "Refuser";
            colorBackground[] = TASK_WARN;
            colorBackground2[] = TASK_WARN;
            colorBackgroundFocused[] = TASK_WARN_F;
        };

        class BtnRefresh: BCE_RscButtonMenu
        {
            idc = 9907;
            x = QUOTE(COMSPEC_TASK_W(0.08));
            y = QUOTE(COMSPEC_TASK_H(6.94));
            w = QUOTE(COMSPEC_TASK_W(2.84));
            h = QUOTE(COMSPEC_TASK_H(0.38));
            size = QUOTE(COMSPEC_TASK_H(0.24));
            text = "Actualiser";
            colorBackground[] = TASK_BTN;
            colorBackground2[] = TASK_BTN;
            colorBackgroundFocused[] = TASK_BTN_F;
            onButtonClick = "if (!isNil 'comspec_overwatch_connect_fnc_pollOrders') then { [] call comspec_overwatch_connect_fnc_pollOrders; }; [] call comspec_overwatch_atak_athena_fnc_athena_updateTask";
            class Attributes { align = "center"; valign = "middle"; };
        };
    };
};
