// Panneau Athena dans ATAK — quatre écrans (Journal, Alerter, Rapporter, Poste).
#ifndef QUOTE
    #define QUOTE(var1) #var1
#endif

#ifndef COMSPEC_ATHENA_PHONE_W
    #define COMSPEC_ATHENA_PHONE_W (safezoneW * 0.8)
#endif
#ifndef COMSPEC_ATHENA_PHONE_H
    #define COMSPEC_ATHENA_PHONE_H (COMSPEC_ATHENA_PHONE_W * 4/3)
#endif
#ifndef COMSPEC_ATHENA_SIZE_H
    #define COMSPEC_ATHENA_SIZE_H ((((626) - (60) - (0))) / 2048 * COMSPEC_ATHENA_PHONE_H)
#endif
#ifndef COMSPEC_ATHENA_POS_H
    #define COMSPEC_ATHENA_POS_H (((60)) / 2048 * COMSPEC_ATHENA_PHONE_H)
#endif
#ifndef COMSPEC_ATHENA_POS_W
    #define COMSPEC_ATHENA_POS_W (((COMSPEC_ATHENA_SIZE_H * 0.56)/3))
#endif
#ifndef COMSPEC_ATHENA_W
    #define COMSPEC_ATHENA_W(n) ((n) * COMSPEC_ATHENA_POS_W)
#endif
#ifndef COMSPEC_ATHENA_H
    #define COMSPEC_ATHENA_H(n) ((n) * COMSPEC_ATHENA_POS_H)
#endif

#define ATHENA_BG_TITLE ATAK_BG_TITLE
#define ATHENA_BG_STRIP ATAK_BG_STRIP
#define ATHENA_BG_LIST ATAK_BG_LIST
#define ATHENA_BG_DETAIL ATAK_BG_DETAIL
#define ATHENA_TAB_IDLE ATAK_TAB_IDLE
#define ATHENA_TAB_ACTIVE ATAK_TAB_ACTIVE
#define ATHENA_BTN ATAK_BTN
#define ATHENA_BTN_FOCUS ATAK_BTN_F
#define ATHENA_BTN_ACCENT ATAK_BG_TILE
#define ATHENA_BTN_ACCENT_F ATAK_BG_TILE_F
#define ATHENA_BTN_OK ATAK_GO
#define ATHENA_BTN_OK_F ATAK_GO_F
#define ATHENA_BTN_WARN ATAK_WARN
#define ATHENA_BTN_WARN_F ATAK_WARN_F
#define ATHENA_BTN_DANGER ATAK_DANGER
#define ATHENA_BTN_DANGER_F ATAK_DANGER_F
#define ATHENA_ACCENT ATAK_ACCENT

class COMSPEC_ATAK_Athena: ATAK_Message
{
    class controls
    {
        class Title: COMSPEC_ATAK_Title
        {
            idc = 9700;
            x = 0;
            y = 0;
            w = QUOTE(COMSPEC_ATHENA_W(3));
            h = QUOTE(COMSPEC_ATHENA_H(0.62));
            size = QUOTE(COMSPEC_ATHENA_H(0.40));
            text = "  Athena";
            onButtonClick = "call BCE_fnc_ATAK_toggleSubListMenu";
            tooltip = "Revenir au tiroir des applications.";
        };

        class AccentBar: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_ATHENA_H(0.62));
            w = QUOTE(COMSPEC_ATHENA_W(3));
            h = QUOTE(COMSPEC_ATHENA_H(0.06));
            colorBackground[] = ATHENA_ACCENT;
        };

        class PanelFill: RscText
        {
            idc = -1;
            x = 0;
            y = QUOTE(COMSPEC_ATHENA_H(0.68));
            w = QUOTE(COMSPEC_ATHENA_W(3));
            h = QUOTE(COMSPEC_ATHENA_H(11.20));
            colorBackground[] = ATAK_BG_PANEL;
        };

        class Status: RscStructuredText
        {
            idc = 9701;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(0.74));
            w = QUOTE(COMSPEC_ATHENA_W(2.88));
            h = QUOTE(COMSPEC_ATHENA_H(0.42));
            size = QUOTE(COMSPEC_ATHENA_H(0.30));
            text = "";
            colorBackground[] = ATHENA_BG_STRIP;
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#C8CDD2";
                align = "center";
                valign = "middle";
                shadow = 1;
                size = "1";
            };
        };

        class HomeFil: COMSPEC_ATAK_Btn
        {
            idc = 9761;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(1.22));
            w = QUOTE(COMSPEC_ATHENA_W(0.69));
            h = QUOTE(COMSPEC_ATHENA_H(0.52));
            size = QUOTE(COMSPEC_ATHENA_H(0.36));
            text = "Journal";
            colorBackground[] = ATHENA_TAB_ACTIVE;
            colorBackground2[] = ATHENA_TAB_ACTIVE;
            colorBackgroundFocused[] = ATHENA_TAB_ACTIVE;
            onButtonClick = "['fil'] call comspec_overwatch_atak_athena_fnc_athena_selectHome";
        };
        class HomeAlerter: HomeFil
        {
            idc = 9762;
            x = QUOTE(COMSPEC_ATHENA_W(0.79));
            text = "Alerter";
            colorBackground[] = ATHENA_TAB_IDLE;
            colorBackground2[] = ATHENA_TAB_IDLE;
            colorBackgroundFocused[] = ATHENA_TAB_ACTIVE;
            onButtonClick = "['alerter'] call comspec_overwatch_atak_athena_fnc_athena_selectHome";
        };
        class HomeRapporter: HomeFil
        {
            idc = 9763;
            x = QUOTE(COMSPEC_ATHENA_W(1.52));
            text = "Rapporter";
            colorBackground[] = ATHENA_TAB_IDLE;
            colorBackground2[] = ATHENA_TAB_IDLE;
            colorBackgroundFocused[] = ATHENA_TAB_ACTIVE;
            onButtonClick = "['rapporter'] call comspec_overwatch_atak_athena_fnc_athena_selectHome";
        };
        class HomePoste: HomeFil
        {
            idc = 9764;
            x = QUOTE(COMSPEC_ATHENA_W(2.25));
            text = "Poste";
            colorBackground[] = ATHENA_TAB_IDLE;
            colorBackground2[] = ATHENA_TAB_IDLE;
            colorBackgroundFocused[] = ATHENA_TAB_ACTIVE;
            onButtonClick = "['poste'] call comspec_overwatch_atak_athena_fnc_athena_selectHome";
        };

        class FilterCombo: RscCombo
        {
            idc = 9760;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(1.82));
            w = QUOTE(COMSPEC_ATHENA_W(2.88));
            h = QUOTE(COMSPEC_ATHENA_H(0.42));
            sizeEx = QUOTE(COMSPEC_ATHENA_H(0.28));
            colorBackground[] = ATAK_BG_EDIT;
            colorSelectBackground[] = ATHENA_TAB_ACTIVE;
            onLBSelChanged = "_this call comspec_overwatch_atak_athena_fnc_athena_selectFilter";
        };

        class Inbox: RscListBox
        {
            idc = 9710;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(2.32));
            w = QUOTE(COMSPEC_ATHENA_W(2.88));
            h = QUOTE(COMSPEC_ATHENA_H(3.55));
            colorBackground[] = ATHENA_BG_LIST;
            colorText[] = ATAK_LIST_TEXT;
            colorSelect[] = ATAK_LIST_SEL;
            colorSelect2[] = ATAK_LIST_SEL;
            colorSelectBackground[] = ATAK_LIST_SEL_BG;
            colorSelectBackground2[] = ATAK_LIST_SEL_BG;
            sizeEx = QUOTE(COMSPEC_ATHENA_H(0.42));
            rowHeight = QUOTE(COMSPEC_ATHENA_H(0.54));
            onLBSelChanged = "_this call comspec_overwatch_atak_athena_fnc_athena_selectInbox";
        };

        class Detail: RscStructuredText
        {
            idc = 9711;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(5.95));
            w = QUOTE(COMSPEC_ATHENA_W(2.88));
            h = QUOTE(COMSPEC_ATHENA_H(2.35));
            size = QUOTE(COMSPEC_ATHENA_H(0.32));
            text = "";
            colorBackground[] = ATHENA_BG_DETAIL;
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#E8F0F4";
                align = "left";
                valign = "top";
                shadow = 1;
                size = "1";
            };
        };

        class Feedback: RscStructuredText
        {
            idc = 9712;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(8.38));
            w = QUOTE(COMSPEC_ATHENA_W(2.88));
            h = QUOTE(COMSPEC_ATHENA_H(0.36));
            size = QUOTE(COMSPEC_ATHENA_H(0.28));
            text = "";
            show = 0;
            colorBackground[] = {0.04, 0.12, 0.14, 0.96};
            class Attributes
            {
                font = "RobotoCondensed";
                color = "#E8F4F0";
                align = "center";
                valign = "middle";
                shadow = 1;
                size = "1";
            };
        };

        class BtnTic: COMSPEC_ATAK_BtnDanger
        {
            idc = 9720;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(1.82));
            w = QUOTE(COMSPEC_ATHENA_W(0.92));
            h = QUOTE(COMSPEC_ATHENA_H(0.58));
            size = QUOTE(COMSPEC_ATHENA_H(0.34));
            text = "Contact";
            show = 0;
            onButtonClick = "['TIC'] call comspec_overwatch_atak_athena_fnc_athena_sendQuick";
        };
        class BtnClear: BtnTic
        {
            idc = 9724;
            x = QUOTE(COMSPEC_ATHENA_W(1.04));
            text = "Fin contact";
            colorBackground[] = ATHENA_BTN_OK;
            colorBackground2[] = ATHENA_BTN_OK;
            colorBackgroundFocused[] = ATHENA_BTN_OK_F;
            onButtonClick = "['TIC_CLEAR'] call comspec_overwatch_atak_athena_fnc_athena_sendQuick";
            class Attributes { font = "RobotoCondensed"; color = "#7CFF9A"; align = "center"; valign = "middle"; shadow = "false"; };
        };
        class BtnEagle: BtnTic
        {
            idc = 9723;
            x = QUOTE(COMSPEC_ATHENA_W(2.02));
            text = "Op. à terre";
            colorBackground[] = ATHENA_BTN_WARN;
            colorBackground2[] = ATHENA_BTN_WARN;
            colorBackgroundFocused[] = ATHENA_BTN_WARN_F;
            onButtonClick = "['EAGLE_DOWN'] call comspec_overwatch_atak_athena_fnc_athena_sendQuick";
            class Attributes { font = "RobotoCondensed"; color = "#FFD080"; align = "center"; valign = "middle"; shadow = "false"; };
        };

        class BtnTriageEnCours: COMSPEC_ATAK_BtnWarn
        {
            idc = 9736;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(2.48));
            w = QUOTE(COMSPEC_ATHENA_W(0.92));
            h = QUOTE(COMSPEC_ATHENA_H(0.50));
            size = QUOTE(COMSPEC_ATHENA_H(0.32));
            text = "Prise en charge";
            show = 0;
            colorBackground[] = ATHENA_BTN_WARN;
            colorBackground2[] = ATHENA_BTN_WARN;
            colorBackgroundFocused[] = ATHENA_BTN_WARN_F;
            onButtonClick = "['en_cours'] call comspec_overwatch_connect_fnc_medicalTriage";
        };
        class BtnTriageTraite: BtnTriageEnCours
        {
            idc = 9737;
            x = QUOTE(COMSPEC_ATHENA_W(1.04));
            text = "Traité";
            colorBackground[] = ATHENA_BTN_OK;
            colorBackground2[] = ATHENA_BTN_OK;
            colorBackgroundFocused[] = ATHENA_BTN_OK_F;
            onButtonClick = "['traite'] call comspec_overwatch_connect_fnc_medicalTriage";
            class Attributes { font = "RobotoCondensed"; color = "#7CFF9A"; align = "center"; valign = "middle"; shadow = "false"; };
        };
        class BtnTriageAnnule: BtnTriageEnCours
        {
            idc = 9738;
            x = QUOTE(COMSPEC_ATHENA_W(2.02));
            text = "Annuler";
            colorBackground[] = ATHENA_BTN;
            colorBackground2[] = ATHENA_BTN;
            colorBackgroundFocused[] = ATHENA_BTN_FOCUS;
            onButtonClick = "['annule'] call comspec_overwatch_connect_fnc_medicalTriage";
            class Attributes { font = "RobotoCondensed"; color = "#FFFFFF"; align = "center"; valign = "middle"; shadow = "false"; };
        };

        class BtnFrago: COMSPEC_ATAK_Btn
        {
            idc = 9721;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(1.82));
            w = QUOTE(COMSPEC_ATHENA_W(0.92));
            h = QUOTE(COMSPEC_ATHENA_H(0.50));
            size = QUOTE(COMSPEC_ATHENA_H(0.34));
            text = "FRAGO";
            show = 0;
            colorBackground[] = ATHENA_BTN;
            colorBackground2[] = ATHENA_BTN;
            colorBackgroundFocused[] = ATHENA_BTN_FOCUS;
            onButtonClick = "['FRAGO'] call comspec_overwatch_atak_athena_fnc_athena_sendQuick";
        };
        class BtnBda: BtnFrago
        {
            idc = 9725;
            x = QUOTE(COMSPEC_ATHENA_W(1.04));
            text = "BDA";
            onButtonClick = "['BDA'] call comspec_overwatch_atak_athena_fnc_athena_sendQuick";
        };
        class BtnSalute: BtnFrago
        {
            idc = 9722;
            x = QUOTE(COMSPEC_ATHENA_W(2.02));
            text = "SALUTE";
            onButtonClick = "['SALUTE'] call comspec_overwatch_atak_athena_fnc_athena_sendQuick";
        };

        class BtnPhoto: COMSPEC_ATAK_Btn
        {
            idc = 9732;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(2.40));
            w = QUOTE(COMSPEC_ATHENA_W(0.92));
            h = QUOTE(COMSPEC_ATHENA_H(0.50));
            size = QUOTE(COMSPEC_ATHENA_H(0.32));
            text = "Renvoyer photo";
            show = 0;
            colorBackground[] = ATHENA_BTN;
            colorBackground2[] = ATHENA_BTN;
            colorBackgroundFocused[] = ATHENA_BTN_FOCUS;
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_sendPhoto";
        };
        class BtnWebMarker: BtnPhoto
        {
            idc = 9739;
            x = QUOTE(COMSPEC_ATHENA_W(1.04));
            text = "Repère poste";
            colorBackground[] = ATHENA_BTN_ACCENT;
            colorBackground2[] = ATHENA_BTN_ACCENT;
            colorBackgroundFocused[] = ATHENA_BTN_ACCENT_F;
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_createWebMarker";
        };
        class BtnSeekTx: BtnPhoto
        {
            idc = 9753;
            x = QUOTE(COMSPEC_ATHENA_W(2.02));
            text = "Relevé SEEK";
            colorBackground[] = ATHENA_BTN_OK;
            colorBackground2[] = ATHENA_BTN_OK;
            colorBackgroundFocused[] = ATHENA_BTN_OK_F;
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_sendSeekData";
            class Attributes { font = "RobotoCondensed"; color = "#7CFF9A"; align = "center"; valign = "middle"; shadow = "false"; };
        };

        class BtnCas: COMSPEC_ATAK_BtnWarn
        {
            idc = 9750;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(1.82));
            w = QUOTE(COMSPEC_ATHENA_W(0.92));
            h = QUOTE(COMSPEC_ATHENA_H(0.50));
            size = QUOTE(COMSPEC_ATHENA_H(0.32));
            text = "Appui aérien";
            show = 0;
            colorBackground[] = ATHENA_BTN_WARN;
            colorBackground2[] = ATHENA_BTN_WARN;
            colorBackgroundFocused[] = ATHENA_BTN_WARN_F;
            onButtonClick = "['CAS'] call comspec_overwatch_atak_athena_fnc_athena_sendQuick";
        };
        class BtnManifest: BtnCas
        {
            idc = 9751;
            x = QUOTE(COMSPEC_ATHENA_W(1.04));
            text = "Manifeste";
            colorBackground[] = ATHENA_BTN;
            colorBackground2[] = ATHENA_BTN;
            colorBackgroundFocused[] = ATHENA_BTN_FOCUS;
            onButtonClick = "['MANIFEST'] call comspec_overwatch_atak_athena_fnc_athena_sendQuick";
            class Attributes { font = "RobotoCondensed"; color = "#FFFFFF"; align = "center"; valign = "middle"; shadow = "false"; };
        };
        class BtnBriefing: BtnCas
        {
            idc = 9752;
            x = QUOTE(COMSPEC_ATHENA_W(2.02));
            text = "Briefing";
            colorBackground[] = ATHENA_BTN_ACCENT;
            colorBackground2[] = ATHENA_BTN_ACCENT;
            colorBackgroundFocused[] = ATHENA_BTN_ACCENT_F;
            onButtonClick = "['BRIEFING'] call comspec_overwatch_atak_athena_fnc_athena_sendQuick";
        };

        class BtnLink: COMSPEC_ATAK_Btn
        {
            idc = 9734;
            x = QUOTE(COMSPEC_ATHENA_W(0.06));
            y = QUOTE(COMSPEC_ATHENA_H(2.40));
            w = QUOTE(COMSPEC_ATHENA_W(0.92));
            h = QUOTE(COMSPEC_ATHENA_H(0.50));
            size = QUOTE(COMSPEC_ATHENA_H(0.32));
            text = "Compte Athena";
            show = 0;
            colorBackground[] = ATHENA_BTN_ACCENT;
            colorBackground2[] = ATHENA_BTN_ACCENT;
            colorBackgroundFocused[] = ATHENA_BTN_ACCENT_F;
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_showLinkDialog";
        };
        class BtnPhoneQr: BtnLink
        {
            idc = 9735;
            x = QUOTE(COMSPEC_ATHENA_W(1.04));
            text = "Adresse mobile";
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_showPhoneConnect";
        };
        class BtnRefresh: BtnLink
        {
            idc = 9731;
            x = QUOTE(COMSPEC_ATHENA_W(2.02));
            text = "Actualiser";
            colorBackground[] = ATHENA_BTN;
            colorBackground2[] = ATHENA_BTN;
            colorBackgroundFocused[] = ATHENA_BTN_FOCUS;
            onButtonClick = "[] call comspec_overwatch_atak_athena_fnc_athena_refresh";
        };

        // Anciens onglets : conservés pour compat idc, masqués.
        class TabAll: COMSPEC_ATAK_Btn
        {
            idc = 9740;
            x = 0;
            y = 0;
            w = 0;
            h = 0;
            show = 0;
            text = "Tout";
            onButtonClick = "['all'] call comspec_overwatch_atak_athena_fnc_athena_selectTab";
        };
        class TabPhoto: TabAll { idc = 9742; };
        class TabOrder: TabAll { idc = 9743; };
        class TabMessages: TabAll { idc = 9741; };
        class TabUrgences: TabAll { idc = 9745; };
        class TabBda: TabAll { idc = 9744; };
        class TabLiaison: TabAll { idc = 9746; };
        class TabModules: TabAll { idc = 9747; };

        class NotifList: RscListBox
        {
            idc = 9715;
            x = 0;
            y = 0;
            w = 0;
            h = 0;
            show = 0;
            onLBSelChanged = "_this call comspec_overwatch_atak_athena_fnc_athena_selectNotif";
        };

        class BtnTablet: COMSPEC_ATAK_Btn
        {
            idc = 9730;
            x = 0;
            y = 0;
            w = 0;
            h = 0;
            show = 0;
            text = "";
            onButtonClick = "";
        };
        class BtnModulesLog: BtnTablet
        {
            idc = 9733;
        };
    };
};
