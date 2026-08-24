// App « Briefing » — diaporama mission dans le téléphone ATAK.
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif

#ifndef COMSPEC_BRIEF_PHONE_W
    #define COMSPEC_BRIEF_PHONE_W (safezoneW * 0.8)
#endif
#ifndef COMSPEC_BRIEF_PHONE_H
    #define COMSPEC_BRIEF_PHONE_H (COMSPEC_BRIEF_PHONE_W * 4/3)
#endif
#ifndef COMSPEC_BRIEF_SIZE_H
    #define COMSPEC_BRIEF_SIZE_H ((((626) - (60) - (0))) / 2048 * COMSPEC_BRIEF_PHONE_H)
#endif
#ifndef COMSPEC_BRIEF_POS_H
    #define COMSPEC_BRIEF_POS_H (((60)) / 2048 * COMSPEC_BRIEF_PHONE_H)
#endif
#ifndef COMSPEC_BRIEF_POS_W
    #define COMSPEC_BRIEF_POS_W (((COMSPEC_BRIEF_SIZE_H * 0.56)/3))
#endif
#ifndef COMSPEC_BRIEF_W
    #define COMSPEC_BRIEF_W(n) ((n) * COMSPEC_BRIEF_POS_W)
#endif
#ifndef COMSPEC_BRIEF_H
    #define COMSPEC_BRIEF_H(n) ((n) * COMSPEC_BRIEF_POS_H)
#endif

#define BRIEF_BG_TITLE ATAK_BG_TITLE
#define BRIEF_BG_STRIP ATAK_BG_STRIP
#define BRIEF_BG_PIC {0.04, 0.04, 0.04, 1}
#define BRIEF_BTN ATAK_BTN
#define BRIEF_BTN_F ATAK_BTN_F
#define BRIEF_BTN_DIM {0.11, 0.11, 0.11, 0.96}
#define BRIEF_ACCENT ATAK_ACCENT

class COMSPEC_ATAK_Briefing: ATAK_Message
{
    class controls
    {
        class Title: COMSPEC_ATAK_Title
        {
            idc = 9850;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_BRIEF_W(3));
            h = QUOTE(COMSPEC_BRIEF_H(0.52));
            size = QUOTE(COMSPEC_BRIEF_H(0.34));
            text = "  Briefing";
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            tooltip = "Revenir au tiroir des applications.";
        };

        class AccentBar: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_BRIEF_H(0.52));
            w = QUOTE(COMSPEC_BRIEF_W(3));
            h = QUOTE(COMSPEC_BRIEF_H(0.05));
            colorBackground[] = BRIEF_ACCENT;
        };

        class IndexLabel: RscStructuredText
        {
            idc = 9851;
            x = QUOTE(COMSPEC_BRIEF_W(0.08));
            y = QUOTE(COMSPEC_BRIEF_H(0.64));
            w = QUOTE(COMSPEC_BRIEF_W(2.84));
            h = QUOTE(COMSPEC_BRIEF_H(0.36));
            text = "— / —";
            colorBackground[] = BRIEF_BG_STRIP;
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#E6EEF0";
                align = "center";
                valign = "middle";
                size = "0.72";
            };
        };

        class SlidePicture: RscPictureKeepAspect
        {
            idc = 9852;
            text = "";
            x = QUOTE(COMSPEC_BRIEF_W(0.08));
            y = QUOTE(COMSPEC_BRIEF_H(1.08));
            w = QUOTE(COMSPEC_BRIEF_W(2.84));
            h = QUOTE(COMSPEC_BRIEF_H(4.55));
            colorBackground[] = BRIEF_BG_PIC;
        };

        class Caption: RscStructuredText
        {
            idc = 9853;
            x = QUOTE(COMSPEC_BRIEF_W(0.08));
            y = QUOTE(COMSPEC_BRIEF_H(5.72));
            w = QUOTE(COMSPEC_BRIEF_W(2.84));
            h = QUOTE(COMSPEC_BRIEF_H(0.55));
            text = "";
            colorBackground[] = BRIEF_BG_STRIP;
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#D8E4EA";
                align = "center";
                valign = "middle";
                size = "0.68";
            };
        };

        class BtnPrev: COMSPEC_ATAK_Btn
        {
            idc = 9854;
            x = QUOTE(COMSPEC_BRIEF_W(0.08));
            y = QUOTE(COMSPEC_BRIEF_H(6.40));
            w = QUOTE(COMSPEC_BRIEF_W(0.90));
            h = QUOTE(COMSPEC_BRIEF_H(0.48));
            size = QUOTE(COMSPEC_BRIEF_H(0.26));
            text = "◀ Préc.";
            colorBackground[] = BRIEF_BTN;
            colorBackground2[] = BRIEF_BTN;
            colorBackgroundFocused[] = BRIEF_BTN_F;
            onButtonClick = "[-1] call comspec_overwatch_connect_fnc_briefingBoardStep";
        };

        class BtnNext: BtnPrev
        {
            idc = 9855;
            x = QUOTE(COMSPEC_BRIEF_W(1.05));
            text = "Suiv. ▶";
            onButtonClick = "[1] call comspec_overwatch_connect_fnc_briefingBoardStep";
        };

        class BtnRefresh: BtnPrev
        {
            idc = 9856;
            x = QUOTE(COMSPEC_BRIEF_W(2.02));
            text = "Actu.";
            colorBackground[] = BRIEF_BTN_DIM;
            colorBackground2[] = BRIEF_BTN_DIM;
            onButtonClick = "[] call comspec_overwatch_connect_fnc_refreshBriefingSlides";
        };

        class BtnPhone: COMSPEC_ATAK_Btn
        {
            idc = 9857;
            x = QUOTE(COMSPEC_BRIEF_W(0.08));
            y = QUOTE(COMSPEC_BRIEF_H(7.00));
            w = QUOTE(COMSPEC_BRIEF_W(1.38));
            h = QUOTE(COMSPEC_BRIEF_H(0.48));
            size = QUOTE(COMSPEC_BRIEF_H(0.24));
            text = "Liaison mobile";
            colorBackground[] = BRIEF_BTN_DIM;
            colorBackground2[] = BRIEF_BTN_DIM;
            colorBackgroundFocused[] = BRIEF_BTN_F;
            onButtonClick = "['liaison'] call comspec_overwatch_atak_athena_fnc_athena_openFeature";
        };

        class BtnAthena: BtnPhone
        {
            idc = 9858;
            x = QUOTE(COMSPEC_BRIEF_W(1.54));
            text = "Athena";
            onButtonClick = "['all'] call comspec_overwatch_atak_athena_fnc_athena_openFeature";
        };
    };
};
