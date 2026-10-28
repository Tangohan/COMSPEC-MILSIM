class COMSPEC_SSE_Documents {
    displayName = "$STR_COMSPEC_SSE_CFG_4811ECDA45";
    collapsed = 1;
    class Attributes {
        class comspec_sse_documentsMode {
            displayName = "$STR_COMSPEC_SSE_CFG_9260BC8B10";
            tooltip = "$STR_COMSPEC_SSE_CFG_3C674C54E0";
            property = "comspec_sse_documentsMode";
            control = "Combo";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_documentsMode', _value, true]; };";
            defaultValue = "'AUTO'";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
            class values {
                class AUTO { name = "$STR_COMSPEC_SSE_CFG_BAA823183D"; value = "AUTO"; default = 1; };
                class NONE { name = "$STR_COMSPEC_SSE_CFG_476B4E4107"; value = "NONE"; };
                class CUSTOM { name = "$STR_COMSPEC_SSE_CFG_E763C43C79"; value = "CUSTOM"; };
            };
        };
        class comspec_sse_doc1_title {
            displayName = "$STR_COMSPEC_SSE_CFG_CB16B6648F";
            tooltip = "$STR_COMSPEC_SSE_CFG_C4BA3E1DB8";
            property = "comspec_sse_doc1_title";
            control = "Edit";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_doc1_title', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_doc1_summary {
            displayName = "$STR_COMSPEC_SSE_CFG_C36411F152";
            tooltip = "$STR_COMSPEC_SSE_CFG_2A78F654E6";
            property = "comspec_sse_doc1_summary";
            control = "EditMulti3";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_doc1_summary', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_doc1_grid {
            displayName = "$STR_COMSPEC_SSE_CFG_5B3CD43C74";
            tooltip = "$STR_COMSPEC_SSE_CFG_EABBD83E6E";
            property = "comspec_sse_doc1_grid";
            control = "Edit";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_doc1_grid', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_doc1_codeword {
            displayName = "$STR_COMSPEC_SSE_CFG_D6542AB806";
            tooltip = "$STR_COMSPEC_SSE_CFG_33BD4A53AC";
            property = "comspec_sse_doc1_codeword";
            control = "Edit";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_doc1_codeword', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_doc2_title {
            displayName = "$STR_COMSPEC_SSE_CFG_57D0313941";
            tooltip = "$STR_COMSPEC_SSE_CFG_824AA3F775";
            property = "comspec_sse_doc2_title";
            control = "Edit";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_doc2_title', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_doc2_summary {
            displayName = "$STR_COMSPEC_SSE_CFG_A22EE63F5C";
            tooltip = "$STR_COMSPEC_SSE_CFG_4D03EE89F4";
            property = "comspec_sse_doc2_summary";
            control = "EditMulti3";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_doc2_summary', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_doc2_grid {
            displayName = "$STR_COMSPEC_SSE_CFG_04014B10EE";
            tooltip = "$STR_COMSPEC_SSE_CFG_7B4C88CB3D";
            property = "comspec_sse_doc2_grid";
            control = "Edit";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_doc2_grid', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_doc2_codeword {
            displayName = "$STR_COMSPEC_SSE_CFG_B6C099B1B7";
            tooltip = "$STR_COMSPEC_SSE_CFG_5E9A3CF066";
            property = "comspec_sse_doc2_codeword";
            control = "Edit";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_doc2_codeword', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_doc3_title {
            displayName = "$STR_COMSPEC_SSE_CFG_4039481832";
            tooltip = "$STR_COMSPEC_SSE_CFG_11D30DD9B9";
            property = "comspec_sse_doc3_title";
            control = "Edit";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_doc3_title', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_doc3_summary {
            displayName = "$STR_COMSPEC_SSE_CFG_A9FFF95001";
            tooltip = "$STR_COMSPEC_SSE_CFG_4D03EE89F4";
            property = "comspec_sse_doc3_summary";
            control = "EditMulti3";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_doc3_summary', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_doc3_grid {
            displayName = "$STR_COMSPEC_SSE_CFG_A2950F421E";
            tooltip = "$STR_COMSPEC_SSE_CFG_7B4C88CB3D";
            property = "comspec_sse_doc3_grid";
            control = "Edit";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_doc3_grid', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_doc3_codeword {
            displayName = "$STR_COMSPEC_SSE_CFG_2E7337892A";
            tooltip = "$STR_COMSPEC_SSE_CFG_5E9A3CF066";
            property = "comspec_sse_doc3_codeword";
            control = "Edit";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_doc3_codeword', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
    };
};

class COMSPEC_SSE_Phone {
    displayName = "$STR_COMSPEC_SSE_CFG_8E786C76A8";
    collapsed = 1;
    class Attributes {
        class comspec_sse_phoneMode {
            displayName = "$STR_COMSPEC_SSE_CFG_8BB01ACE3F";
            tooltip = "$STR_COMSPEC_SSE_CFG_2B8561B169";
            property = "comspec_sse_phoneMode";
            control = "Combo";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_phoneMode', _value, true]; };";
            defaultValue = "'AUTO'";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
            class values {
                class AUTO { name = "$STR_COMSPEC_SSE_CFG_BAA823183D"; value = "AUTO"; default = 1; };
                class NONE { name = "$STR_COMSPEC_SSE_CFG_359E99272E"; value = "NONE"; };
                class CUSTOM { name = "$STR_COMSPEC_SSE_CFG_61E290C4C3"; value = "CUSTOM"; };
            };
        };
        class comspec_sse_phoneNumber {
            displayName = "$STR_COMSPEC_SSE_CFG_15E73DB01A";
            tooltip = "$STR_COMSPEC_SSE_CFG_072AA07E5B";
            property = "comspec_sse_phoneNumber";
            control = "Edit";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_phoneNumber', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_phoneModel {
            displayName = "$STR_COMSPEC_SSE_CFG_259CC6EAC4";
            tooltip = "$STR_COMSPEC_SSE_CFG_F657DAC55C";
            property = "comspec_sse_phoneModel";
            control = "Edit";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_phoneModel', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_phoneContacts {
            displayName = "$STR_COMSPEC_SSE_CFG_85244C0B91";
            tooltip = "$STR_COMSPEC_SSE_CFG_2C0BC4D5A7";
            property = "comspec_sse_phoneContacts";
            control = "EditMulti5";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_phoneContacts', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_phoneMessages {
            displayName = "$STR_COMSPEC_SSE_CFG_CC0099938C";
            tooltip = "$STR_COMSPEC_SSE_CFG_C358CEA867";
            property = "comspec_sse_phoneMessages";
            control = "EditMulti5";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_phoneMessages', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_phoneNotes {
            displayName = "$STR_COMSPEC_SSE_CFG_99035C61FD";
            tooltip = "$STR_COMSPEC_SSE_CFG_2C5469EC6E";
            property = "comspec_sse_phoneNotes";
            control = "EditMulti3";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_phoneNotes', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_phonePlaces {
            displayName = "$STR_COMSPEC_SSE_CFG_342833114E";
            tooltip = "$STR_COMSPEC_SSE_CFG_5B8C197248";
            property = "comspec_sse_phonePlaces";
            control = "EditMulti3";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_phonePlaces', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
    };
};

class COMSPEC_SSE_Computer {
    displayName = "$STR_COMSPEC_SSE_CFG_FD8A2A97D1";
    collapsed = 1;
    class Attributes {
        class comspec_sse_digitalMode {
            displayName = "$STR_COMSPEC_SSE_CFG_BA4FEC0445";
            tooltip = "$STR_COMSPEC_SSE_CFG_A545BBF213";
            property = "comspec_sse_digitalMode";
            control = "Combo";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_digitalMode', _value, true]; };";
            defaultValue = "'AUTO'";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
            class values {
                class AUTO { name = "$STR_COMSPEC_SSE_CFG_BAA823183D"; value = "AUTO"; default = 1; };
                class NONE { name = "$STR_COMSPEC_SSE_CFG_1D121FA27C"; value = "NONE"; };
                class CUSTOM { name = "$STR_COMSPEC_SSE_CFG_5D66D947AD"; value = "CUSTOM"; };
            };
        };
        class comspec_sse_pcHostname {
            displayName = "$STR_COMSPEC_SSE_CFG_58F212F6D8";
            tooltip = "$STR_COMSPEC_SSE_CFG_8AF850AA39";
            property = "comspec_sse_pcHostname";
            control = "Edit";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_pcHostname', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_pcOwner {
            displayName = "$STR_COMSPEC_SSE_CFG_5433C0536D";
            tooltip = "$STR_COMSPEC_SSE_CFG_FFD602F24B";
            property = "comspec_sse_pcOwner";
            control = "Edit";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_pcOwner', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_pcFiles {
            displayName = "$STR_COMSPEC_SSE_CFG_A17E72E6BD";
            tooltip = "$STR_COMSPEC_SSE_CFG_2A815E91A6";
            property = "comspec_sse_pcFiles";
            control = "EditMulti5";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_pcFiles', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_pcMailSubject {
            displayName = "$STR_COMSPEC_SSE_CFG_CA5701EE37";
            tooltip = "$STR_COMSPEC_SSE_CFG_F950DFB835";
            property = "comspec_sse_pcMailSubject";
            control = "Edit";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_pcMailSubject', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_pcMailSnippet {
            displayName = "$STR_COMSPEC_SSE_CFG_BDE87EFDEA";
            tooltip = "$STR_COMSPEC_SSE_CFG_0A410DAD21";
            property = "comspec_sse_pcMailSnippet";
            control = "EditMulti3";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_pcMailSnippet', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_pcWifi {
            displayName = "$STR_COMSPEC_SSE_CFG_63F20CBF0F";
            tooltip = "$STR_COMSPEC_SSE_CFG_438E4536B9";
            property = "comspec_sse_pcWifi";
            control = "Edit";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_pcWifi', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
        class comspec_sse_pcAccessHint {
            displayName = "$STR_COMSPEC_SSE_CFG_705F78D09E";
            tooltip = "$STR_COMSPEC_SSE_CFG_DAC5C299CF";
            property = "comspec_sse_pcAccessHint";
            control = "Edit";
            expression = "if (isServer) then { _this setVariable ['comspec_sse_pcAccessHint', _value, true]; };";
            defaultValue = "''";
            typeName = "STRING";
            condition = "objectBrain + objectVehicle + objectControllable";
        };
    };
};
