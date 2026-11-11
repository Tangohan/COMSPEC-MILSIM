#include "script_component.hpp"

/*
    COMSPEC IDENTITY : documents d'identité fictifs, contrôle par ACE et par l'app « Contrôle ID » de l'ATAK.
    Lot 2 : photo du document jointe à la FRM, SALUTE / SPOTREP pré-remplis, contrôles notables au compte rendu AAR.
    Lot 3 : IA réalistes (machine à états CBA, fn_idAiFsm), checkpoints IA (module Eden, Zeus), réglages CBA.
    Lot 4 : couvertures et infiltrés (légende fn_idLegend, cohérence et couverture brûlée fn_idCover, questions de légende
            fn_idQuestion, app « Ma couverture » fn_idCoverPage).
    Lot 5 : HVT (comparaison du visage N4 fn_idBio, PID, JACKPOT, BOLO fn_idHvt) branchées sur la chasse HVT de l'ATAK.

    Vérité (vraie identité, couverture, faux, qualité) : uniquement sur le serveur (fn_idRegistry, jamais diffusée).
    Chaque exemplaire matérialisé est un objet d'inventaire d'une classe libre (CfgWeapons_instances.hpp).
    Contrôle par niveaux : N0 visuel, N1 MRZ, N2 puce NFC, N3 base (locale hors ligne, Athena en ligne).
*/
class CfgPatches {
    class comspec_sse_identity {
        name = "COMSPEC SSE - Identity";
        units[] = {"COMSPEC_SSE_Module_IdCheckpoint"};
        weapons[] = {"COMSPEC_ID_Card", "COMSPEC_ID_Passport", "COMSPEC_ID_DrivingLicense", "COMSPEC_ID_MilitaryCard", "COMSPEC_ID_ResidencePermit", "COMSPEC_ID_VehicleRegistration"};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {
            "comspec_sse_core",
            "comspec_sse_generator",
            "comspec_sse_biometrics",
            "comspec_sse_interaction",
            "comspec_sse_ui",
            "comspec_sse_network",
            "comspec_sse_main",
            "comspec_sse_zeus",
            "cba_main",
            "cba_xeh",
            "cba_statemachine",
            "ace_interact_menu",
            "comspec_atak_native_main"
        };
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";
        class identity {
            file = "z\comspec_sse\addons\identity\functions";
            class idInitSettings {};
            class idRegistry {};
            class idCountries {};
            class idRealCountries {};
            // Pays réels (registre CfgComspecCountries) : numéros à clé, essais du registre (console de débogage).
            class idNumbers {};
            class idCountryChrome {};
            class idCountrySelfTest {};
            class idGenerate {};
            class idDocNumber {};
            class idMrz {};
            class idMrzCheck {};
            class idIssue {};
            class idMaterialize {};
            class idDocsOf {};
            class idServer {};
            class idClient {};
            class idEvaluate {};
            class idAiAnswer {};
            class idViewerOpen {};
            class idViewerOnLoad {};
            class idFace {};
            class idDocTypes {};
            class idDocPicker {};
            class idEvidence {};
            class idPage {};
            class idAction {};
            class idHistory {};
            class idAthenaPack {};
            class idAthenaSubmit {};
            class idFrm {};
            class idModuleGive {};
            class idZenRegister {};
            class initAceIdentity {};
            class idCanSearch {};
            class idActionChildren {};
            class idPresentPrompt {};
            class idTransfer {};
            class idVehicleOccupants {};
            class idKit {};
            class idAar {};
            class idAtakAtLeast {};
            class idPhoto {};
            class idReport {};
            class idAiFsm {};
            class idAiBrain {};
            class idAiEnter {};
            class idAiSay {};
            class idAiReinforce {};
            class idCheckpoint {};
            class idCheckpointModule {};
            class idLegend {};
            class idCover {};
            class idQuestion {};
            class idCoverPage {};
            class idCoverSync {};
            class idCoverImport {};
            class idBio {};
            class idHvt {};
            class idAiConfig {};
            // API publique des papiers en jeu (mods et scripts, serveur) : identité Athena liée, propriétaire, suivi.
            class idPaperOf {};
            class idPaperOwner {};
            class idPaperLink {};
            class idPaperGive {};
            class idPaperTrack {};
            // Passage aux fichiers (app Fichiers, SEEK II, relevés) et animation des analyses dans le téléphone.
            class fileServer {};
            class fileClient {};
            class fileQuery {};
            class fileMerge {};
            class fileSubmit {};
            class filePage {};
            class fileAction {};
            class controlRecord {};
            class scanAnim {};
        };
    };
};

// Demandes du joueur au serveur (le demandeur est retrouvé par remoteExecutedOwner) ; réponses du serveur au joueur.
class CfgRemoteExec {
    class Functions {
        class comspec_sse_fnc_idServer { allowedTargets = 2; };
        class comspec_sse_fnc_idClient { allowedTargets = 1; };
        class comspec_sse_fnc_fileServer { allowedTargets = 2; };
        class comspec_sse_fnc_fileClient { allowedTargets = 1; };
    };
};

// App « Contrôle ID » du téléphone ATAK, déclarée depuis le SSE (le main.pbo de l'ATAK n'est pas touché).
class COMSPEC_ATAK_Apps {
    class IdCheck { name="Contrôle ID"; page="IDCHECK"; function="comspec_sse_fnc_idPage"; section="Renseignement"; order=120; dock=0; new=1; icon="\z\comspec_sse\addons\identity\data\app_id.paa"; };
    class Fichiers { name="Fichiers"; page="FICHIERS"; function="comspec_sse_fnc_filePage"; section="Renseignement"; order=119; dock=0; new=1; icon="\z\comspec_atak_native\addons\main\data\app_intel.paa"; desc="Passage aux fichiers : personne, document, biométrie SEEK II, véhicule (HIT / NO HIT, mentions, contrôles subis)"; };
    class IdCover { name="Ma couverture"; page="IDCOVER"; function="comspec_sse_fnc_idCoverPage"; section="Renseignement"; order=121; dock=0; new=1; icon="\z\comspec_sse\addons\identity\data\icon_card_ca.paa"; desc="Votre identité de couverture : légende, contacts, cohérence, questions des contrôleurs"; };
};

class Extended_PreInit_EventHandlers {
    class comspec_sse_identity {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\identity\XEH_preInit.sqf'";
    };
};

class Extended_PostInit_EventHandlers {
    class comspec_sse_identity {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\identity\XEH_postInit.sqf'";
        clientInit = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\identity\XEH_postInitClient.sqf'";
    };
};

// Personne qui apparaît : papiers imposés par l'attribut Eden ou objets du kit (documents vierges du catalogue dans le
// paquetage) délivrés à son nom, une seconde plus tard (clé biométrique attribuée, attributs Eden appliqués).
// Les IA sans attribut : rien ici, papiers décidés au premier contrôle (matérialisation paresseuse).
class Extended_InitPost_EventHandlers {
    class CAManBase {
        class comspec_sse_identity {
            serverInit = "[{ ['kit', [_this select 0]] call comspec_sse_fnc_idRegistry; }, [_this select 0], 1] call CBA_fnc_waitAndExecute;";
        };
    };
};

class CfgWeapons {
    #include "CfgWeapons.hpp"
};

class CfgVehicles {
    #include "modules_identity.hpp"
};

#include "CfgComspecDocuments.hpp"
// Pays des papiers (France, Allemagne, États-Unis… puis pays fictifs d'Arma) : généré par tools/countries/gen_countries.py.
#include "CfgComspecCountries.hpp"

class RscText;
class RscButton;
class RscStructuredText;
class RscPicture;
class RscListBox;
class RscEdit;
class RscCombo;
class RscXSliderH;
class RscCheckBox;

#include "dialogs\idViewer.hpp"
#include "dialogs\docPicker.hpp"
