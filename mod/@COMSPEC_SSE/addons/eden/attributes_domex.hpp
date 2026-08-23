class COMSPEC_SSE_Domex {
    displayName = "Athena — Intelligence numérique";
    collapsed = 1;
    class Attributes {
        class comspec_sse_domex_enabled {
            displayName = "Support exploitable (DOMEX)";
            tooltip = "Marque cet objet comme nœud de renseignement numérique. Pas un outil de piratage : le contenu est écrit par le scénario.";
            property = "comspec_sse_domex_enabled";
            control = "Checkbox";
            expression = "_this setVariable ['comspec_sse_domex_enabled', _value, true]; if (_value) then { _this setVariable ['comspec_sse_enabled', true, true]; };";
            defaultValue = "false";
            typeName = "BOOL";
            condition = "objectSimulated - objectBrain";
        };
        class comspec_sse_domex_nodeId {
            displayName = "Identifiant";
            tooltip = "Référence stable du support (ex. PC-KESTREL-04). Sert de lien entre la mission et le laboratoire.";
            property = "comspec_sse_domex_nodeId";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_domex_nodeId', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
        };
        class comspec_sse_domex_deviceType {
            displayName = "Type de support";
            property = "comspec_sse_domex_deviceType";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_deviceType', _value, true];";
            defaultValue = "'ordinateur'";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class ordinateur { name = "Ordinateur"; value = "ordinateur"; default = 1; };
                class telephone { name = "Téléphone"; value = "telephone"; };
                class tablette { name = "Tablette"; value = "tablette"; };
                class radio_numerique { name = "Radio"; value = "radio_numerique"; };
                class disque_dur { name = "Disque dur"; value = "disque_dur"; };
                class cle_usb { name = "Clé USB"; value = "cle_usb"; };
                class gps { name = "GPS"; value = "gps"; };
                class appareil_photo { name = "Appareil photo"; value = "appareil_photo"; };
                class support_amovible { name = "Autre support"; value = "support_amovible"; };
            };
        };
        class comspec_sse_domex_owner {
            displayName = "Propriétaire apparent";
            tooltip = "Nom ou fiche déjà connue (ex. HASSAN Karim). Ce n’est pas une preuve d’attribution.";
            property = "comspec_sse_domex_owner";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_domex_owner', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
        };
        class comspec_sse_domex_org {
            displayName = "Organisation";
            property = "comspec_sse_domex_org";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_domex_org', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
        };
        class comspec_sse_domex_network {
            displayName = "Réseau fictif";
            tooltip = "Nom du réseau de scénario (ex. KESTREL-LAN). Aucune technique réelle n’est simulée.";
            property = "comspec_sse_domex_network";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_domex_network', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
        };
        class comspec_sse_domex_exploitable {
            displayName = "Exploitable";
            property = "comspec_sse_domex_exploitable";
            control = "Checkbox";
            expression = "_this setVariable ['comspec_sse_domex_exploitable', _value, true];";
            defaultValue = "true";
            typeName = "BOOL";
            condition = "objectSimulated - objectBrain";
        };
        class comspec_sse_domex_accessPhysical {
            displayName = "Accès physique possible";
            property = "comspec_sse_domex_accessPhysical";
            control = "Checkbox";
            expression = "_this setVariable ['comspec_sse_domex_accessPhysical', _value, true];";
            defaultValue = "true";
            typeName = "BOOL";
            condition = "objectSimulated - objectBrain";
        };
        class comspec_sse_domex_accessRemote {
            displayName = "Accès distant scénarisé";
            tooltip = "Si coché, une partie du contenu peut être révélée sans saisie physique, plus pauvre que l’accès de près.";
            property = "comspec_sse_domex_accessRemote";
            control = "Checkbox";
            expression = "_this setVariable ['comspec_sse_domex_accessRemote', _value, true];";
            defaultValue = "false";
            typeName = "BOOL";
            condition = "objectSimulated - objectBrain";
        };
        class comspec_sse_domex_security {
            displayName = "Sécurité scénarisée";
            tooltip = "Durée et pauvreté du contenu à distance. Ce n’est pas un vrai chiffrement.";
            property = "comspec_sse_domex_security";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_security', _value, true];";
            defaultValue = "'moyenne'";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class faible { name = "Faible"; value = "faible"; };
                class moyenne { name = "Moyenne"; value = "moyenne"; default = 1; };
                class elevee { name = "Élevée"; value = "elevee"; };
            };
        };
        class comspec_sse_domex_profile {
            displayName = "Profil de contenu";
            property = "comspec_sse_domex_profile";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_profile', _value, true];";
            defaultValue = "'generique'";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class generique { name = "Générique"; value = "generique"; default = 1; };
                class logistique { name = "Logistique"; value = "logistique"; };
                class commandement { name = "Commandement"; value = "commandement"; };
                class personnel { name = "Personnel"; value = "personnel"; };
                class radio { name = "Radio / liaisons"; value = "radio"; };
            };
        };
        class comspec_sse_domex_duration {
            displayName = "Durée d’exploitation";
            property = "comspec_sse_domex_duration";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_duration', _value, true];";
            defaultValue = "'180'";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class t30 { name = "30 secondes"; value = "30"; };
                class t60 { name = "1 minute"; value = "60"; };
                class t120 { name = "2 minutes"; value = "120"; };
                class t180 { name = "3 minutes"; value = "180"; default = 1; };
            };
        };
        class comspec_sse_domex_stage {
            displayName = "Palier d’accès (départ)";
            tooltip = "État de départ. Le chef de mission peut le changer en cours de partie.";
            property = "comspec_sse_domex_stage";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_stage', _value, true];";
            defaultValue = "'non_identifie'";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class non_identifie { name = "Non identifié"; value = "non_identifie"; default = 1; };
                class decouvert { name = "Découvert"; value = "decouvert"; };
                class acces_en_cours { name = "Accès en cours"; value = "acces_en_cours"; };
                class acces_etabli { name = "Accès établi"; value = "acces_etabli"; };
                class exploite { name = "Exploité"; value = "exploite"; };
            };
        };
        class comspec_sse_domex_p1_type {
            displayName = "Paquet 1 — type";
            property = "comspec_sse_domex_p1_type";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_p1_type', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class none { name = "(aucun)"; value = ""; default = 1; };
                class message { name = "Message"; value = "message"; };
                class document { name = "Document"; value = "document"; };
                class photo { name = "Photographie"; value = "photo"; };
                class contact { name = "Contact"; value = "contact"; };
                class coordinate { name = "Coordonnée / point"; value = "coordinate"; };
                class frequency { name = "Fréquence"; value = "frequency"; };
                class schedule { name = "Horaire"; value = "schedule"; };
                class manifest { name = "Manifeste"; value = "manifest"; };
                class objective { name = "Objectif"; value = "objective"; };
            };
        };
        class comspec_sse_domex_p1_text {
            displayName = "Paquet 1 — texte";
            tooltip = "Ce que le joueur lira. Les entités liées se saisissent ci-dessous, une par ligne.";
            property = "comspec_sse_domex_p1_text";
            control = "EditMulti5";
            expression = "_this setVariable ['comspec_sse_domex_p1_text', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
        };
        class comspec_sse_domex_p1_quality {
            displayName = "Paquet 1 — qualité";
            property = "comspec_sse_domex_p1_quality";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_p1_quality', _value, true];";
            defaultValue = "'complet'";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class complet { name = "Complet"; value = "complet"; default = 1; };
                class fragment { name = "Fragment (à croiser)"; value = "fragment"; };
                class leurre_possible { name = "Peut être un leurre"; value = "leurre_possible"; };
            };
        };
        class comspec_sse_domex_p1_channel {
            displayName = "Paquet 1 — canal";
            property = "comspec_sse_domex_p1_channel";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_p1_channel', _value, true];";
            defaultValue = "'physique'";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class physique { name = "Accès physique uniquement"; value = "physique"; default = 1; };
                class distant { name = "Visible aussi à distance (plus pauvre)"; value = "distant"; };
                class les_deux { name = "Physique et distant"; value = "les_deux"; };
            };
        };
        class comspec_sse_domex_p1_reveal {
            displayName = "Paquet 1 — révélation";
            property = "comspec_sse_domex_p1_reveal";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_p1_reveal', _value, true];";
            defaultValue = "'immediat'";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class immediat { name = "Immédiat"; value = "immediat"; default = 1; };
                class delai { name = "Après le délai d’exploitation"; value = "delai"; };
                class acces_etabli { name = "Au palier « accès établi »"; value = "acces_etabli"; };
            };
        };
        class comspec_sse_domex_p1_entities {
            displayName = "Paquet 1 — entités (une par ligne)";
            tooltip = "Format : Nom | type. Types : lieu, personne, organisation, evenement, objectif, frequence, vehicule, support.";
            property = "comspec_sse_domex_p1_entities";
            control = "EditMulti3";
            expression = "_this setVariable ['comspec_sse_domex_p1_entities', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
        };
        class comspec_sse_domex_p2_type {
            displayName = "Paquet 2 — type";
            property = "comspec_sse_domex_p2_type";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_p2_type', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class none { name = "(aucun)"; value = ""; default = 1; };
                class message { name = "Message"; value = "message"; };
                class document { name = "Document"; value = "document"; };
                class photo { name = "Photographie"; value = "photo"; };
                class contact { name = "Contact"; value = "contact"; };
                class coordinate { name = "Coordonnée / point"; value = "coordinate"; };
                class frequency { name = "Fréquence"; value = "frequency"; };
                class schedule { name = "Horaire"; value = "schedule"; };
                class manifest { name = "Manifeste"; value = "manifest"; };
                class objective { name = "Objectif"; value = "objective"; };
            };
        };
        class comspec_sse_domex_p2_text {
            displayName = "Paquet 2 — texte";
            property = "comspec_sse_domex_p2_text";
            control = "EditMulti5";
            expression = "_this setVariable ['comspec_sse_domex_p2_text', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
        };
        class comspec_sse_domex_p2_quality {
            displayName = "Paquet 2 — qualité";
            property = "comspec_sse_domex_p2_quality";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_p2_quality', _value, true];";
            defaultValue = "'complet'";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class complet { name = "Complet"; value = "complet"; default = 1; };
                class fragment { name = "Fragment (à croiser)"; value = "fragment"; };
                class leurre_possible { name = "Peut être un leurre"; value = "leurre_possible"; };
            };
        };
        class comspec_sse_domex_p2_channel {
            displayName = "Paquet 2 — canal";
            property = "comspec_sse_domex_p2_channel";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_p2_channel', _value, true];";
            defaultValue = "'physique'";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class physique { name = "Accès physique uniquement"; value = "physique"; default = 1; };
                class distant { name = "Visible aussi à distance (plus pauvre)"; value = "distant"; };
                class les_deux { name = "Physique et distant"; value = "les_deux"; };
            };
        };
        class comspec_sse_domex_p2_reveal {
            displayName = "Paquet 2 — révélation";
            property = "comspec_sse_domex_p2_reveal";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_domex_p2_reveal', _value, true];";
            defaultValue = "'immediat'";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
            class values {
                class immediat { name = "Immédiat"; value = "immediat"; default = 1; };
                class delai { name = "Après le délai d’exploitation"; value = "delai"; };
                class acces_etabli { name = "Au palier « accès établi »"; value = "acces_etabli"; };
            };
        };
        class comspec_sse_domex_p2_entities {
            displayName = "Paquet 2 — entités (une par ligne)";
            tooltip = "Format : Nom | type (lieu, personne, organisation…).";
            property = "comspec_sse_domex_p2_entities";
            control = "EditMulti3";
            expression = "_this setVariable ['comspec_sse_domex_p2_entities', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectSimulated - objectBrain";
        };
    };
};
