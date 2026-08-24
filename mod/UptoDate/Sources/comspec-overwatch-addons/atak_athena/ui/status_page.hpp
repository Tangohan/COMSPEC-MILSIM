// App « État ATAK » — diagnostics de liaison (latence, débit, stabilité, pertes…).
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif

#ifndef COMSPEC_STATUS_PHONE_W
    #define COMSPEC_STATUS_PHONE_W (safezoneW * 0.8)
#endif
#ifndef COMSPEC_STATUS_PHONE_H
    #define COMSPEC_STATUS_PHONE_H (COMSPEC_STATUS_PHONE_W * 4/3)
#endif
#ifndef COMSPEC_STATUS_SIZE_H
    #define COMSPEC_STATUS_SIZE_H ((((626) - (60) - (0))) / 2048 * COMSPEC_STATUS_PHONE_H)
#endif
#ifndef COMSPEC_STATUS_POS_H
    #define COMSPEC_STATUS_POS_H (((60)) / 2048 * COMSPEC_STATUS_PHONE_H)
#endif
#ifndef COMSPEC_STATUS_POS_W
    #define COMSPEC_STATUS_POS_W (((COMSPEC_STATUS_SIZE_H * 0.56)/3))
#endif
#ifndef COMSPEC_STATUS_W
    #define COMSPEC_STATUS_W(n) ((n) * COMSPEC_STATUS_POS_W)
#endif
#ifndef COMSPEC_STATUS_H
    #define COMSPEC_STATUS_H(n) ((n) * COMSPEC_STATUS_POS_H)
#endif

#define STATUS_BG_TITLE ATAK_BG_TITLE
#define STATUS_BG_STRIP ATAK_BG_STRIP
#define STATUS_BG_BODY ATAK_BG_DETAIL
#define STATUS_BTN ATAK_BTN
#define STATUS_BTN_F ATAK_BTN_F
#define STATUS_ACCENT ATAK_ACCENT

class COMSPEC_ATAK_Status: ATAK_Message
{
    class controls
    {
        class Title: COMSPEC_ATAK_Title
        {
            idc = 9800;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_STATUS_W(3));
            h = QUOTE(COMSPEC_STATUS_H(0.62));
            size = QUOTE(COMSPEC_STATUS_H(0.40));
            text = "  État ATAK";
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            tooltip = "Revenir au tiroir des applications.";
        };

        class AccentBar: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_STATUS_H(0.62));
            w = QUOTE(COMSPEC_STATUS_W(3));
            h = QUOTE(COMSPEC_STATUS_H(0.06));
            colorBackground[] = STATUS_ACCENT;
        };

        class PanelFill: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_STATUS_H(0.68));
            w = QUOTE(COMSPEC_STATUS_W(3));
            h = QUOTE(COMSPEC_STATUS_H(7.40));
            colorBackground[] = ATAK_BG_PANEL;
        };

        class Summary: RscStructuredText
        {
            idc = 9801;
            x = QUOTE(COMSPEC_STATUS_W(0.08));
            y = QUOTE(COMSPEC_STATUS_H(0.78));
            w = QUOTE(COMSPEC_STATUS_W(2.84));
            h = QUOTE(COMSPEC_STATUS_H(0.72));
            text = "";
            colorBackground[] = STATUS_BG_STRIP;
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#E6EEF0";
                align = "center";
                valign = "middle";
                shadow = 1;
                size = "0.74";
            };
        };

        class BodyViewport: RscControlsGroup
        {
            idc = 9806;
            x = QUOTE(COMSPEC_STATUS_W(0.08));
            y = QUOTE(COMSPEC_STATUS_H(1.62));
            w = QUOTE(COMSPEC_STATUS_W(2.84));
            h = QUOTE(COMSPEC_STATUS_H(5.35));
            colorBackground[] = STATUS_BG_BODY;
            class VScrollbar
            {
                width = 0.014;
                autoScrollEnabled = 1;
                color[] = {0.35, 0.75, 0.95, 0.75};
                colorActive[] = {0.45, 0.85, 1, 1};
                colorDisabled[] = {0.25, 0.35, 0.4, 0.35};
                shadow = 0;
                scrollSpeed = 0.06;
            };
            class HScrollbar
            {
                height = 0;
                color[] = {0, 0, 0, 0};
            };
            class ScrollBar
            {
                color[] = {0.35, 0.75, 0.95, 0.75};
                colorActive[] = {0.45, 0.85, 1, 1};
                colorDisabled[] = {0.25, 0.35, 0.4, 0.35};
                shadow = 0;
                thumb = "\A3\ui_f\data\gui\cfg\scrollbar\thumb_ca.paa";
                arrowFull = "\A3\ui_f\data\gui\cfg\scrollbar\arrowFull_ca.paa";
                arrowEmpty = "\A3\ui_f\data\gui\cfg\scrollbar\arrowEmpty_ca.paa";
                border = "\A3\ui_f\data\gui\cfg\scrollbar\border_ca.paa";
            };
            class Controls
            {
                class Body: RscStructuredText
                {
                    idc = 9802;
                    x = 0;
                    y = 0;
                    w = QUOTE(COMSPEC_STATUS_W(2.72));
                    h = QUOTE(COMSPEC_STATUS_H(5.35));
                    text = "";
                    colorBackground[] = {0, 0, 0, 0};
                    class Attributes
                    {
                        font = "RobotoCondensed";
                        color = "#D8E4EA";
                        align = "left";
                        valign = "top";
                        shadow = 1;
                        size = "0.68";
                    };
                };
            };
        };

        class BtnRefresh: COMSPEC_ATAK_Btn
        {
            idc = 9803;
            x = QUOTE(COMSPEC_STATUS_W(0.08));
            y = QUOTE(COMSPEC_STATUS_H(7.10));
            w = QUOTE(COMSPEC_STATUS_W(0.90));
            h = QUOTE(COMSPEC_STATUS_H(0.52));
            size = QUOTE(COMSPEC_STATUS_H(0.28));
            text = "Actualiser";
            colorBackground[] = STATUS_BTN;
            colorBackground2[] = STATUS_BTN;
            colorBackgroundFocused[] = STATUS_BTN_F;
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_updateStatus";
        };

        class BtnSound: BtnRefresh
        {
            idc = 9805;
            x = QUOTE(COMSPEC_STATUS_W(1.05));
            text = "Sons";
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_openSound";
        };

        class BtnAthena: BtnRefresh
        {
            idc = 9804;
            x = QUOTE(COMSPEC_STATUS_W(2.02));
            text = "Athena";
            onButtonClick = "['all'] call comspec_overwatch_atak_athena_fnc_athena_openFeature";
        };
    };
};
