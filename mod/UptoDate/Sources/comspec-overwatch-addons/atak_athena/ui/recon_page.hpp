// App ATAK « Reco » — note de reconnaissance (comme Appui aérien).
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif

#ifndef COMSPEC_RECON_PHONE_W
    #define COMSPEC_RECON_PHONE_W (safezoneW * 0.8)
#endif
#ifndef COMSPEC_RECON_PHONE_H
    #define COMSPEC_RECON_PHONE_H (COMSPEC_RECON_PHONE_W * 4/3)
#endif
#ifndef COMSPEC_RECON_SIZE_H
    #define COMSPEC_RECON_SIZE_H ((((626) - (60) - (0))) / 2048 * COMSPEC_RECON_PHONE_H)
#endif
#ifndef COMSPEC_RECON_POS_H
    #define COMSPEC_RECON_POS_H (((60)) / 2048 * COMSPEC_RECON_PHONE_H)
#endif
#ifndef COMSPEC_RECON_POS_W
    #define COMSPEC_RECON_POS_W (((COMSPEC_RECON_SIZE_H * 0.56)/3))
#endif
#ifndef COMSPEC_RECON_W
    #define COMSPEC_RECON_W(n) ((n) * COMSPEC_RECON_POS_W)
#endif
#ifndef COMSPEC_RECON_H
    #define COMSPEC_RECON_H(n) ((n) * COMSPEC_RECON_POS_H)
#endif

class COMSPEC_ATAK_Recon: ATAK_Message
{
    class controls
    {
        class Title: COMSPEC_ATAK_Title
        {
            idc = 9780;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_RECON_W(3));
            h = QUOTE(COMSPEC_RECON_H(0.54));
            size = QUOTE(COMSPEC_RECON_H(0.36));
            text = "  Reco";
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            tooltip = "Revenir au tiroir des applications.";
        };

        class AccentBar: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_RECON_H(0.54));
            w = QUOTE(COMSPEC_RECON_W(3));
            h = QUOTE(COMSPEC_RECON_H(0.05));
            colorBackground[] = ATAK_ACCENT;
        };

        class Hint: RscStructuredText
        {
            idc = 9781;
            x = QUOTE(COMSPEC_RECON_W(0.08));
            y = QUOTE(COMSPEC_RECON_H(0.68));
            w = QUOTE(COMSPEC_RECON_W(2.84));
            h = QUOTE(COMSPEC_RECON_H(0.72));
            text = "Pointé sous le regard. 140 caractères max.";
            colorBackground[] = ATAK_BG_DETAIL;
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#E8F2FA";
                align = "left";
                valign = "top";
                size = "0.88";
            };
        };

        class LblType: RscStructuredText
        {
            idc = -1;
            x = QUOTE(COMSPEC_RECON_W(0.08));
            y = QUOTE(COMSPEC_RECON_H(1.50));
            w = QUOTE(COMSPEC_RECON_W(2.84));
            h = QUOTE(COMSPEC_RECON_H(0.36));
            text = "Type";
            colorBackground[] = {0, 0, 0, 0};
            class Attributes
            {
                font = "RobotoCondensedBold";
                color = "#9ADCF5";
                align = "left";
                valign = "middle";
                size = "0.88";
            };
        };
        class ComboType: RscCombo
        {
            idc = 9784;
            x = QUOTE(COMSPEC_RECON_W(0.08));
            y = QUOTE(COMSPEC_RECON_H(1.86));
            w = QUOTE(COMSPEC_RECON_W(2.84));
            h = QUOTE(COMSPEC_RECON_H(0.48));
            wholeHeight = QUOTE(COMSPEC_RECON_H(2.80));
            colorBackground[] = ATAK_BG_EDIT;
            colorSelectBackground[] = {0.06, 0.22, 0.12, 1};
            colorText[] = {0.95, 0.98, 0.9, 1};
            sizeEx = QUOTE(COMSPEC_RECON_H(0.26));
        };

        class LblConf: LblType
        {
            y = QUOTE(COMSPEC_RECON_H(2.42));
            text = "Confiance";
        };
        class ComboConf: RscCombo
        {
            idc = 9785;
            x = QUOTE(COMSPEC_RECON_W(0.08));
            y = QUOTE(COMSPEC_RECON_H(2.78));
            w = QUOTE(COMSPEC_RECON_W(2.84));
            h = QUOTE(COMSPEC_RECON_H(0.48));
            wholeHeight = QUOTE(COMSPEC_RECON_H(1.40));
            colorBackground[] = ATAK_BG_EDIT;
            colorSelectBackground[] = {0.06, 0.22, 0.12, 1};
            colorText[] = {0.95, 0.98, 0.9, 1};
            sizeEx = QUOTE(COMSPEC_RECON_H(0.26));
        };

        class LblText: LblType
        {
            y = QUOTE(COMSPEC_RECON_H(3.34));
            text = "Observation";
        };
        class EditText: RscEdit
        {
            idc = 9782;
            style = 16;
            x = QUOTE(COMSPEC_RECON_W(0.08));
            y = QUOTE(COMSPEC_RECON_H(3.70));
            w = QUOTE(COMSPEC_RECON_W(2.84));
            h = QUOTE(COMSPEC_RECON_H(1.10));
            colorBackground[] = ATAK_BG_EDIT;
            colorText[] = {0.95, 0.98, 0.9, 1};
            sizeEx = QUOTE(COMSPEC_RECON_H(0.26));
            autocomplete = "";
            maxChars = 140;
        };

        class BtnSend: COMSPEC_ATAK_BtnGo
        {
            idc = 9787;
            x = QUOTE(COMSPEC_RECON_W(0.08));
            y = QUOTE(COMSPEC_RECON_H(4.92));
            w = QUOTE(COMSPEC_RECON_W(2.84));
            h = QUOTE(COMSPEC_RECON_H(0.58));
            size = QUOTE(COMSPEC_RECON_H(0.30));
            text = "Envoyer";
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_reconSubmit";
        };

        class Recent: RscStructuredText
        {
            idc = 9786;
            x = QUOTE(COMSPEC_RECON_W(0.08));
            y = QUOTE(COMSPEC_RECON_H(5.60));
            w = QUOTE(COMSPEC_RECON_W(2.84));
            h = QUOTE(COMSPEC_RECON_H(2.30));
            text = "";
            colorBackground[] = ATAK_BG_DETAIL;
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#DCE0F0";
                align = "left";
                valign = "top";
                size = "0.78";
            };
        };
    };
};
