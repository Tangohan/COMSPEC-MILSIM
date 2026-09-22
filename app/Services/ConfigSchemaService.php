<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Service de lecture et cache du schéma JSON de configuration réalisme.
 * Source unique de vérité pour tous les paramètres, types, validations, profils.
 */
final class ConfigSchemaService
{
    private static ?array $schemaCache = null;
    private static string $schemaPath = __DIR__ . '/../../config/realism-schema.json';
    
    /**
     * Récupère le schéma complet (avec cache).
     * 
     * @return array Schéma complet
     * @throws RuntimeException Si schéma introuvable ou invalide
     */
    public static function getSchema(): array
    {
        if (self::$schemaCache !== null) {
            return self::$schemaCache;
        }
        
        if (!file_exists(self::$schemaPath)) {
            throw new RuntimeException("Schéma JSON introuvable : " . self::$schemaPath);
        }
        
        $content = file_get_contents(self::$schemaPath);
        if ($content === false) {
            throw new RuntimeException("Impossible de lire le schéma JSON");
        }
        
        $schema = json_decode($content, true);
        if ($schema === null) {
            throw new RuntimeException("Schéma JSON invalide : " . json_last_error_msg());
        }
        
        self::$schemaCache = $schema;
        
        return $schema;
    }
    
    /**
     * Récupère un domaine spécifique du schéma.
     * 
     * @param string $domainKey Clé du domaine (ex: 'radio_relays')
     * @return array|null Données du domaine ou null si inexistant
     */
    public static function getDomain(string $domainKey): ?array
    {
        $schema = self::getSchema();
        return $schema['domains'][$domainKey] ?? null;
    }
    
    /**
     * Récupère un paramètre spécifique.
     * 
     * @param string $domainKey Clé du domaine
     * @param string $paramKey Clé du paramètre
     * @return array|null Données du paramètre ou null si inexistant
     */
    public static function getParameter(string $domainKey, string $paramKey): ?array
    {
        $domain = self::getDomain($domainKey);
        if ($domain === null) {
            return null;
        }
        
        return $domain['parameters'][$paramKey] ?? null;
    }
    
    /**
     * Récupère tous les profils disponibles.
     * 
     * @return array Liste des profils (beginner, event, expert)
     */
    public static function getProfiles(): array
    {
        $schema = self::getSchema();
        return $schema['profiles'] ?? [];
    }
    
    /**
     * Récupère un profil spécifique.
     * 
     * @param string $profileKey Clé du profil (ex: 'beginner')
     * @return array|null Données du profil ou null si inexistant
     */
    public static function getProfile(string $profileKey): ?array
    {
        $profiles = self::getProfiles();
        return $profiles[$profileKey] ?? null;
    }
    
    /**
     * Construit une configuration par défaut depuis le schéma.
     * Utilise les valeurs 'default' de chaque paramètre.
     * 
     * @return array Configuration par défaut structurée par domaines
     */
    public static function buildDefaultConfig(): array
    {
        $schema = self::getSchema();
        $config = [];
        
        foreach ($schema['domains'] as $domainKey => $domain) {
            $config[$domainKey] = [];
            
            foreach ($domain['parameters'] as $paramKey => $param) {
                $config[$domainKey][$paramKey] = $param['default'] ?? null;
            }
        }
        
        return $config;
    }
    
    /**
     * Applique un profil à une configuration.
     * Écrase les valeurs par défaut avec les overrides du profil.
     * 
     * @param string $profileKey Clé du profil à appliquer
     * @param array|null $baseConfig Config de base (défaut = config par défaut du schéma)
     * @return array Configuration avec profil appliqué
     * @throws RuntimeException Si profil inexistant
     */
    public static function applyProfile(string $profileKey, ?array $baseConfig = null): array
    {
        $profile = self::getProfile($profileKey);
        if ($profile === null) {
            throw new RuntimeException("Profil inexistant : $profileKey");
        }
        
        $config = $baseConfig ?? self::buildDefaultConfig();
        $overrides = $profile['overrides'] ?? [];
        
        foreach ($overrides as $domainKey => $domainOverrides) {
            if (!isset($config[$domainKey])) {
                $config[$domainKey] = [];
            }
            
            foreach ($domainOverrides as $paramKey => $value) {
                $config[$domainKey][$paramKey] = $value;
            }
        }
        
        return $config;
    }
    
    /**
     * Valide une valeur selon les règles de validation d'un paramètre.
     * 
     * @param string $domainKey Clé du domaine
     * @param string $paramKey Clé du paramètre
     * @param mixed $value Valeur à valider
     * @return array{valid: bool, errors: array<string>}
     */
    public static function validateParameter(string $domainKey, string $paramKey, mixed $value): array
    {
        $param = self::getParameter($domainKey, $paramKey);
        if ($param === null) {
            return [
                'valid' => false,
                'errors' => ["Paramètre inconnu : {$domainKey}.{$paramKey}"]
            ];
        }
        
        $validation = $param['validation'] ?? [];
        $errors = [];
        
        // Type boolean
        if (isset($validation['type']) && $validation['type'] === 'boolean') {
            if (!is_bool($value)) {
                $errors[] = "{$domainKey}.{$paramKey} doit être un booléen";
            }
        }
        
        // Min/max pour nombres
        if (isset($validation['min']) && is_numeric($value)) {
            if ($value < $validation['min']) {
                $errors[] = "{$domainKey}.{$paramKey} doit être >= {$validation['min']}";
            }
        }
        
        if (isset($validation['max']) && is_numeric($value)) {
            if ($value > $validation['max']) {
                $errors[] = "{$domainKey}.{$paramKey} doit être <= {$validation['max']}";
            }
        }
        
        // Enum (dropdown)
        if (isset($validation['enum']) && is_array($validation['enum'])) {
            if (!in_array($value, $validation['enum'], true)) {
                $errors[] = "{$domainKey}.{$paramKey} doit être l'une des valeurs : " . implode(', ', $validation['enum']);
            }
        }
        
        // Pattern (regex, ex: couleurs)
        if (isset($validation['pattern']) && is_string($value)) {
            if (!preg_match('/' . $validation['pattern'] . '/', $value)) {
                $errors[] = "{$domainKey}.{$paramKey} ne respecte pas le format attendu";
            }
        }
        
        // MaxLength (texte)
        if (isset($validation['maxLength']) && is_string($value)) {
            if (strlen($value) > $validation['maxLength']) {
                $errors[] = "{$domainKey}.{$paramKey} doit faire max {$validation['maxLength']} caractères";
            }
        }
        
        // MinItems (multi-select)
        if (isset($validation['minItems']) && is_array($value)) {
            if (count($value) < $validation['minItems']) {
                $errors[] = "{$domainKey}.{$paramKey} doit avoir au moins {$validation['minItems']} éléments";
            }
        }
        
        return [
            'valid' => count($errors) === 0,
            'errors' => $errors
        ];
    }
    
    /**
     * Valide une configuration complète selon le schéma.
     * 
     * @param array $config Configuration à valider
     * @return array{valid: bool, errors: array<string>}
     */
    public static function validateConfig(array $config): array
    {
        $schema = self::getSchema();
        $allErrors = [];
        
        foreach ($schema['domains'] as $domainKey => $domain) {
            if (!isset($config[$domainKey])) {
                $allErrors[] = "Domaine manquant : {$domainKey}";
                continue;
            }
            
            foreach ($domain['parameters'] as $paramKey => $param) {
                if (!isset($config[$domainKey][$paramKey])) {
                    // Paramètre manquant (pas forcément erreur si default existe)
                    continue;
                }
                
                $value = $config[$domainKey][$paramKey];
                $validation = self::validateParameter($domainKey, $paramKey, $value);
                
                if (!$validation['valid']) {
                    $allErrors = array_merge($allErrors, $validation['errors']);
                }
            }
        }
        
        return [
            'valid' => count($allErrors) === 0,
            'errors' => $allErrors
        ];
    }
    
    /**
     * Récupère le nombre total de paramètres dans le schéma.
     * 
     * @return int Nombre de paramètres
     */
    public static function getParametersCount(): int
    {
        $schema = self::getSchema();
        $count = 0;
        
        foreach ($schema['domains'] as $domain) {
            $count += count($domain['parameters'] ?? []);
        }
        
        return $count;
    }
    
    /**
     * Invalide le cache (force rechargement schéma au prochain appel).
     * Utile pour tests ou si schéma modifié à chaud.
     */
    public static function clearCache(): void
    {
        self::$schemaCache = null;
    }
    
    /**
     * Définit un chemin custom pour le schéma (utile pour tests).
     * 
     * @param string $path Chemin absolu vers fichier JSON
     */
    public static function setSchemaPath(string $path): void
    {
        self::$schemaPath = $path;
        self::clearCache();
    }
}
