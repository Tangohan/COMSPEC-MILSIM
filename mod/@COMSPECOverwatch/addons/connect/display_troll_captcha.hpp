// Dialog troll captcha (idd 9950)
class COMSPEC_TrollCaptcha_Dialog {
    idd = 9950;
    movingEnable = 0;
    onLoad = "uiNamespace setVariable ['COMSPEC_TrollCaptcha_Display', _this select 0]; [] spawn comspec_overwatch_connect_fnc_updateTrollCaptchaDisplay;";
    onUnload = "uiNamespace setVariable ['COMSPEC_TrollCaptcha_Display', displayNull];";

    class ControlsBackground {
        class BlackScreen: RscText {
            idc = -1;
            x = safezoneX;
            y = safezoneY;
            w = safezoneW;
            h = safezoneH;
            colorBackground[] = {0, 0, 0, 0.92};
        };
        
        class DialogBackground: RscText {
            idc = -1;
            x = 0.28 * safezoneW + safezoneX;
            y = 0.18 * safezoneH + safezoneY;
            w = 0.44 * safezoneW;
            h = 0.64 * safezoneH;
            colorBackground[] = {0.95, 0.95, 0.95, 1};
        };
        
        class DialogHeader: RscText {
            idc = -1;
            x = 0.28 * safezoneW + safezoneX;
            y = 0.18 * safezoneH + safezoneY;
            w = 0.44 * safezoneW;
            h = 0.05 * safezoneH;
            colorBackground[] = {0.2, 0.4, 0.8, 1};
        };
    };

    class Controls {
        class Title: RscStructuredText {
            idc = 9951;
            text = "<t font='PuristaLight' size='1' align='center' color='#ffffff'>Vérification Requise</t>";
            x = 0.29 * safezoneW + safezoneX;
            y = 0.195 * safezoneH + safezoneY;
            w = 0.42 * safezoneW;
            h = 0.03 * safezoneH;
        };
        
        class Logo: RscPicture {
            idc = -1;
            text = "\A3\Ui_f\data\GUI\Cfg\Ranks\general_gs.paa";
            x = 0.45 * safezoneW + safezoneX;
            y = 0.25 * safezoneH + safezoneY;
            w = 0.1 * safezoneW;
            h = 0.08 * safezoneH;
        };
        
        class Message: RscStructuredText {
            idc = 9952;
            text = "";
            x = 0.3 * safezoneW + safezoneX;
            y = 0.35 * safezoneH + safezoneY;
            w = 0.4 * safezoneW;
            h = 0.28 * safezoneH;
            colorBackground[] = {1, 1, 1, 0};
        };
        
        class Button1: RscButton {
            idc = 9961;
            text = "Option 1";
            x = 0.3 * safezoneW + safezoneX;
            y = 0.65 * safezoneH + safezoneY;
            w = 0.18 * safezoneW;
            h = 0.04 * safezoneH;
            colorBackground[] = {0.2, 0.4, 0.8, 1};
            colorBackgroundActive[] = {0.3, 0.5, 0.9, 1};
            colorText[] = {1, 1, 1, 1};
            action = "[0] call comspec_overwatch_connect_fnc_validateTrollCaptcha;";
        };
        
        class Button2: RscButton {
            idc = 9962;
            text = "Option 2";
            x = 0.52 * safezoneW + safezoneX;
            y = 0.65 * safezoneH + safezoneY;
            w = 0.18 * safezoneW;
            h = 0.04 * safezoneH;
            colorBackground[] = {0.2, 0.4, 0.8, 1};
            colorBackgroundActive[] = {0.3, 0.5, 0.9, 1};
            colorText[] = {1, 1, 1, 1};
            action = "[1] call comspec_overwatch_connect_fnc_validateTrollCaptcha;";
        };
        
        class Button3: RscButton {
            idc = 9963;
            text = "Option 3";
            x = 0.3 * safezoneW + safezoneX;
            y = 0.7 * safezoneH + safezoneY;
            w = 0.18 * safezoneW;
            h = 0.04 * safezoneH;
            colorBackground[] = {0.2, 0.4, 0.8, 1};
            colorBackgroundActive[] = {0.3, 0.5, 0.9, 1};
            colorText[] = {1, 1, 1, 1};
            action = "[2] call comspec_overwatch_connect_fnc_validateTrollCaptcha;";
        };
        
        class Button4: RscButton {
            idc = 9964;
            text = "Option 4";
            x = 0.52 * safezoneW + safezoneX;
            y = 0.7 * safezoneH + safezoneY;
            w = 0.18 * safezoneW;
            h = 0.04 * safezoneH;
            colorBackground[] = {0.2, 0.4, 0.8, 1};
            colorBackgroundActive[] = {0.3, 0.5, 0.9, 1};
            colorText[] = {1, 1, 1, 1};
            action = "[3] call comspec_overwatch_connect_fnc_validateTrollCaptcha;";
        };
        
        class Footer: RscStructuredText {
            idc = -1;
            text = "<t font='PuristaLight' size='0.6' align='center' color='#666666'>Cette vérification permet de garantir la sécurité de votre connexion ATAK<br/>En cas de difficulté, contactez le support technique (délai de réponse : 3-5 jours ouvrés)</t>";
            x = 0.3 * safezoneW + safezoneX;
            y = 0.76 * safezoneH + safezoneY;
            w = 0.4 * safezoneW;
            h = 0.05 * safezoneH;
        };
        
        class PrivacyLink: RscStructuredText {
            idc = -1;
            text = "<t font='PuristaLight' size='0.5' align='center' color='#0066cc' underline='1'>Politique de confidentialité</t> · <t font='PuristaLight' size='0.5' align='center' color='#0066cc' underline='1'>Conditions d'utilisation</t> · <t font='PuristaLight' size='0.5' align='center' color='#0066cc' underline='1'>Cookies</t>";
            x = 0.3 * safezoneW + safezoneX;
            y = 0.8 * safezoneH + safezoneY;
            w = 0.4 * safezoneW;
            h = 0.018 * safezoneH;
        };
    };
};
