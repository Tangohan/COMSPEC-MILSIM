-- Rend l'application des règles et les escalades rejouables sans doublonner les destinataires.
-- La notification reste en attente tant qu'un transport n'a pas confirmé son envoi.

DELETE duplicate_route
FROM atak_report_routing_history duplicate_route
JOIN atak_report_routing_history canonical_route
  ON canonical_route.report_id = duplicate_route.report_id
 AND canonical_route.routing_rule_id <=> duplicate_route.routing_rule_id
 AND canonical_route.routed_to_type = duplicate_route.routed_to_type
 AND canonical_route.routed_to_identifier = duplicate_route.routed_to_identifier
 AND canonical_route.id < duplicate_route.id;

ALTER TABLE atak_report_routing_history
    ADD UNIQUE KEY uq_report_rule_recipient (
        report_id,
        routing_rule_id,
        routed_to_type,
        routed_to_identifier
    );
