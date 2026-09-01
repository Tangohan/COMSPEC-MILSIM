// Connexion Athena — porte d’entrée obligatoire (idd 9981)
class COMSPEC_AthenaAuth_Dialog {
    idd = 9981;
    movingEnable = 1;
    onLoad = "uiNamespace setVariable ['COMSPEC_AthenaAuth_Display', _this select 0]; [] call comspec_overwatch_connect_fnc_pollAuth;";
    onUnload = "uiNamespace setVariable ['COMSPEC_AthenaAuth_Display', displayNull];";

    class Controls {
        class Background: RscText {
            idc = -1;
            x = 0.29 * safezoneW + safezoneX;
            y = 0.10 * safezoneH + safezoneY;
            w = 0.42 * safezoneW;
            h = 0.78 * safezoneH;
            colorBackground[] = {0.012, 0.035, 0.055, 0.97};
        };
        class AccentBar: RscText {
            idc = -1;
            x = 0.29 * safezoneW + safezoneX;
            y = 0.10 * safezoneH + safezoneY;
            w = 0.42 * safezoneW;
            h = 0.004 * safezoneH;
            colorBackground[] = {0.18, 0.82, 0.62, 0.95};
        };
        class Header: RscStructuredText {
            idc = 9415;
            text = "<t font='RobotoCondensedBold' size='1.05' color='#e8f4f0'>ATHENA</t><t align='right' size='0.7' color='#7aa89a'>COMSPEC OVERWATCH</t>";
            x = 0.31 * safezoneW + safezoneX;
            y = 0.115 * safezoneH + safezoneY;
            w = 0.38 * safezoneW;
            h = 0.032 * safezoneH;
        };
        class BrandingHtml: RscHTML {
            idc = 9414;
            x = 0.31 * safezoneW + safezoneX;
            y = 0.150 * safezoneH + safezoneY;
            w = 0.38 * safezoneW;
            h = 0.118 * safezoneH;
            colorBackground[] = {0.03, 0.07, 0.10, 1};
        };
        class Title: RscStructuredText {
            idc = 9416;
            text = "<t align='center' font='RobotoCondensedBold' size='1.1' color='#e8f4f0'>CONNEXION À ATHENA</t>";
            x = 0.31 * safezoneW + safezoneX;
            y = 0.275 * safezoneH + safezoneY;
            w = 0.38 * safezoneW;
            h = 0.036 * safezoneH;
        };
        class EmailEdit: RscEdit {
            idc = 9401;
            x = 0.33 * safezoneW + safezoneX;
            y = 0.325 * safezoneH + safezoneY;
            w = 0.34 * safezoneW;
            h = 0.034 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorText[] = {0.92, 0.95, 0.97, 1};
            autocomplete = "";
            tooltip = "Adresse e-mail de votre compte Athena";
        };
        class PasswordEdit: RscEdit {
            idc = 9402;
            x = 0.33 * safezoneW + safezoneX;
            y = 0.368 * safezoneH + safezoneY;
            w = 0.34 * safezoneW;
            h = 0.034 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorText[] = {0.92, 0.95, 0.97, 1};
            autocomplete = "";
            password = 1;
        };
        class OtpEdit: RscEdit {
            idc = 9403;
            x = 0.33 * safezoneW + safezoneX;
            y = 0.368 * safezoneH + safezoneY;
            w = 0.34 * safezoneW;
            h = 0.034 * safezoneH;
            colorBackground[] = {0.04, 0.08, 0.12, 1};
            colorText[] = {0.92, 0.95, 0.97, 1};
            autocomplete = "";
            show = 0;
        };
        class LoginBtn: COMSPEC_RscButton {
            idc = 9420;
            text = "SE CONNECTER";
            x = 0.33 * safezoneW + safezoneX;
            y = 0.418 * safezoneH + safezoneY;
            w = 0.34 * safezoneW;
            h = 0.040 * safezoneH;
            action = "[] call comspec_overwatch_connect_fnc_submitPassword;";
        };
        class Divider: RscStructuredText {
            idc = 9425;
            text = "<t align='center' size='0.55' color='#5a7a72'>─────────── ou ───────────</t>";
            x = 0.31 * safezoneW + safezoneX;
            y = 0.468 * safezoneH + safezoneY;
            w = 0.38 * safezoneW;
            h = 0.024 * safezoneH;
        };
        class OtpBtn: COMSPEC_RscButton {
            idc = 9421;
            text = "Code temporaire par e-mail";
            x = 0.33 * safezoneW + safezoneX;
            y = 0.500 * safezoneH + safezoneY;
            w = 0.34 * safezoneW;
            h = 0.034 * safezoneH;
            action = "[] call comspec_overwatch_connect_fnc_requestOTP;";
        };
        class OtpSubmitBtn: COMSPEC_RscButton {
            idc = 9424;
            text = "Valider le code";
            x = 0.33 * safezoneW + safezoneX;
            y = 0.418 * safezoneH + safezoneY;
            w = 0.34 * safezoneW;
            h = 0.040 * safezoneH;
            action = "[] call comspec_overwatch_connect_fnc_submitOTP;";
            show = 0;
        };
        class SteamBtn: COMSPEC_RscButton {
            idc = 9422;
            text = "Connexion avec Steam";
            x = 0.33 * safezoneW + safezoneX;
            y = 0.542 * safezoneH + safezoneY;
            w = 0.34 * safezoneW;
            h = 0.034 * safezoneH;
            action = "[] call comspec_overwatch_connect_fnc_loginSteam;";
        };
        class Loader: RscStructuredText {
            idc = 9413;
            text = "";
            x = 0.31 * safezoneW + safezoneX;
            y = 0.325 * safezoneH + safezoneY;
            w = 0.38 * safezoneW;
            h = 0.280 * safezoneH;
            show = 0;
        };
        class EnterBtn: COMSPEC_RscButton {
            idc = 9423;
            text = "ENTRER";
            x = 0.33 * safezoneW + safezoneX;
            y = 0.640 * safezoneH + safezoneY;
            w = 0.34 * safezoneW;
            h = 0.042 * safezoneH;
            action = "closeDialog 1;";
            show = 0;
        };
        class Status: RscStructuredText {
            idc = 9410;
            text = "<t align='center' size='0.55' color='#7a9e88'>● Athena — vérification de la liaison</t>";
            x = 0.31 * safezoneW + safezoneX;
            y = 0.760 * safezoneH + safezoneY;
            w = 0.38 * safezoneW;
            h = 0.028 * safezoneH;
        };
        class Footer: RscStructuredText {
            idc = 9430;
            text = "<t align='center' size='0.5' color='#5a7080'>Extension • Mod</t>";
            x = 0.31 * safezoneW + safezoneX;
            y = 0.790 * safezoneH + safezoneY;
            w = 0.38 * safezoneW;
            h = 0.022 * safezoneH;
        };
    };
};
