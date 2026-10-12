/*
    Attributs Eden — biométrie du sujet (moteur du pack SSE).

    Tout est facultatif : laissé sur « Automatique », le sujet reçoit des empreintes,
    des iris, un visage et un ADN générés, et sa présence en base dépend des taux du
    camp (réglages CBA › COMSPEC SSE › Biométrie — population).
    Variables écrites : COMSPEC_SSE_Bio* (voir comspec_sse_fnc_bioSetProfile).
*/
class COMSPEC_SSE_Biometrie {
    displayName = "COMSPEC SSE — Biométrie";
    collapsed = 1;
    class Attributes {
        class COMSPEC_SSE_BioDb {
            displayName = "Fichier biométrique";
            tooltip = "Présence dans la base et niveau de watchlist (BEWL). Automatique : selon le camp.";
            property = "COMSPEC_SSE_BioDb";
            control = "Combo";
            expression = "if (_value isNotEqualTo 'auto') then { _this setVariable ['COMSPEC_SSE_BioDb', _value, true]; };";
            defaultValue = "'auto'";
            typeName = "STRING";
            condition = "objectBrain";
            class values {
                class Auto { name = "Automatique (selon le camp)"; value = "auto"; default = 1; };
                class Absent { name = "Inconnu de toute base"; value = "absent"; };
                class Enrole { name = "Enrôlé, non signalé"; value = "enrole"; };
                class Bewl1 { name = "Watchlist tier 1 · DETAIN"; value = "bewl1"; };
                class Bewl2 { name = "Watchlist tier 2 · QUESTION / DETAIN"; value = "bewl2"; };
                class Bewl3 { name = "Watchlist tier 3 · QUESTION / DENY ACCESS"; value = "bewl3"; };
                class Bewl4 { name = "Watchlist tier 4 · pour info"; value = "bewl4"; };
            };
        };
        class COMSPEC_SSE_BioReason {
            displayName = "Motif de signalement";
            tooltip = "Affiché par le SEEK en cas de correspondance. Vide = motif généré selon le tier.";
            property = "COMSPEC_SSE_BioReason";
            control = "Edit";
            expression = "if (_value isNotEqualTo '') then { _this setVariable ['COMSPEC_SSE_BioReason', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain";
        };
        class COMSPEC_SSE_BioFalsePapers {
            displayName = "Faux papiers";
            tooltip = "Le nom déclaré (panneau Identité ou SSE) n'est pas le vrai : seule une correspondance biométrique le révèle.";
            property = "COMSPEC_SSE_BioFalsePapers";
            control = "Combo";
            expression = "if (_value isNotEqualTo 'auto') then { _this setVariable ['COMSPEC_SSE_BioFalsePapers', _value, true]; };";
            defaultValue = "'auto'";
            typeName = "STRING";
            condition = "objectBrain";
            class values {
                class Auto { name = "Automatique (taux du camp)"; value = "auto"; default = 1; };
                class Oui { name = "Oui"; value = "oui"; };
                class Non { name = "Non"; value = "non"; };
            };
        };
        class COMSPEC_SSE_BioTrueName {
            displayName = "Vraie identité (si faux papiers)";
            tooltip = "Prénom Nom réels, portés par son dossier biométrique. Vide avec « Faux papiers : oui » = nom généré.";
            property = "COMSPEC_SSE_BioTrueName";
            control = "Edit";
            expression = "if (_value isNotEqualTo '') then { _this setVariable ['COMSPEC_SSE_BioTrueName', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain";
        };
        class COMSPEC_SSE_BioId {
            displayName = "Identifiant biométrique (campagne)";
            tooltip = "Ex. HVT-ABU-HAMZA. Avec la même graine de campagne, ce personnage garde les mêmes empreintes, iris et ADN d'une mission à l'autre.";
            property = "COMSPEC_SSE_BioId";
            control = "Edit";
            expression = "if (_value isNotEqualTo '') then { _this setVariable ['COMSPEC_SSE_BioId', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain";
        };
        class COMSPEC_SSE_BioRelativeOf {
            displayName = "Parent de (nom de variable)";
            tooltip = "Nom de variable d'une autre unité (ou son identifiant biométrique). Leurs ADN partageront des allèles : le laboratoire signalera la parenté.";
            property = "COMSPEC_SSE_BioRelativeOf";
            control = "Edit";
            expression = "if (_value isNotEqualTo '') then { _this setVariable ['COMSPEC_SSE_BioRelativeOf', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain";
        };
        class COMSPEC_SSE_BioRelation {
            displayName = "Lien de parenté";
            tooltip = "Ce que ce sujet est pour l'unité désignée ci-dessus.";
            property = "COMSPEC_SSE_BioRelation";
            control = "Combo";
            expression = "_this setVariable ['COMSPEC_SSE_BioRelation', _value, true];";
            defaultValue = "'frere'";
            typeName = "STRING";
            condition = "objectBrain";
            class values {
                class Frere { name = "Frère / sœur"; value = "frere"; default = 1; };
                class Parent { name = "Parent"; value = "parent"; };
                class Enfant { name = "Enfant"; value = "enfant"; };
            };
        };
        class COMSPEC_SSE_BioMissingFingers {
            displayName = "Doigts manquants";
            tooltip = "Numérotation FBI : 1 pouce D … 5 auriculaire D, 6 pouce G … 10 auriculaire G. Ex. 2,7";
            property = "COMSPEC_SSE_BioMissingFingers";
            control = "Edit";
            expression = "if (_value isNotEqualTo '') then { _this setVariable ['COMSPEC_SSE_BioMissingFingers', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain";
        };
        class COMSPEC_SSE_BioIrisState {
            displayName = "Iris";
            tooltip = "Automatique : cataracte possible après 50 ans, prothèse rarissime.";
            property = "COMSPEC_SSE_BioIrisState";
            control = "Combo";
            expression = "if (_value isNotEqualTo 'auto') then { _this setVariable ['COMSPEC_SSE_BioIrisState', _value, true]; };";
            defaultValue = "'auto'";
            typeName = "STRING";
            condition = "objectBrain";
            class values {
                class Auto { name = "Automatique"; value = "auto"; default = 1; };
                class Cataracte { name = "Cataracte"; value = "cataracte"; };
                class ProtheseOD { name = "Prothèse œil droit"; value = "prothese_od"; };
                class ProtheseOG { name = "Prothèse œil gauche"; value = "prothese_og"; };
                class LesionOD { name = "Lésion œil droit"; value = "lesion_od"; };
                class LesionOG { name = "Lésion œil gauche"; value = "lesion_og"; };
            };
        };
        class COMSPEC_SSE_BioLatentOwners {
            displayName = "Empreintes latentes de (objets)";
            tooltip = "Sur un objet : noms de variable des unités dont les empreintes s'y trouvent, séparés par des virgules (arme, téléphone, pièce d'IED…).";
            property = "COMSPEC_SSE_BioLatentOwners";
            control = "Edit";
            expression = "if (_value isNotEqualTo '') then { _this setVariable ['COMSPEC_SSE_BioLatentOwners', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectSimulated + objectVehicle";
        };
    };
};
