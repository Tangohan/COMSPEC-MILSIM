class CfgPatches {
    class comspec_overwatch_main {
        name = "COMSPEC Overwatch Main";
        units[] = {};
        weapons[] = {};
        requiredVersion = 1.0;
        requiredAddons[] = {"cba_main"};
        author = "COMSPEC";
        version = 1.20;
        versionStr = "1.2.0";
        versionAr[] = {1, 2, 0};
    };
};

class CfgMods {
    class COMSPEC_Overwatch {
        dir = "@COMSPECOverwatch";
        name = "COMSPEC Overwatch (BÊTA)";
        picture = "\z\comspec_overwatch\addons\connect\img\comspec_atak_logo.paa";
        actionName = "Website";
        action = "https://athena.ttrd.fr/public";
        overview = "Accès anticipé — Reliez Arma 3 à Athena : carte tactique, messagerie, tablette et téléphone.";
        tooltip = "COMSPEC Overwatch · BÊTA";
        author = "COMSPEC";
    };
};
