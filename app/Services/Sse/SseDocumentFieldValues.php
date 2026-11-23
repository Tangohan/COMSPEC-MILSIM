<?php

declare(strict_types=1);

namespace App\Services\Sse;

/**
 * Valeurs des champs dynamiques d'un document SSE (feuille, aperçu, PDF) : dossier, classification, référence, intitulé, type,
 * et identifiants de la fiche (numéro d'identification, nom de code, DSE) quand ils existent. Un identifiant absent reste vide :
 * le champ garde alors son texte d'attente.
 */
final class SseDocumentFieldValues
{
    /**
     * @param array<string,mixed> $doc document hydraté
     * @return array<string,string>
     */
    public static function for(array $doc): array
    {
        return [
            'dossier' => (string) ($doc['case_reference'] ?? ''),
            'classification' => (string) ($doc['classification_label'] ?? ''),
            'reference' => (string) ($doc['reference_code'] ?? ''),
            'titre' => (string) ($doc['title'] ?? ''),
            'type' => (string) ($doc['document_type_label'] ?? ''),
        ] + self::identifiers((int) ($doc['tenant_id'] ?? 0), (int) ($doc['id'] ?? 0));
    }

    /** @return array{id_fiche:string,nom_code:string,dse:string} */
    public static function identifiers(int $tenantId, int $docId): array
    {
        $out = ['id_fiche' => '', 'nom_code' => '', 'dse' => ''];
        if ($tenantId < 1 || $docId < 1) {
            return $out;
        }
        try {
            $row = (new SseIdentifierService())->forEntity($tenantId, 'document', $docId);
        } catch (\Throwable) {
            $row = null;
        }
        if (is_array($row)) {
            $out = ['id_fiche' => (string) ($row['ident_no'] ?? ''), 'nom_code' => (string) ($row['codename'] ?? ''), 'dse' => (string) ($row['dse'] ?? '')];
        }

        return $out;
    }
}
