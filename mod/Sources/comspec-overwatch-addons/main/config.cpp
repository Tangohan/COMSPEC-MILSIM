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
        // Icône temporaire (tablette Athena, déjà utilisée dans l'UI) — à remplacer par un vrai
        // logo dédié dès qu'un asset .paa est fourni (aucun outil de génération/encodage PAA
        // disponible pour en produire un ici).
        picture = "\z\comspec_overwatch\addons\connect\img\device\comspec_icon_tablet.paa";
        actionName = "Website";
        action = "https://athena.ttrd.fr/public";
        overview = "Accès anticipé — Reliez Arma 3 à Athena : carte tactique, messagerie, tablette et téléphone.";
        tooltip = "COMSPEC Overwatch · BÊTA";
        author = "COMSPEC";
    };
};
