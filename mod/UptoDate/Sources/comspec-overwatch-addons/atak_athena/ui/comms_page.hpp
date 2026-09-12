// App ATAK « Messagerie » — canaux radio Athena (liste + fil + envoi).
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif

#ifndef COMSPEC_COMMS_PHONE_W
    #define COMSPEC_COMMS_PHONE_W (safezoneW * 0.8)
#endif
#ifndef COMSPEC_COMMS_PHONE_H
    #define COMSPEC_COMMS_PHONE_H (COMSPEC_COMMS_PHONE_W * 4/3)
#endif
#ifndef COMSPEC_COMMS_SIZE_H
    #define COMSPEC_COMMS_SIZE_H ((((626) - (60) - (0))) / 2048 * COMSPEC_COMMS_PHONE_H)
#endif
#ifndef COMSPEC_COMMS_POS_H
    #define COMSPEC_COMMS_POS_H (((60)) / 2048 * COMSPEC_COMMS_PHONE_H)
#endif
#ifndef COMSPEC_COMMS_POS_W
    #define COMSPEC_COMMS_POS_W (((COMSPEC_COMMS_SIZE_H * 0.56)/3))
#endif
#ifndef COMSPEC_COMMS_W
    #define COMSPEC_COMMS_W(n) ((n) * COMSPEC_COMMS_POS_W)
#endif
#ifndef COMSPEC_COMMS_H
    #define COMSPEC_COMMS_H(n) ((n) * COMSPEC_COMMS_POS_H)
#endif

#define COMMS_BG_TITLE ATAK_BG_TITLE
#define COMMS_BG_STRIP ATAK_BG_STRIP
#define COMMS_BG_BODY ATAK_BG_DETAIL
#define COMMS_BTN ATAK_BTN
#define COMMS_BTN_F ATAK_BTN_F
#define COMMS_OK ATAK_GO
#define COMMS_OK_F ATAK_GO_F
#define COMMS_ACCENT ATAK_ACCENT

class COMSPEC_ATAK_Comms: ATAK_Message
{
    class controls
    {
        class Title: COMSPEC_ATAK_Title
        {
            idc = 9920;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_COMMS_W(3));
            h = QUOTE(COMSPEC_COMMS_H(0.48));
            size = QUOTE(COMSPEC_COMMS_H(0.32));
            text = "  Messagerie";
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            tooltip = "Revenir au tiroir des applications.";
        };

        class AccentBar: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_COMMS_H(0.48));
            w = QUOTE(COMSPEC_COMMS_W(3));
            h = QUOTE(COMSPEC_COMMS_H(0.05));
            colorBackground[] = COMMS_ACCENT;
        };

        class ChannelHint: RscStructuredText
        {
            idc = 9921;
            x = QUOTE(COMSPEC_COMMS_W(0.08));
            y = QUOTE(COMSPEC_COMMS_H(0.56));
            w = QUOTE(COMSPEC_COMMS_W(2.84));
            h = QUOTE(COMSPEC_COMMS_H(0.34));
            size = QUOTE(COMSPEC_COMMS_H(0.26));
            text = "";
            colorBackground[] = COMMS_BG_STRIP;
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#E6EEF0";
                align = "center";
                valign = "middle";
                shadow = 1;
                size = "1";
            };
        };

        class ChannelList: RscListBox
        {
            idc = 9922;
            x = QUOTE(COMSPEC_COMMS_W(0.08));
            y = QUOTE(COMSPEC_COMMS_H(0.94));
            w = QUOTE(COMSPEC_COMMS_W(2.84));
            h = QUOTE(COMSPEC_COMMS_H(1.10));
            colorBackground[] = {0.09, 0.09, 0.09, 0.94};
            colorSelect[] = ATAK_LIST_SEL;
            colorSelect2[] = ATAK_LIST_SEL;
            colorSelectBackground[] = ATAK_LIST_SEL_BG;
            colorSelectBackground2[] = ATAK_LIST_SEL_BG;
            sizeEx = QUOTE(COMSPEC_COMMS_H(0.32));
            rowHeight = QUOTE(COMSPEC_COMMS_H(0.40));
            onLBSelChanged = "_this call comspec_overwatch_atak_athena_fnc_athena_commsSelectChannel";
        };

        class ChannelCreateEdit: RscEdit
        {
            idc = 9927;
            x = QUOTE(COMSPEC_COMMS_W(0.08));
            y = QUOTE(COMSPEC_COMMS_H(2.10));
            w = QUOTE(COMSPEC_COMMS_W(2.00));
            h = QUOTE(COMSPEC_COMMS_H(0.48));
            sizeEx = QUOTE(COMSPEC_COMMS_H(0.28));
            colorBackground[] = ATAK_BG_EDIT;
            colorText[] = {0.95, 0.96, 0.97, 1};
            text = "";
        };

        class BtnCreateChannel: COMSPEC_ATAK_Btn
        {
            idc = 9928;
            x = QUOTE(COMSPEC_COMMS_W(2.14));
            y = QUOTE(COMSPEC_COMMS_H(2.10));
            w = QUOTE(COMSPEC_COMMS_W(0.78));
            h = QUOTE(COMSPEC_COMMS_H(0.48));
            size = QUOTE(COMSPEC_COMMS_H(0.28));
            text = "Créer";
            colorBackground[] = COMMS_BTN;
            colorBackground2[] = COMMS_BTN;
            colorBackgroundFocused[] = COMMS_BTN_F;
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_commsCreateChannel";
            tooltip = "Créer un canal radio personnalisé (visible au journal du poste).";
        };

        class MessageList: RscListBox
        {
            idc = 9923;
            x = QUOTE(COMSPEC_COMMS_W(0.08));
            y = QUOTE(COMSPEC_COMMS_H(2.66));
            w = QUOTE(COMSPEC_COMMS_W(2.84));
            h = QUOTE(COMSPEC_COMMS_H(3.20));
            colorBackground[] = {0.06, 0.07, 0.08, 0.97};
            colorText[] = {0.92, 0.94, 0.93, 1};
            colorSelect[] = {0.95, 0.98, 1, 1};
            colorSelect2[] = {0.95, 0.98, 1, 1};
            colorSelectBackground[] = {0.10, 0.18, 0.24, 0.92};
            colorSelectBackground2[] = {0.10, 0.18, 0.24, 0.92};
            sizeEx = QUOTE(COMSPEC_COMMS_H(0.28));
            rowHeight = QUOTE(COMSPEC_COMMS_H(0.62));
        };

        class Compose: RscEdit
        {
            idc = 9924;
            x = QUOTE(COMSPEC_COMMS_W(0.08));
            y = QUOTE(COMSPEC_COMMS_H(5.96));
            w = QUOTE(COMSPEC_COMMS_W(2.00));
            h = QUOTE(COMSPEC_COMMS_H(0.62));
            sizeEx = QUOTE(COMSPEC_COMMS_H(0.32));
            colorBackground[] = ATAK_BG_EDIT;
            colorText[] = {0.95, 0.96, 0.97, 1};
            text = "";
        };

        class BtnSend: COMSPEC_ATAK_BtnGo
        {
            idc = 9925;
            x = QUOTE(COMSPEC_COMMS_W(2.14));
            y = QUOTE(COMSPEC_COMMS_H(5.96));
            w = QUOTE(COMSPEC_COMMS_W(0.78));
            h = QUOTE(COMSPEC_COMMS_H(0.62));
            size = QUOTE(COMSPEC_COMMS_H(0.34));
            text = "Envoyer";
            colorBackground[] = COMMS_OK;
            colorBackground2[] = COMMS_OK;
            colorBackgroundFocused[] = COMMS_OK_F;
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_commsSend";
            tooltip = "Envoyer sur le canal sélectionné.";
        };

        class BtnClear: COMSPEC_ATAK_Btn
        {
            idc = 9926;
            x = QUOTE(COMSPEC_COMMS_W(0.08));
            y = QUOTE(COMSPEC_COMMS_H(6.68));
            w = QUOTE(COMSPEC_COMMS_W(2.84));
            h = QUOTE(COMSPEC_COMMS_H(0.48));
            size = QUOTE(COMSPEC_COMMS_H(0.30));
            text = "Effacer l’affichage local";
            colorBackground[] = COMMS_BTN;
            colorBackground2[] = COMMS_BTN;
            colorBackgroundFocused[] = COMMS_BTN_F;
            onButtonClick = "[] call comspec_overwatch_connect_fnc_clearLocalChatChannel; [] call comspec_overwatch_atak_athena_fnc_athena_updateComms";
            tooltip = "Retire les messages de cet écran uniquement. L’historique du poste reste inchangé.";
        };
    };
};
