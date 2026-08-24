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

#define TASK_BG_TITLE ATAK_BG_TITLE
#define TASK_BG_STRIP ATAK_BG_STRIP
#define TASK_BG_BODY ATAK_BG_DETAIL
#define TASK_BTN ATAK_BTN
#define TASK_BTN_F ATAK_BTN_F
#define TASK_OK ATAK_GO
#define TASK_OK_F ATAK_GO_F
#define TASK_WARN ATAK_DANGER
#define TASK_WARN_F ATAK_DANGER_F
#define TASK_ACCENT ATAK_ACCENT

class COMSPEC_ATAK_Task: ATAK_Message
{
    class controls
    {
        class Title: COMSPEC_ATAK_Title
        {
            idc = 9900;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_TASK_W(3));
            h = QUOTE(COMSPEC_TASK_H(0.48));
            size = QUOTE(COMSPEC_TASK_H(0.32));
            text = "  Ordres reçus";
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            tooltip = "Revenir au tiroir des applications.";
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
            colorBackground[] = {0.09, 0.09, 0.09, 0.94};
            colorSelect[] = ATAK_LIST_SEL;
            colorSelect2[] = ATAK_LIST_SEL;
            colorSelectBackground[] = ATAK_LIST_SEL_BG;
            colorSelectBackground2[] = ATAK_LIST_SEL_BG;
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

        class BtnLeft: COMSPEC_ATAK_BtnGo
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
        };
        class BtnRight: BtnLeft
        {
            idc = 9906;
            x = QUOTE(COMSPEC_TASK_W(1.54));
            text = "Refuser";
            colorBackground[] = TASK_WARN;
            colorBackground2[] = TASK_WARN;
            colorBackgroundFocused[] = TASK_WARN_F;
            class Attributes { font = "RobotoCondensed"; color = "#FF8A7A"; align = "center"; valign = "middle"; shadow = "false"; };
        };

        class BtnRefresh: COMSPEC_ATAK_Btn
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
        };
    };
};
