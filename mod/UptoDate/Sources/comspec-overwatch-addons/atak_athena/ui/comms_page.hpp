// App ATAK « Messagerie » — liste des canaux, puis fil du canal choisi.
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
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_commsTitleClick";
            tooltip = "Revenir au tiroir des applications, ou à la liste des canaux si un fil est ouvert.";
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

        // --- Vue liste des canaux ---
        class ListHint: RscStructuredText
        {
            idc = 9921;
            x = QUOTE(COMSPEC_COMMS_W(0.08));
            y = QUOTE(COMSPEC_COMMS_H(0.56));
            w = QUOTE(COMSPEC_COMMS_W(2.84));
            h = QUOTE(COMSPEC_COMMS_H(0.42));
            size = QUOTE(COMSPEC_COMMS_H(0.26));
            text = "";
            colorBackground[] = {0.05, 0.08, 0.10, 0.96};
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#F0F6FA";
                align = "center";
                valign = "middle";
                shadow = 0;
                size = "1";
            };
        };

        class ChannelList: RscListBox
        {
            idc = 9922;
            x = QUOTE(COMSPEC_COMMS_W(0.08));
            y = QUOTE(COMSPEC_COMMS_H(1.04));
            w = QUOTE(COMSPEC_COMMS_W(2.84));
            h = QUOTE(COMSPEC_COMMS_H(4.48));
            colorBackground[] = {0.07, 0.08, 0.09, 0.98};
            colorText[] = {0.94, 0.96, 0.97, 1};
            colorSelect[] = {1, 1, 1, 1};
            colorSelect2[] = {1, 1, 1, 1};
            colorSelectBackground[] = {0.12, 0.28, 0.36, 0.95};
            colorSelectBackground2[] = {0.12, 0.28, 0.36, 0.95};
            colorTextRight[] = {1, 0.86, 0.28, 1};
            colorSelectRight[] = {1, 0.92, 0.45, 1};
            sizeEx = QUOTE(COMSPEC_COMMS_H(0.34));
            rowHeight = QUOTE(COMSPEC_COMMS_H(0.50));
            font = "RobotoCondensed";
            onLBSelChanged = "_this call comspec_overwatch_atak_athena_fnc_athena_commsSelectChannel";
        };

        class CreateSectionTitle: RscStructuredText
        {
            idc = 9931;
            x = QUOTE(COMSPEC_COMMS_W(0.08));
            y = QUOTE(COMSPEC_COMMS_H(5.58));
            w = QUOTE(COMSPEC_COMMS_W(2.84));
            h = QUOTE(COMSPEC_COMMS_H(0.46));
            size = QUOTE(COMSPEC_COMMS_H(0.26));
            text = "  Création";
            colorBackground[] = {0.06, 0.09, 0.11, 0.96};
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#9AE8FF";
                align = "left";
                valign = "middle";
                shadow = 0;
                size = "1";
            };
        };

        class ChannelCreateEdit: RscEdit
        {
            idc = 9927;
            x = QUOTE(COMSPEC_COMMS_W(0.08));
            y = QUOTE(COMSPEC_COMMS_H(6.10));
            w = QUOTE(COMSPEC_COMMS_W(1.92));
            h = QUOTE(COMSPEC_COMMS_H(0.52));
            sizeEx = QUOTE(COMSPEC_COMMS_H(0.30));
            colorBackground[] = ATAK_BG_EDIT;
            colorText[] = {0.96, 0.97, 0.98, 1};
            text = "";
            maxChars = 40;
        };

        class BtnCreateChannel: COMSPEC_ATAK_Btn
        {
            idc = 9928;
            x = QUOTE(COMSPEC_COMMS_W(2.08));
            y = QUOTE(COMSPEC_COMMS_H(6.10));
            w = QUOTE(COMSPEC_COMMS_W(0.84));
            h = QUOTE(COMSPEC_COMMS_H(0.52));
            size = QUOTE(COMSPEC_COMMS_H(0.26));
            text = "Créer";
            colorBackground[] = COMMS_BTN;
            colorBackground2[] = COMMS_BTN;
            colorBackgroundFocused[] = COMMS_BTN_F;
            action = "[] call comspec_overwatch_atak_athena_fnc_athena_commsCreateChannel";
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_commsCreateChannel";
            tooltip = "Créer un canal radio personnalisé, visible au journal du poste.";
        };

        // --- Vue fil d’un canal ---
        class BtnBack: COMSPEC_ATAK_Btn
        {
            idc = 9930;
            show = 0;
            x = QUOTE(COMSPEC_COMMS_W(0.08));
            y = QUOTE(COMSPEC_COMMS_H(0.56));
            w = QUOTE(COMSPEC_COMMS_W(2.84));
            h = QUOTE(COMSPEC_COMMS_H(0.50));
            size = QUOTE(COMSPEC_COMMS_H(0.28));
            text = "Retour aux canaux";
            colorBackground[] = COMMS_BTN;
            colorBackground2[] = COMMS_BTN;
            colorBackgroundFocused[] = COMMS_BTN_F;
            action = "[] call comspec_overwatch_atak_athena_fnc_athena_commsBack";
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_commsBack";
            tooltip = "Revenir à la liste des canaux.";
        };

        class ThreadHint: RscStructuredText
        {
            idc = 9934;
            show = 0;
            x = QUOTE(COMSPEC_COMMS_W(0.08));
            y = QUOTE(COMSPEC_COMMS_H(1.10));
            w = QUOTE(COMSPEC_COMMS_W(2.84));
            h = QUOTE(COMSPEC_COMMS_H(0.42));
            size = QUOTE(COMSPEC_COMMS_H(0.26));
            text = "";
            colorBackground[] = {0.05, 0.08, 0.10, 0.96};
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#F0F6FA";
                align = "center";
                valign = "middle";
                shadow = 0;
                size = "1";
            };
        };

        class MessageViewport: RscControlsGroup
        {
            idc = 9923;
            show = 0;
            x = QUOTE(COMSPEC_COMMS_W(0.08));
            y = QUOTE(COMSPEC_COMMS_H(1.56));
            w = QUOTE(COMSPEC_COMMS_W(2.84));
            h = QUOTE(COMSPEC_COMMS_H(4.06));
            colorBackground[] = {0.05, 0.06, 0.07, 0.98};
            class VScrollbar
            {
                width = 0.014;
                autoScrollEnabled = 1;
                color[] = {0.35, 0.75, 0.95, 0.75};
                colorActive[] = {0.45, 0.85, 1, 1};
                colorDisabled[] = {0.25, 0.35, 0.4, 0.35};
                shadow = 0;
                scrollSpeed = 0.08;
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
                class MessageBody: RscStructuredText
                {
                    idc = 9933;
                    x = 0;
                    y = 0;
                    w = QUOTE(COMSPEC_COMMS_W(2.68));
                    h = QUOTE(COMSPEC_COMMS_H(4.06));
                    text = "";
                    colorBackground[] = {0, 0, 0, 0};
                    class Attributes
                    {
                        font = "RobotoCondensed";
                        color = "#F0F6FA";
                        align = "left";
                        valign = "top";
                        shadow = 0;
                        size = "0.78";
                    };
                };
            };
        };

        class Compose: RscEdit
        {
            idc = 9924;
            show = 0;
            x = QUOTE(COMSPEC_COMMS_W(0.08));
            y = QUOTE(COMSPEC_COMMS_H(5.70));
            w = QUOTE(COMSPEC_COMMS_W(2.00));
            h = QUOTE(COMSPEC_COMMS_H(0.64));
            sizeEx = QUOTE(COMSPEC_COMMS_H(0.30));
            colorBackground[] = ATAK_BG_EDIT;
            colorText[] = {0.97, 0.98, 0.98, 1};
            text = "";
            style = 16;
            maxChars = 100;
            tooltip = "100 caractères maximum. Le texte passe à la ligne automatiquement.";
        };

        class BtnSend: COMSPEC_ATAK_BtnGo
        {
            idc = 9925;
            show = 0;
            x = QUOTE(COMSPEC_COMMS_W(2.14));
            y = QUOTE(COMSPEC_COMMS_H(5.70));
            w = QUOTE(COMSPEC_COMMS_W(0.78));
            h = QUOTE(COMSPEC_COMMS_H(0.64));
            size = QUOTE(COMSPEC_COMMS_H(0.30));
            text = "Envoyer";
            colorBackground[] = COMMS_OK;
            colorBackground2[] = COMMS_OK;
            colorBackgroundFocused[] = COMMS_OK_F;
            action = "[] call comspec_overwatch_atak_athena_fnc_athena_commsSend";
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_commsSend";
            tooltip = "Envoyer sur ce canal (100 caractères maximum).";
        };

        class BtnClear: COMSPEC_ATAK_Btn
        {
            idc = 9926;
            show = 0;
            x = QUOTE(COMSPEC_COMMS_W(0.08));
            y = QUOTE(COMSPEC_COMMS_H(6.42));
            w = QUOTE(COMSPEC_COMMS_W(1.38));
            h = QUOTE(COMSPEC_COMMS_H(0.48));
            size = QUOTE(COMSPEC_COMMS_H(0.24));
            text = "Effacer l’écran";
            colorBackground[] = COMMS_BTN;
            colorBackground2[] = COMMS_BTN;
            colorBackgroundFocused[] = COMMS_BTN_F;
            action = "[] call comspec_overwatch_connect_fnc_clearLocalChatChannel; [] call comspec_overwatch_atak_athena_fnc_athena_updateComms";
            onButtonClick = "[] call comspec_overwatch_connect_fnc_clearLocalChatChannel; [] call comspec_overwatch_atak_athena_fnc_athena_updateComms";
            tooltip = "Retire les messages de cet écran uniquement. L’historique du poste reste inchangé.";
        };

        class BtnDeleteChannel: COMSPEC_ATAK_Btn
        {
            idc = 9929;
            show = 0;
            x = QUOTE(COMSPEC_COMMS_W(1.54));
            y = QUOTE(COMSPEC_COMMS_H(6.42));
            w = QUOTE(COMSPEC_COMMS_W(1.38));
            h = QUOTE(COMSPEC_COMMS_H(0.48));
            size = QUOTE(COMSPEC_COMMS_H(0.24));
            text = "Supprimer";
            colorBackground[] = COMMS_BTN;
            colorBackground2[] = COMMS_BTN;
            colorBackgroundFocused[] = COMMS_BTN_F;
            action = "[] call comspec_overwatch_atak_athena_fnc_athena_commsDeleteChannel";
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_commsDeleteChannel";
            tooltip = "Supprimer ce canal personnalisé. Les canaux système restent protégés.";
        };
    };
};
