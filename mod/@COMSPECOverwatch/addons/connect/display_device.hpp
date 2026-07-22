// Vue tablette Athena : superpose l'affichage du mod (statut de liaison, profil joueur, accès au
// hub complet) sur l'écran de la tablette physique (image de fond, idd 9973).
//
// Les coordonnées de l'écran ont été mesurées par analyse de pixels sur l'image source
// (1752x897, écran de (232,134) à (1465,743)) : left=0.1324 top=0.1494 right=0.8362 bottom=0.8283
// exprimés en fraction de l'image. Le fond est dimensionné à 0.72*safezoneW de large ; sa hauteur
// (0.72/1.9532 * safezoneH, où 1.9532 = 1752/897 est le ratio largeur/hauteur de l'image) suit la
// convention Arma où une fraction identique de safezoneW et safezoneH donne un carré à l'écran —
// ce calcul évite donc de déformer la photo. Non vérifié visuellement (pas d'Arma dans cet
// environnement) : à valider en jeu avant diffusion.
class COMSPEC_Device_Dialog {
    idd = 9973;
    movingEnable = 1;
    onLoad = "(_this select 0) call comspec_overwatch_connect_fnc_showDeviceView;";

    class Controls {
        // Fond : la tablette entière (bord olive + écran), dimensions calculées pour préserver
        // le ratio d'aspect réel de la photo (1752:897).
        class DeviceBackground: RscPicture {
            idc = 9300;
            text = "\z\comspec_overwatch\addons\connect\img\athena_tablet.png";
            x = 0.14 * safezoneW + safezoneX;
            y = 0.3157 * safezoneH + safezoneY;
            w = 0.72 * safezoneW;
            h = 0.3686 * safezoneH;
        };

        // --- Contenu superposé, dans la zone écran mesurée (x 0.2353→0.7420, y 0.3708→0.6210) ---

        class DeviceTitle: RscStructuredText {
            idc = -1;
            text = "<t font='RobotoCondensedBold' size='0.62' color='#d8e4ec'>ATHENA — OVERWATCH</t>";
            x = 0.2453 * safezoneW + safezoneX;
            y = 0.378 * safezoneH + safezoneY;
            w = 0.28 * safezoneW;
            h = 0.02 * safezoneH;
        };

        class DeviceStatus: RscStructuredText {
            idc = 9312;
            text = "<t align='right' size='0.6' color='#ff8a7a'>●  Hors liaison</t>";
            x = 0.53 * safezoneW + safezoneX;
            y = 0.378 * safezoneH + safezoneY;
            w = 0.2 * safezoneW;
            h = 0.02 * safezoneH;
        };

        class DeviceProfileAvatar: RscPicture {
            idc = 9302;
            text = "";
            x = 0.2453 * safezoneW + safezoneX;
            y = 0.415 * safezoneH + safezoneY;
            w = 0.09 * safezoneW;
            h = 0.14 * safezoneH;
            colorBackground[] = {0.08, 0.1, 0.12, 0.9};
        };

        class DeviceProfileName: RscStructuredText {
            idc = 9303;
            text = "<t size='0.55' color='#7a8c9e'>Compte non lié</t>";
            x = 0.345 * safezoneW + safezoneX;
            y = 0.415 * safezoneH + safezoneY;
            w = 0.385 * safezoneW;
            h = 0.14 * safezoneH;
        };

        // --- Vue "Effectifs" (BFT léger, façon FBCB2 cTab) : mêmes coordonnées que le bloc profil,
        // basculée avec lui (une seule vue visible à la fois — voir fn_deviceToggleView.sqf).
        class DeviceRosterTitle: RscStructuredText {
            idc = 9315;
            text = "<t size='0.5' color='#5a9e88'>EFFECTIFS EN LIAISON</t>";
            x = 0.2453 * safezoneW + safezoneX;
            y = 0.415 * safezoneH + safezoneY;
            w = 0.475 * safezoneW;
            h = 0.018 * safezoneH;
        };

        class DeviceRosterList: RscStructuredText {
            idc = 9314;
            text = "";
            x = 0.2453 * safezoneW + safezoneX;
            y = 0.436 * safezoneH + safezoneY;
            w = 0.475 * safezoneW;
            h = 0.119 * safezoneH;
        };

        class DeviceBtnHub: RscButton {
            idc = 9304;
            text = "Hub complet";
            x = 0.2453 * safezoneW + safezoneX;
            y = 0.575 * safezoneH + safezoneY;
            w = 0.15 * safezoneW;
            h = 0.034 * safezoneH;
            sizeEx = 0.024;
            colorBackground[] = {0.06, 0.14, 0.2, 0.95};
            action = "closeDialog 0; createDialog 'COMSPEC_Hub_Dialog';";
        };

        class DeviceBtnRoster: RscButton {
            idc = 9306;
            text = "Effectifs";
            x = 0.4053 * safezoneW + safezoneX;
            y = 0.575 * safezoneH + safezoneY;
            w = 0.15 * safezoneW;
            h = 0.034 * safezoneH;
            sizeEx = 0.024;
            colorBackground[] = {0.08, 0.16, 0.14, 0.95};
            action = "[findDisplay 9973] call comspec_overwatch_connect_fnc_deviceToggleView;";
        };

        class DeviceBtnClose: RscButton {
            idc = 9305;
            text = "Fermer";
            x = 0.5653 * safezoneW + safezoneX;
            y = 0.575 * safezoneH + safezoneY;
            w = 0.12 * safezoneW;
            h = 0.034 * safezoneH;
            sizeEx = 0.024;
            colorBackground[] = {0.12, 0.08, 0.08, 0.95};
            action = "closeDialog 0;";
        };
    };
};
