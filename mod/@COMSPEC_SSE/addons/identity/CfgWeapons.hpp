// Documents d'identité fictifs (inventaire ACE / Arsenal : CBA MiscItem). Inclus dans class CfgWeapons (config.cpp).
// Classes du catalogue (scope = 2) : un objet pris à l'Arsenal ou au Zeus est un document vierge, sans titulaire ;
// le registre du serveur ne connaît que les exemplaires (CfgWeapons_instances.hpp) qu'il a lui-même délivrés.
// Noms d'objets sans la mention : SIMULATION est imprimé sur le papier lui-même (gabarits, visionneuse).
class CBA_MiscItem;
class CBA_MiscItem_ItemInfo;

class COMSPEC_ID_Base: CBA_MiscItem {
    scope = 0;
    author = "COMSPEC";
    picture = "\z\comspec_sse\addons\identity\data\icon_card_ca.paa";
    model = "\A3\Props_F_Oldman\Items\Documents\Creditcard_01_F.p3d";
    descriptionShort = "$STR_COMSPEC_SSE_CFG_F3E840A9AB";
    class ItemInfo: CBA_MiscItem_ItemInfo {
        mass = 0.1;
    };
};

class COMSPEC_ID_Card: COMSPEC_ID_Base {
    scope = 2;
    displayName = "$STR_COMSPEC_SSE_CFG_B8E7E90D9A";
    picture = "\z\comspec_sse\addons\identity\data\icon_card_ca.paa";
};

class COMSPEC_ID_MilitaryCard: COMSPEC_ID_Base {
    scope = 2;
    displayName = "$STR_COMSPEC_SSE_CFG_ID_MILITARYCARD";
    picture = "\z\comspec_sse\addons\identity\data\icon_military_ca.paa";
};

class COMSPEC_ID_Passport: COMSPEC_ID_Base {
    scope = 2;
    displayName = "$STR_COMSPEC_SSE_CFG_BBFD0B835C";
    picture = "\z\comspec_sse\addons\identity\data\icon_passport_ca.paa";
    model = "\A3\Structures_F\Items\Documents\File2_F.p3d";
    class ItemInfo: CBA_MiscItem_ItemInfo { mass = 0.3; };
};

class COMSPEC_ID_DrivingLicense: COMSPEC_ID_Base {
    scope = 2;
    displayName = "$STR_COMSPEC_SSE_CFG_5D056C9123";
    picture = "\z\comspec_sse\addons\identity\data\icon_license_ca.paa";
};

// Pays réels (registre CfgComspecCountries) : titre de séjour et certificat d'immatriculation.
class COMSPEC_ID_ResidencePermit: COMSPEC_ID_Base {
    scope = 2;
    displayName = "$STR_COMSPEC_SSE_CFG_ID_RESIDENCEPERMIT";
    picture = "\z\comspec_sse\addons\identity\data\icon_residence_ca.paa";
};

class COMSPEC_ID_VehicleRegistration: COMSPEC_ID_Base {
    scope = 2;
    displayName = "$STR_COMSPEC_SSE_CFG_ID_VEHICLEREG";
    picture = "\z\comspec_sse\addons\identity\data\icon_vreg_ca.paa";
    model = "\A3\Structures_F\Items\Documents\File2_F.p3d";
};

#include "CfgWeapons_instances.hpp"
