// Menu hub ATAK / Overwatch — choix des vues (idd 9969)
class COMSPEC_Hub_Dialog {
    idd = 9969;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_Hub_Display', _this select 0]; [] call comspec_overwatch_connect_fnc_updateStatusBadges; [] spawn comspec_overwatch_connect_fnc_showPlayerProfile;";
    onUnload = "uiNamespace setVariable ['COMSPEC_Hub_Display', displayNull];";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.33 * safezoneW + safezoneX;
            y = 0.08 * safezoneH + safezoneY;
            w = 0.34 * safezoneW;
            h = 0.89 * safezoneH;
            colorBackground[] = {0.015, 0.04, 0.08, 0.96};
        };

        class AccentBar: RscText {
            idc = -1;
            x = 0.33 * safezoneW + safezoneX;
            y = 0.08 * safezoneH + safezoneY;
            w = 0.34 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.2, 0.85, 0.65, 0.9};
        };

        class Title: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='1.05' align='center' color='#e8f4f0'>COMSPEC Overwatch</t>";
            x = 0.345 * safezoneW + safezoneX;
            y = 0.095 * safezoneH + safezoneY;
            w = 0.31 * safezoneW;
            h = 0.032 * safezoneH;
        };

        class Subtitle: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.62' color='#8aa0b4'>Centre opérationnel · touche K</t>";
            x = 0.345 * safezoneW + safezoneX;
            y = 0.125 * safezoneH + safezoneY;
            w = 0.31 * safezoneW;
            h = 0.024 * safezoneH;
        };

        class VersionBadge: RscStructuredText {
            idc = 9110;
            text = "<t align='left' size='0.62' color='#8aa0b4'>Mod  —</t>";
            x = 0.345 * safezoneW + safezoneX;
            y = 0.155 * safezoneH + safezoneY;
            w = 0.14 * safezoneW;
            h = 0.022 * safezoneH;
        };

        class SyncBadge: RscStructuredText {
            idc = 9111;
            text = "<t align='right' size='0.62' color='#ff8a7a'>●  Hors liaison</t>";
            x = 0.49 * safezoneW + safezoneX;
            y = 0.155 * safezoneH + safezoneY;
            w = 0.165 * safezoneW;
            h = 0.022 * safezoneH;
        };

        class SyncDetail: RscStructuredText {
            idc = 9112;
            text = "<t align='center' size='0.55' color='#7a8c9e'>Position · —</t>";
            x = 0.345 * safezoneW + safezoneX;
            y = 0.178 * safezoneH + safezoneY;
            w = 0.31 * safezoneW;
            h = 0.02 * safezoneH;
        };

        class Hint: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.55' color='#6a7c90'>Messagerie : Ctrl+K · Compte Athena : code depuis le site (Connexion en jeu)</t>";
            x = 0.345 * safezoneW + safezoneX;
            y = 0.202 * safezoneH + safezoneY;
            w = 0.31 * safezoneW;
            h = 0.028 * safezoneH;
        };

        class SectionComms: RscStructuredText {
            idc = -1;
            text = "<t size='0.52' color='#5a9e88'>COMMUNICATIONS &amp; COMPTE</t>";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.235 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.018 * safezoneH;
        };

        class BtnChat: RscButton {
            idc = 9101;
            text = "Messagerie";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.258 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.034 * safezoneH;
            colorBackground[] = {0.06, 0.14, 0.2, 0.95};
            colorBackgroundActive[] = {0.1, 0.28, 0.35, 1};
            action = "['chat'] call comspec_overwatch_connect_fnc_hubSelect;";
        };

        class BtnTabletWeb: RscButton {
            idc = 9118;
            text = "Tablette Overwatch (écran tactique)";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.298 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.034 * safezoneH;
            colorBackground[] = {0.08, 0.22, 0.2, 0.95};
            colorBackgroundActive[] = {0.12, 0.35, 0.3, 1};
            action = "['webbrowser'] call comspec_overwatch_connect_fnc_hubSelect;";
        };

        class BtnPhone: RscButton {
            idc = 9104;
            text = "Connecter mon téléphone";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.338 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.034 * safezoneH;
            colorBackground[] = {0.06, 0.14, 0.2, 0.95};
            colorBackgroundActive[] = {0.1, 0.28, 0.35, 1};
            action = "['phone'] call comspec_overwatch_connect_fnc_hubSelect;";
        };

        class BtnAccount: RscButton {
            idc = 9113;
            text = "Compte Athena (saisir un code)";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.378 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.032 * safezoneH;
            colorBackground[] = {0.08, 0.22, 0.2, 0.95};
            colorBackgroundActive[] = {0.12, 0.35, 0.3, 1};
            action = "['account'] call comspec_overwatch_connect_fnc_hubSelect;";
        };

        class BtnCallsign: RscButton {
            idc = 9116;
            text = "Mon indicatif";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.416 * safezoneH + safezoneY;
            w = 0.14 * safezoneW;
            h = 0.032 * safezoneH;
            colorBackground[] = {0.06, 0.14, 0.2, 0.95};
            colorBackgroundActive[] = {0.1, 0.28, 0.35, 1};
            action = "['callsign'] call comspec_overwatch_connect_fnc_hubSelect;";
        };

        class BtnOrders: RscButton {
            idc = 9117;
            text = "Ordres reçus";
            x = 0.505 * safezoneW + safezoneX;
            y = 0.416 * safezoneH + safezoneY;
            w = 0.14 * safezoneW;
            h = 0.032 * safezoneH;
            colorBackground[] = {0.22, 0.14, 0.06, 0.95};
            colorBackgroundActive[] = {0.35, 0.22, 0.1, 1};
            action = "['orders'] call comspec_overwatch_connect_fnc_hubSelect;";
        };

        class SectionOps: RscStructuredText {
            idc = -1;
            text = "<t size='0.52' color='#5a9e88'>OPÉRATIONS</t>";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.458 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.018 * safezoneH;
        };

        class BtnCAS: RscButton {
            idc = 9102;
            text = "Appui aérien (9 lignes)";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.48 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.032 * safezoneH;
            colorBackground[] = {0.06, 0.14, 0.2, 0.95};
            colorBackgroundActive[] = {0.1, 0.28, 0.35, 1};
            action = "['cas'] call comspec_overwatch_connect_fnc_hubSelect;";
        };

        class BtnBriefing: RscButton {
            idc = 9103;
            text = "Tableau de briefing";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.518 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.032 * safezoneH;
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
            h = 0.032 * safezoneH;
            colorBackground[] = {0.06, 0.14, 0.2, 0.95};
            colorBackgroundActive[] = {0.1, 0.28, 0.35, 1};
            action = "['manifest'] call comspec_overwatch_connect_fnc_hubSelect;";
        };

        class SectionField: RscStructuredText {
            idc = -1;
            text = "<t size='0.52' color='#5a9e88'>TERRAIN</t>";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.598 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.018 * safezoneH;
        };

        class BtnPing: RscButton {
            idc = 9106;
            text = "Signaler un point d'intérêt";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.62 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.032 * safezoneH;
            colorBackground[] = {0.06, 0.14, 0.2, 0.95};
            colorBackgroundActive[] = {0.1, 0.28, 0.35, 1};
            action = "['ping'] call comspec_overwatch_connect_fnc_hubSelect;";
        };

        class BtnMedical: RscButton {
            idc = 9107;
            text = "Transmettre le bilan de santé";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.658 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.032 * safezoneH;
            colorBackground[] = {0.06, 0.14, 0.2, 0.95};
            colorBackgroundActive[] = {0.1, 0.28, 0.35, 1};
            action = "['medical'] call comspec_overwatch_connect_fnc_hubSelect;";
        };

        class BtnForceSync: COMSPEC_RscButtonAccent {
            idc = 9119;
            text = "Transmettre ma position et mes données";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.696 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.032 * safezoneH;
            action = "[] spawn { [] call comspec_overwatch_connect_fnc_forceSyncData; };";
        };

        class BtnRefreshLink: RscButton {
            idc = 9109;
            text = "Vérifier la liaison";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.738 * safezoneH + safezoneY;
            w = 0.14 * safezoneW;
            h = 0.03 * safezoneH;
            colorBackground[] = {0.05, 0.12, 0.16, 0.95};
            colorBackgroundActive[] = {0.12, 0.3, 0.32, 1};
            action = "[] spawn { [] call comspec_overwatch_connect_fnc_refreshLinkStatus; };";
        };

        class BtnClose: RscButton {
            idc = 9108;
            text = "Fermer";
            x = 0.505 * safezoneW + safezoneX;
            y = 0.738 * safezoneH + safezoneY;
            w = 0.14 * safezoneW;
            h = 0.03 * safezoneH;
            colorBackground[] = {0.12, 0.08, 0.08, 0.95};
            colorBackgroundActive[] = {0.28, 0.12, 0.12, 1};
            action = "closeDialog 0;";
        };

        class SectionProfile: RscStructuredText {
            idc = -1;
            text = "<t size='0.52' color='#5a9e88'>PROFIL</t>";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.78 * safezoneH + safezoneY;
            w = 0.29 * safezoneW;
            h = 0.016 * safezoneH;
        };

        class ProfileAvatar: RscPicture {
            idc = 9114;
            text = "";
            x = 0.355 * safezoneW + safezoneX;
            y = 0.8 * safezoneH + safezoneY;
            w = 0.05 * safezoneW;
            h = 0.078 * safezoneH;
            colorBackground[] = {0.06, 0.1, 0.14, 0.9};
        };

        class ProfileName: RscStructuredText {
            idc = 9115;
            text = "";
            x = 0.415 * safezoneW + safezoneX;
            y = 0.8 * safezoneH + safezoneY;
            w = 0.23 * safezoneW;
            h = 0.078 * safezoneH;
        };

        class Footer: RscStructuredText {
            idc = -1;
            text = "<t align='center' size='0.5' color='#4a5c6e'>Athena ATAK · image tactique commune</t>";
            x = 0.345 * safezoneW + safezoneX;
            y = 0.89 * safezoneH + safezoneY;
            w = 0.31 * safezoneW;
            h = 0.018 * safezoneH;
        };
    };
};
