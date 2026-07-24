/*
    Modules Zeus/Eden pour créer des zones roleplay.
*/

// Module : Zone sans couverture
class COMSPEC_Module_NoCoverage : Module_F
{
    scope = 2;
    displayName = "Zone sans couverture ATAK";
    icon = "\A3\ui_f\data\map\markers\military\warning_CA.paa";
    category = "COMSPEC_Roleplay";
    function = "comspec_overwatch_connect_fnc_moduleNoCoverage";
    functionPriority = 1;
    isGlobal = 1;
    isTriggerActivated = 0;
    isDisposable = 1;
    
    class Arguments
    {
        class Radius
        {
            displayName = "Rayon (mètres)";
            description = "Rayon de la zone en mètres";
            typeName = "NUMBER";
            defaultValue = 200;
        };
        
        class Intensity
        {
            displayName = "Intensité (%)";
            description = "Intensité de l'effet (0-100)";
            typeName = "NUMBER";
            defaultValue = 100;
        };
    };
    
    class ModuleDescription
    {
        description = "Zone où la liaison ATAK est totalement coupée (hors couverture réseau)";
        sync[] = {};
    };
};

// Module : Zone d'interférence
class COMSPEC_Module_Interference : Module_F
{
    scope = 2;
    displayName = "Zone d'interférence ATAK";
    icon = "\A3\ui_f\data\map\markers\military\warning_CA.paa";
    category = "COMSPEC_Roleplay";
    function = "comspec_overwatch_connect_fnc_moduleInterference";
    functionPriority = 1;
    isGlobal = 1;
    isTriggerActivated = 0;
    isDisposable = 1;
    
    class Arguments
    {
        class Radius
        {
            displayName = "Rayon (mètres)";
            description = "Rayon de la zone en mètres";
            typeName = "NUMBER";
            defaultValue = 300;
        };
        
        class Intensity
        {
            displayName = "Intensité (%)";
            description = "Intensité de l'interférence (0-100)";
            typeName = "NUMBER";
            defaultValue = 50;
        };
    };
    
    class ModuleDescription
    {
        description = "Zone avec forte interférence radio (packet loss élevé)";
        sync[] = {};
    };
};

// Module : Zone dégradée
class COMSPEC_Module_Degraded : Module_F
{
    scope = 2;
    displayName = "Zone de couverture dégradée";
    icon = "\A3\ui_f\data\map\markers\military\warning_CA.paa";
    category = "COMSPEC_Roleplay";
    function = "comspec_overwatch_connect_fnc_moduleDegraded";
    functionPriority = 1;
    isGlobal = 1;
    isTriggerActivated = 0;
    isDisposable = 1;
    
    class Arguments
    {
        class Radius
        {
            displayName = "Rayon (mètres)";
            description = "Rayon de la zone en mètres";
            typeName = "NUMBER";
            defaultValue = 500;
        };
        
        class Intensity
        {
            displayName = "Intensité (%)";
            description = "Intensité de la dégradation (0-100)";
            typeName = "NUMBER";
            defaultValue = 30;
        };
    };
    
    class ModuleDescription
    {
        description = "Zone de couverture dégradée (latence + packet loss modéré)";
        sync[] = {};
    };
};

// Module : Brouilleur
class COMSPEC_Module_Jammer : Module_F
{
    scope = 2;
    displayName = "Brouilleur ATAK actif";
    icon = "\A3\ui_f\data\map\markers\military\warning_CA.paa";
    category = "COMSPEC_Roleplay";
    function = "comspec_overwatch_connect_fnc_moduleJammer";
    functionPriority = 1;
    isGlobal = 1;
    isTriggerActivated = 0;
    isDisposable = 1;
    
    class Arguments
    {
        class Radius
        {
            displayName = "Rayon (mètres)";
            description = "Rayon du brouilleur en mètres";
            typeName = "NUMBER";
            defaultValue = 400;
        };
        
        class Intensity
        {
            displayName = "Puissance (%)";
            description = "Puissance du brouilleur (0-100)";
            typeName = "NUMBER";
            defaultValue = 80;
        };
    };
    
    class ModuleDescription
    {
        description = "Brouilleur radio actif (déconnexions intermittentes)";
        sync[] = {};
    };
};
