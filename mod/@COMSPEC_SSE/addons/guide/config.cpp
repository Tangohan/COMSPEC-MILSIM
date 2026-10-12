#include "script_component.hpp"

/*
    Tutoriels du SSE :
      - manuel de terrain (Field Manual, catégorie COMSPEC SSE), lisible dans Eden, en jeu et au Zeus ;
      - menu « COMSPEC SSE » dans la barre de menus d'Eden ;
      - Zeus : manuel et exercice complet posé en un clic ;
      - ACE : « Guide SSE (manuel) ».
*/
class CfgPatches {
    class comspec_sse_guide {
        name = "COMSPEC SSE - Tutoriels";
        units[] = {};
        weapons[] = {};
        requiredVersion = REQUIRED_VERSION;
        requiredAddons[] = {"comspec_sse_main", "comspec_sse_analysis", "comspec_sse_campaign", "comspec_sse_zeus", "3DEN", "cba_xeh"};
        author = "COMSPEC";
        VERSION_CONFIG;
    };
};

class CfgFunctions {
    class comspec_sse {
        tag = "comspec_sse";
        class guide {
            file = "z\comspec_sse\addons\guide\functions";
            class guideExercise {};
        };
    };
};

class Extended_PostInit_EventHandlers {
    class comspec_sse_guide {
        init = "call compile preprocessFileLineNumbers 'z\comspec_sse\addons\guide\XEH_postInit.sqf'";
    };
};

#define HINT_IMG "\a3\ui_f\data\igui\cfg\simpleTasks\types\intel_ca.paa"

class CfgHints {
    class COMSPEC_SSE {
        displayName = "COMSPEC SSE";
        logicalOrder = 90;
        class Start {
            displayName = "Prise en main";
            displayNameShort = "Prise en main";
            description = "Le SSE (Sensitive Site Exploitation) exploite tout ce qu'on trouve sur un objectif : personnes, corps, documents, téléphones, ordinateurs, caches.<br/><br/><t color='#7fd08a'>Où sont les actions</t><br/>Menu ACE (touche Windows) sur une personne ou un objet : COMSPEC SSE. Menu ACE personnel (Ctrl+Windows) : COMSPEC SSE pour le journal, le terminal, le tableau des liens, le registre des scellés, la campagne et ce guide.<br/><br/><t color='#7fd08a'>Le matériel</t><br/>Terminal SEEK II (relevés biométriques), kit d'empreintes, kit ADN, appareil photo, sachets de preuve, gants, sac de tête. « Équiper le kit SSE » dans le menu personnel donne le kit de base.<br/><br/><t color='#7fd08a'>Raccourci</t><br/>Ctrl+Maj+S ouvre le terminal SEEK.";
            tip = "Chaque chapitre de ce manuel correspond à une étape réelle d'un SSE.";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Bio {
            displayName = "Relevés biométriques";
            displayNameShort = "Biométrie";
            description = "Chaque personnage a ses propres empreintes (dix doigts, minuties, classification de Henry), ses iris, son visage et son ADN. Deux personnages différents ne partagent jamais une biométrie.<br/><br/><t color='#7fd08a'>Relever</t><br/>Sur le sujet, menu ACE, SSE : empreintes, iris, visage, ADN, ou tout. Sur un corps, l'opérateur s'agenouille ; un sujet debout et coopérant tend la main.<br/><br/><t color='#7fd08a'>La qualité compte</t><br/>Nuit, pluie, sujet agité, corps ancien, mains ou visage blessés (ACE) dégradent la qualité ; trop abîmé, le relevé est impossible. Un mauvais relevé peut rater la bonne personne ou sortir un faux candidat « à confirmer ».<br/><br/><t color='#7fd08a'>Interroger la base</t><br/>Hors liaison, le terminal ne connaît que sa base locale. En liaison, la recherche part sur l'ABIS. Résultats : MATCH, correspondance possible, NO MATCH, alerte de faux papiers. L'ADN part au laboratoire : résultat différé.";
            tip = "Une identification certaine confirme l'appartenance du sujet à son organisation.";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Capture {
            displayName = "Capture et sac de tête";
            displayNameShort = "Sac de tête";
            description = "Un prisonnier menotté, rendu, inconscient ou mort peut recevoir un sac de tête (objet « Sac de tête », menu ACE, SSE). Il ne voit plus rien, entend étouffé ; son visage et ses iris ne peuvent plus être relevés tant que le sac est en place.<br/><br/>Ordre conseillé : relevés d'abord (visage, iris, empreintes), puis sac de tête, puis transfert.";
            tip = "Le modèle du sac se règle dans les réglages CBA (COMSPEC SSE, Biométrie).";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Evidence {
            displayName = "Saisie et scellés";
            displayNameShort = "Scellés";
            description = "<t color='#7fd08a'>La chaîne de possession</t><br/>Collecter un objet ouvre une pièce (numéro P-dossier-0001). La mettre sous scellé lui donne un numéro de scellé (SC-...). Chaque geste s'inscrit au journal de la pièce : date, heure, qui, où.<br/><br/>Exploiter une pièce scellée brise d'abord le scellé (inscrit) ; pensez à la resceller : nouveau numéro, l'ancien reste au journal.<br/><br/><t color='#7fd08a'>Remettre les pièces</t><br/>Menu ACE sur un équipier : « Remettre mes pièces scellées ». La remise est inscrite et le détenteur change.<br/><br/><t color='#7fd08a'>Un journal infalsifiable</t><br/>Chaque ligne porte l'empreinte de la précédente. Une ligne modifiée après coup apparaît en rouge au registre (menu personnel, Registre des scellés).";
            tip = "« Pièces que je détiens » liste ce que vous portez.";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Links {
            displayName = "Organisations et tableau des liens";
            displayNameShort = "Tableau des liens";
            description = "Les personnages appartiennent à des organisations : réseau, cellule, clan, milice... avec un chef, des cellules et des rôles (financier, artificier, courrier, guetteur...).<br/><br/><t color='#7fd08a'>Découvrir le réseau</t><br/>Rien n'est connu au départ. Une personne contrôlée entre au tableau. Une identification biométrique confirme son appartenance. Exploiter une personne (téléphone, carnet) fait soupçonner des membres de sa cellule. Exploiter un objet rattaché à une organisation la révèle.<br/><br/><t color='#7fd08a'>Le tableau</t><br/>Menu personnel, Tableau des liens. GRAPHE : tout ce qui est relié. ORBAT : organigramme, du chef au sympathisant. Filtres : personnes, organisations, objets et données, confirmé seul. Clic : fiche du nœud ; double-clic : zoom. Un « ? » signale ce qui n'est que soupçonné.";
            tip = "Le Zeus voit tout le réseau, ce que vous ignorez en gris.";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Digital {
            displayName = "Numérique et câble DL-7";
            displayNameShort = "Numérique";
            description = "Téléphones, ordinateurs, clés USB et radios contiennent des fichiers, des messages, des contacts, des identifiants et des lieux générés par le SSE.<br/><br/>Avec l'ATAK natif et le câble DL-7, un opérateur se branche sur un appareil et en télécharge le contenu : mot de passe, indice, forçage, alarme, effacement selon ce que le chef de mission a prévu.";
            tip = "Le contenu numérique du SSE devient extractible au DL-7 sans rien saisir.";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Campaigns {
            displayName = "Campagnes";
            displayNameShort = "Campagnes";
            description = "Une campagne garde d'une mission à l'autre sa graine biométrique, ses personnages récurrents, ses organisations, ce que les joueurs savent et le registre des scellés.<br/><br/><t color='#7fd08a'>Permanente</t> la vraie campagne : tout est gardé.<br/><t color='#7fd08a'>Récurrente</t> le bootcamp : mêmes personnages et organisations à chaque drill, tout redevient inconnu au chargement.<br/><t color='#7fd08a'>Temporaire</t> drill SOT : bac à sable jetable, rien n'est gardé, vidé à la fin ; il peut copier la structure d'une autre campagne.<br/><br/>Un personnage récurrent a un identifiant biométrique fixé (attribut Eden) ou une clé BII : même nom, même alias, mêmes empreintes, même appartenance sur n'importe quelle carte.";
            tip = "Menu personnel, Campagne en cours : type, mission n°, personnages récurrents, journal.";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Eden {
            displayName = "Mise en place dans Eden";
            displayNameShort = "Eden";
            description = "<t color='#7fd08a'>1. Campagne</t> Module « Campagne SSE » (Mission) : identifiant, type, sauvegarde automatique.<br/><t color='#7fd08a'>2. Personnes</t> Attributs d'une unité : catégories COMSPEC SSE (identité, biométrie, identifiant fixé pour un personnage récurrent) et Organisation (organisation, cellule, rôle, nom de guerre, ce que les joueurs savent).<br/><t color='#7fd08a'>3. Organisations</t> Module « Organisation » (Personnes) : synchronisez des personnes ou le chef d'un groupe ; rôles saisis ou générés en cellules ; synchronisez aussi les objets qui lui appartiennent.<br/><t color='#7fd08a'>4. Objets</t> Tout objet peut être SSE : attributs COMSPEC SSE (nature imposée, richesse), « Rattaché à l'organisation », Extraction DL-7.<br/><t color='#7fd08a'>5. Sites</t> Modules Sites et objets pour générer un site complet.<br/><t color='#7fd08a'>6. Fin</t> Module « Campagne SSE : sauvegarder » synchronisé à un déclencheur.";
            tip = "Menu COMSPEC SSE de la barre d'Eden : ce manuel à tout moment.";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Zeus {
            displayName = "Conduite au Zeus";
            displayNameShort = "Zeus";
            description = "Modules ZEN « COMSPEC SSE » :<br/><t color='#7fd08a'>Mission</t> campagne (charger, sauvegarder, état, remise à zéro, fin, export, import), dossier actif, présentation des documents, altérer un scellé (exercice).<br/><t color='#7fd08a'>Personnes</t> identité d'un sujet, biométrie, organisations (affecter, générer un réseau sur une sélection ou un groupe, rattacher des objets, révéler, retirer), tableau des liens vue Zeus.<br/><t color='#7fd08a'>Numérique</t> DOMEX, rendre extractible au DL-7.<br/><t color='#7fd08a'>Outils</t> ce manuel, et « poser un exercice ici » : une cellule complète prête à exploiter.<br/><br/>Le tableau des liens vue Zeus montre tout, ce que les joueurs ignorent en gris ; RÉVÉLER rend un nœud connu des joueurs.";
            tip = "Pour un drill SOT : campagne temporaire, puis « poser un exercice ici ».";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
        class Custom {
            displayName = "Personnalisation";
            displayNameShort = "Personnalisation";
            description = "Réglages CBA (Échap, Options, Réglages de l'addon, COMSPEC SSE) :<br/>· Biométrie : seuils, qualité, population, graine de campagne, modèle du sac de tête ;<br/>· Organisations et scellés : rôles et natures d'organisation en plus (identifiant:Libellé:rang), noms de code des cellules, nombre de contacts révélés par exploitation, confirmation par la biométrie, préfixes des pièces et des scellés.<br/><br/>Les profils et thèmes de génération (insurgé, criminel, étatique...) se choisissent dans chaque module.";
            tip = "Les rôles ajoutés apparaissent dans les formulaires Zeus.";
            arguments[] = {};
            image = HINT_IMG;
            noImage = 0;
        };
    };
};

// Barre de menus d'Eden : COMSPEC SSE.
class ctrlMenuStrip;
class Display3DEN {
    class Controls {
        class MenuStrip: ctrlMenuStrip {
            class Items {
                items[] += {"COMSPEC_SSE_Menu"};
                class COMSPEC_SSE_Menu {
                    text = "COMSPEC SSE";
                    items[] = {"COMSPEC_SSE_MenuEden", "COMSPEC_SSE_MenuStart", "COMSPEC_SSE_MenuLinks", "COMSPEC_SSE_MenuCampaigns", "COMSPEC_SSE_MenuZeus", "COMSPEC_SSE_MenuCustom"};
                };
                class COMSPEC_SSE_MenuEden { text = "Mise en place d'une mission"; action = "['COMSPEC_SSE', 'Eden'] call BIS_fnc_openFieldManual"; };
                class COMSPEC_SSE_MenuStart { text = "Prise en main (joueurs)"; action = "['COMSPEC_SSE', 'Start'] call BIS_fnc_openFieldManual"; };
                class COMSPEC_SSE_MenuLinks { text = "Organisations et tableau des liens"; action = "['COMSPEC_SSE', 'Links'] call BIS_fnc_openFieldManual"; };
                class COMSPEC_SSE_MenuCampaigns { text = "Campagnes"; action = "['COMSPEC_SSE', 'Campaigns'] call BIS_fnc_openFieldManual"; };
                class COMSPEC_SSE_MenuZeus { text = "Conduite au Zeus"; action = "['COMSPEC_SSE', 'Zeus'] call BIS_fnc_openFieldManual"; };
                class COMSPEC_SSE_MenuCustom { text = "Personnalisation"; action = "['COMSPEC_SSE', 'Custom'] call BIS_fnc_openFieldManual"; };
            };
        };
    };
};
