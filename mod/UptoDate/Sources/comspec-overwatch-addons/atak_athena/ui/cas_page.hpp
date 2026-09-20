// App « Appui aérien » — demande depuis le téléphone.
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif

#ifndef COMSPEC_CAS_PHONE_W
    #define COMSPEC_CAS_PHONE_W (safezoneW * 0.8)
#endif
#ifndef COMSPEC_CAS_PHONE_H
    #define COMSPEC_CAS_PHONE_H (COMSPEC_CAS_PHONE_W * 4/3)
#endif
#ifndef COMSPEC_CAS_SIZE_H
    #define COMSPEC_CAS_SIZE_H ((((626) - (60) - (0))) / 2048 * COMSPEC_CAS_PHONE_H)
#endif
#ifndef COMSPEC_CAS_POS_H
    #define COMSPEC_CAS_POS_H (((60)) / 2048 * COMSPEC_CAS_PHONE_H)
#endif
#ifndef COMSPEC_CAS_POS_W
    #define COMSPEC_CAS_POS_W (((COMSPEC_CAS_SIZE_H * 0.56)/3))
#endif
#ifndef COMSPEC_CAS_W
    #define COMSPEC_CAS_W(n) ((n) * COMSPEC_CAS_POS_W)
#endif
#ifndef COMSPEC_CAS_H
    #define COMSPEC_CAS_H(n) ((n) * COMSPEC_CAS_POS_H)
#endif

class COMSPEC_ATAK_Cas: ATAK_Message
{
    class controls
    {
        class Title: COMSPEC_ATAK_Title
        {
            idc = 9920;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_CAS_W(3));
            h = QUOTE(COMSPEC_CAS_H(0.54));
            size = QUOTE(COMSPEC_CAS_H(0.36));
            text = "  Appui aérien";
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            tooltip = "Revenir au tiroir des applications.";
        };

        class AccentBar: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_CAS_H(0.54));
            w = QUOTE(COMSPEC_CAS_W(3));
            h = QUOTE(COMSPEC_CAS_H(0.05));
            colorBackground[] = ATAK_ACCENT;
        };

        class Hint: RscStructuredText
        {
            idc = 9925;
            x = QUOTE(COMSPEC_CAS_W(0.08));
            y = QUOTE(COMSPEC_CAS_H(0.68));
            w = QUOTE(COMSPEC_CAS_W(2.84));
            h = QUOTE(COMSPEC_CAS_H(0.90));
            text = "Type d’appui, emplacement, puis envoi au poste.";
            colorBackground[] = ATAK_BG_DETAIL;
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#E8F2FA";
                align = "left";
                valign = "top";
                size = "0.92";
            };
        };

        class LblType: RscStructuredText
        {
            idc = -1;
            x = QUOTE(COMSPEC_CAS_W(0.08));
            y = QUOTE(COMSPEC_CAS_H(1.68));
            w = QUOTE(COMSPEC_CAS_W(2.84));
            h = QUOTE(COMSPEC_CAS_H(0.40));
            text = "Type d’appui";
            colorBackground[] = {0, 0, 0, 0};
            class Attributes
            {
                font = "RobotoCondensedBold";
                color = "#9ADCF5";
                align = "left";
                valign = "middle";
                size = "0.92";
            };
        };
        class ComboType: RscCombo
        {
            idc = 9701;
            x = QUOTE(COMSPEC_CAS_W(0.08));
            y = QUOTE(COMSPEC_CAS_H(2.08));
            w = QUOTE(COMSPEC_CAS_W(2.84));
            h = QUOTE(COMSPEC_CAS_H(0.50));
            wholeHeight = QUOTE(COMSPEC_CAS_H(2.40));
            colorBackground[] = ATAK_BG_EDIT;
            colorSelectBackground[] = {0.06, 0.22, 0.12, 1};
            colorText[] = {0.95, 0.98, 0.9, 1};
            sizeEx = QUOTE(COMSPEC_CAS_H(0.28));
        };

        class LblGrid: LblType
        {
            y = QUOTE(COMSPEC_CAS_H(2.70));
            text = "Emplacement (grille)";
        };
        class EditGrid: RscEdit
        {
            idc = 9702;
            x = QUOTE(COMSPEC_CAS_W(0.08));
            y = QUOTE(COMSPEC_CAS_H(3.10));
            w = QUOTE(COMSPEC_CAS_W(2.84));
            h = QUOTE(COMSPEC_CAS_H(0.50));
            colorBackground[] = ATAK_BG_EDIT;
            colorText[] = {0.95, 0.98, 0.9, 1};
            sizeEx = QUOTE(COMSPEC_CAS_H(0.28));
            autocomplete = "";
        };

        class LblNotes: LblType
        {
            y = QUOTE(COMSPEC_CAS_H(3.72));
            text = "Notes courtes (optionnel)";
        };
        class EditNotes: RscEdit
        {
            idc = 9703;
            style = 16;
            x = QUOTE(COMSPEC_CAS_W(0.08));
            y = QUOTE(COMSPEC_CAS_H(4.12));
            w = QUOTE(COMSPEC_CAS_W(2.84));
            h = QUOTE(COMSPEC_CAS_H(1.10));
            colorBackground[] = ATAK_BG_EDIT;
            colorText[] = {0.95, 0.98, 0.9, 1};
            sizeEx = QUOTE(COMSPEC_CAS_H(0.28));
            autocomplete = "";
        };

        class BtnSend: COMSPEC_ATAK_BtnWarn
        {
            idc = 9704;
            x = QUOTE(COMSPEC_CAS_W(0.08));
            y = QUOTE(COMSPEC_CAS_H(5.40));
            w = QUOTE(COMSPEC_CAS_W(2.84));
            h = QUOTE(COMSPEC_CAS_H(0.62));
            size = QUOTE(COMSPEC_CAS_H(0.32));
            text = "Envoyer";
            onButtonClick = "[] call comspec_overwatch_connect_fnc_casRequestSubmit";
        };
    };
};
