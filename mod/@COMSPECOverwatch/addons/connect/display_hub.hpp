// Menu hub ATAK / Overwatch — choix des vues (idd 9969)
class COMSPEC_Hub_Dialog {
    idd = 9969;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_Hub_Display', _this select 0]; [] call comspec_overwatch_connect_fnc_updateStatusBadges;";
    onUnload = "uiNamespace setVariable ['COMSPEC_Hub_Display', displayNull];";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.33 * safezoneW + safezoneX;
            y = 0.12 * safezoneH + safezoneY;
            w = 0.34 * safezoneW;
            h = 0.82 * safezoneH;
            colorBackground[] = {0.015, 0.04, 0.08, 0.96};
        };

        class AccentBar: RscText {
            idc = -1;
            x = 0.33 * safezoneW + safezoneX;
            y = 0.12 * safezoneH + safezoneY;
            w = 0.34 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.2, 0.85, 0.65, 0.9};
        };

        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='1.05' align='center' color='#e8f4f0'>COMSPEC Overwatch</t>";
            x = 0.345 * safezoneW + safezoneX;
            y = 0.135 * safezoneH + safezoneY;
            w = 0.31 * safezoneW;
            h = 0.032 * safezoneH;
        };

        class Subtitle: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.62' color='#8aa0b4'>Menu des vues · touche K</t>";
            x = 0.345 * safezoneW + safezoneX;
            y = 0.165 * safezoneH + safezoneY;
            w = 0.31 * safezoneW;
            h = 0.024 * safezoneH;
        };

        class VersionBadge: RscStructuredText {
            idc = 9110;
            text = "<t align='left' size='0.62' color='#8aa0b4'>Mod  —</t>";
            x = 0.345 * safezoneW + safezoneX;
            y = 0.195 * safezoneH + safezoneY;
            w = 0.14 * safezoneW;
            h = 0.022 * safezoneH;
        };

        class SyncBadge: RscStructuredText {
            idc = 9111;
            text = "<t align='right' size='0.62' color='#ff8a7a'>●  Hors liaison</t>";
            x = 0.49 * safezoneW + safezoneX;
            y = 0.195 * safezoneH + safezoneY;
            w = 0.165 * safezoneW;
            h = 0.022 * safezoneH;
        };

        class SyncDetail: RscStructuredText {
            idc = 9112;
            text = "<t align='center' size='0.55' color='#7a8c9e'>Position · —</t>";
            x = 0.345 * safezoneW + safezoneX;
            y = 0.218 * safezoneH + safezoneY;
            w = 0.31 * safezoneW;
            h = 0.02 * safezoneH;
        };

        class Hint: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.58' color='#6a7c90'>Choisissez un écran. Messagerie directe : Ctrl+K.</t>";
            x = 0.345 * safezoneW + safezoneX;
            y = 0.242 * safezoneH + safezoneY;
            w = 0.31 * safezoneW;
            h = 0.024 * safezoneH;
        };

        class SectionComms: RscStructuredText {
            idc = -1;
            text = "<t size='0.52' color='#5a9e88'>COMMUNICATIONS</t>";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.275 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.018 * safezoneH;
        };

        class BtnChat: RscButton {
            idc = 9101;
            text = "Messagerie";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.298 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.038 * safezoneH;
            colorBackground[] = {0.06, 0.14, 0.2, 0.95};
            colorBackgroundActive[] = {0.1, 0.28, 0.35, 1};
            action = "['chat'] call comspec_overwatch_connect_fnc_hubSelect;";
        };

        class BtnPhone: RscButton {
            idc = 9104;
            text = "Connecter mon téléphone";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.344 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.038 * safezoneH;
            colorBackground[] = {0.06, 0.14, 0.2, 0.95};
            colorBackgroundActive[] = {0.1, 0.28, 0.35, 1};
            action = "['phone'] call comspec_overwatch_connect_fnc_hubSelect;";
        };

        class BtnAccount: RscButton {
            idc = 9113;
            text = "Connecter mon compte Athena";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.39 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.038 * safezoneH;
            colorBackground[] = {0.08, 0.22, 0.2, 0.95};
            colorBackgroundActive[] = {0.12, 0.35, 0.3, 1};
            action = "['account'] call comspec_overwatch_connect_fnc_hubSelect;";
        };

        class SectionOps: RscStructuredText {
            idc = -1;
            text = "<t size='0.52' color='#5a9e88'>OPÉRATIONS</t>";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.441 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.018 * safezoneH;
        };

        class BtnCAS: RscButton {
            idc = 9102;
            text = "Appui aérien (9 lignes)";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.464 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.038 * safezoneH;
            colorBackground[] = {0.06, 0.14, 0.2, 0.95};
            colorBackgroundActive[] = {0.1, 0.28, 0.35, 1};
            action = "['cas'] call comspec_overwatch_connect_fnc_hubSelect;";
        };

        class BtnBriefing: RscButton {
            idc = 9103;
            text = "Tableau de briefing";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.51 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.038 * safezoneH;
            colorBackground[] = {0.06, 0.14, 0.2, 0.95};
            colorBackgroundActive[] = {0.1, 0.28, 0.35, 1};
            action = "['briefing'] call comspec_overwatch_connect_fnc_hubSelect;";
        };

        class BtnManifest: RscButton {
            idc = 9105;
            text = "Opérations aériennes";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.556 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.038 * safezoneH;
            colorBackground[] = {0.06, 0.14, 0.2, 0.95};
            colorBackgroundActive[] = {0.1, 0.28, 0.35, 1};
            action = "['manifest'] call comspec_overwatch_connect_fnc_hubSelect;";
        };

        class SectionField: RscStructuredText {
            idc = -1;
            text = "<t size='0.52' color='#5a9e88'>TERRAIN</t>";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.607 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.018 * safezoneH;
        };

        class BtnPing: RscButton {
            idc = 9106;
            text = "Signaler un point d'intérêt";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.63 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.038 * safezoneH;
            colorBackground[] = {0.06, 0.14, 0.2, 0.95};
            colorBackgroundActive[] = {0.1, 0.28, 0.35, 1};
            action = "['ping'] call comspec_overwatch_connect_fnc_hubSelect;";
        };

        class BtnMedical: RscButton {
            idc = 9107;
            text = "Transmettre le bilan de santé";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.676 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.038 * safezoneH;
            colorBackground[] = {0.06, 0.14, 0.2, 0.95};
            colorBackgroundActive[] = {0.1, 0.28, 0.35, 1};
            action = "['medical'] call comspec_overwatch_connect_fnc_hubSelect;";
        };

        class BtnRefreshLink: RscButton {
            idc = 9109;
            text = "Vérifier la liaison";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.74 * safezoneH + safezoneY;
            w = 0.14 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.05, 0.12, 0.16, 0.95};
            colorBackgroundActive[] = {0.12, 0.3, 0.32, 1};
            action = "[] spawn { [] call comspec_overwatch_connect_fnc_refreshLinkStatus; };";
        };

        class BtnClose: RscButton {
            idc = 9108;
            text = "Fermer";
            x = 0.505 * safezoneW + safezoneX;
            y = 0.74 * safezoneH + safezoneY;
            w = 0.14 * safezoneW;
            h = 0.036 * safezoneH;
            colorBackground[] = {0.12, 0.08, 0.08, 0.95};
            colorBackgroundActive[] = {0.28, 0.12, 0.12, 1};
            action = "closeDialog 0;";
        };

        class Footer: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.5' color='#4a5c6e'>Athena · cartographie opérationnelle</t>";
            x = 0.345 * safezoneW + safezoneX;
            y = 0.79 * safezoneH + safezoneY;
            w = 0.31 * safezoneW;
            h = 0.02 * safezoneH;
        };
    };
};
