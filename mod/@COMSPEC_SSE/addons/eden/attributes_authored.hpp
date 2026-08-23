class COMSPEC_SSE_Documents {
    displayName = "COMSPEC SSE — Documents";
    collapsed = 1;
    class Attributes {
        class comspec_sse_documentsMode {
            displayName = "Documents sur cette personne";
            tooltip = "Automatique : le jeu compose les pièces. Personnaliser : seules les pièces remplies ci-dessous sont utilisées. Ne pas inclure : aucun document.";
            property = "comspec_sse_documentsMode";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_documentsMode', _value, true];";
            defaultValue = "'AUTO'";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
            class values {
                class AUTO { name = "Générer automatiquement"; value = "AUTO"; default = 1; };
                class NONE { name = "Ne pas inclure de documents"; value = "NONE"; };
                class CUSTOM { name = "Personnaliser les pièces ci-dessous"; value = "CUSTOM"; };
            };
        };
        class comspec_sse_doc1_title {
            displayName = "Pièce 1 — intitulé";
            tooltip = "Ex. Relevé de comptes, ordre de mission, carte d’identité.";
            property = "comspec_sse_doc1_title";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_doc1_title', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_doc1_summary {
            displayName = "Pièce 1 — contenu";
            tooltip = "Texte lu sur le document (résumé, fonds, annotations).";
            property = "comspec_sse_doc1_summary";
            control = "EditMulti3";
            expression = "_this setVariable ['comspec_sse_doc1_summary', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_doc1_grid {
            displayName = "Pièce 1 — grille";
            tooltip = "Repère de carte mentionné sur la pièce, s’il y en a un.";
            property = "comspec_sse_doc1_grid";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_doc1_grid', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_doc1_codeword {
            displayName = "Pièce 1 — mot de code";
            tooltip = "Mot de code ou référence interne, s’il figure sur la pièce.";
            property = "comspec_sse_doc1_codeword";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_doc1_codeword', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_doc2_title {
            displayName = "Pièce 2 — intitulé";
            property = "comspec_sse_doc2_title";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_doc2_title', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_doc2_summary {
            displayName = "Pièce 2 — contenu";
            property = "comspec_sse_doc2_summary";
            control = "EditMulti3";
            expression = "_this setVariable ['comspec_sse_doc2_summary', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_doc2_grid {
            displayName = "Pièce 2 — grille";
            property = "comspec_sse_doc2_grid";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_doc2_grid', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_doc2_codeword {
            displayName = "Pièce 2 — mot de code";
            property = "comspec_sse_doc2_codeword";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_doc2_codeword', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_doc3_title {
            displayName = "Pièce 3 — intitulé";
            property = "comspec_sse_doc3_title";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_doc3_title', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_doc3_summary {
            displayName = "Pièce 3 — contenu";
            property = "comspec_sse_doc3_summary";
            control = "EditMulti3";
            expression = "_this setVariable ['comspec_sse_doc3_summary', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_doc3_grid {
            displayName = "Pièce 3 — grille";
            property = "comspec_sse_doc3_grid";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_doc3_grid', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_doc3_codeword {
            displayName = "Pièce 3 — mot de code";
            property = "comspec_sse_doc3_codeword";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_doc3_codeword', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
    };
};

class COMSPEC_SSE_Phone {
    displayName = "COMSPEC SSE — Téléphone";
    collapsed = 1;
    class Attributes {
        class comspec_sse_phoneMode {
            displayName = "Téléphone sur cette personne";
            tooltip = "Automatique : le jeu compose l’appareil. Personnaliser : les champs ci-dessous remplacent le contenu. Ne pas inclure : pas de téléphone.";
            property = "comspec_sse_phoneMode";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_phoneMode', _value, true];";
            defaultValue = "'AUTO'";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
            class values {
                class AUTO { name = "Générer automatiquement"; value = "AUTO"; default = 1; };
                class NONE { name = "Ne pas inclure de téléphone"; value = "NONE"; };
                class CUSTOM { name = "Personnaliser le téléphone ci-dessous"; value = "CUSTOM"; };
            };
        };
        class comspec_sse_phoneNumber {
            displayName = "Numéro";
            property = "comspec_sse_phoneNumber";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_phoneNumber', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_phoneModel {
            displayName = "Modèle d’appareil";
            tooltip = "Ex. téléphone basique, smartphone usagé.";
            property = "comspec_sse_phoneModel";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_phoneModel', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_phoneContacts {
            displayName = "Contacts (un par ligne)";
            tooltip = "Un nom ou alias par ligne.";
            property = "comspec_sse_phoneContacts";
            control = "EditMulti5";
            expression = "_this setVariable ['comspec_sse_phoneContacts', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_phoneMessages {
            displayName = "Messages (un par ligne)";
            tooltip = "Format : Expéditeur : texte du message";
            property = "comspec_sse_phoneMessages";
            control = "EditMulti5";
            expression = "_this setVariable ['comspec_sse_phoneMessages', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_phoneNotes {
            displayName = "Notes du téléphone (une par ligne)";
            property = "comspec_sse_phoneNotes";
            control = "EditMulti3";
            expression = "_this setVariable ['comspec_sse_phoneNotes', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_phonePlaces {
            displayName = "Lieux enregistrés (un par ligne)";
            tooltip = "Format : Libellé | grille  (ex. Dépôt nord | 56032 106032)";
            property = "comspec_sse_phonePlaces";
            control = "EditMulti3";
            expression = "_this setVariable ['comspec_sse_phonePlaces', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
    };
};

class COMSPEC_SSE_Computer {
    displayName = "COMSPEC SSE — Ordinateur";
    collapsed = 1;
    class Attributes {
        class comspec_sse_digitalMode {
            displayName = "Ordinateur sur cette personne";
            tooltip = "Automatique : un PC peut apparaître selon la richesse. Personnaliser : les champs ci-dessous composent l’ordinateur. Ne pas inclure : pas d’ordinateur.";
            property = "comspec_sse_digitalMode";
            control = "Combo";
            expression = "_this setVariable ['comspec_sse_digitalMode', _value, true];";
            defaultValue = "'AUTO'";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
            class values {
                class AUTO { name = "Générer automatiquement"; value = "AUTO"; default = 1; };
                class NONE { name = "Ne pas inclure d’ordinateur"; value = "NONE"; };
                class CUSTOM { name = "Personnaliser l’ordinateur ci-dessous"; value = "CUSTOM"; };
            };
        };
        class comspec_sse_pcHostname {
            displayName = "Nom de l’ordinateur";
            tooltip = "Nom affiché sur la machine (ex. PC-BUREAU).";
            property = "comspec_sse_pcHostname";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_pcHostname', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_pcOwner {
            displayName = "Compte / propriétaire";
            property = "comspec_sse_pcOwner";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_pcOwner', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_pcFiles {
            displayName = "Fichiers (un nom par ligne)";
            tooltip = "Noms de fichiers trouvés sur la machine, un par ligne.";
            property = "comspec_sse_pcFiles";
            control = "EditMulti5";
            expression = "_this setVariable ['comspec_sse_pcFiles', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_pcMailSubject {
            displayName = "Courrier — objet";
            property = "comspec_sse_pcMailSubject";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_pcMailSubject', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_pcMailSnippet {
            displayName = "Courrier — extrait";
            property = "comspec_sse_pcMailSnippet";
            control = "EditMulti3";
            expression = "_this setVariable ['comspec_sse_pcMailSnippet', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_pcWifi {
            displayName = "Nom du réseau";
            tooltip = "Nom du réseau auquel la machine s’est connectée.";
            property = "comspec_sse_pcWifi";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_pcWifi', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_pcAccessHint {
            displayName = "Indice d’accès";
            tooltip = "Mot de passe, mot de code ou indice laissé sur la machine.";
            property = "comspec_sse_pcAccessHint";
            control = "Edit";
            expression = "_this setVariable ['comspec_sse_pcAccessHint', _value, true];";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
    };
};
