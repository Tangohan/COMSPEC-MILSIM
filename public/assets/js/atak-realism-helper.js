/**
 * ATHENA C2 Realism Configuration Helper
 * 
 * Helper JavaScript pour charger et accéder à la configuration réalisme centralisée
 * depuis l'API ATHENA. Toutes les valeurs hardcodées doivent être remplacées par
 * des appels à cette classe.
 * 
 * @version 1.0.0
 * @author ATHENA C2 Team
 * @date 2026-09-22
 */

class AtakRealismConfig {
    /**
     * Cache de la configuration chargée depuis l'API
     * @private
     * @type {Object|null}
     */
    static _cachedConfig = null;

    /**
     * Timestamp du dernier chargement (ms)
     * @private
     * @type {number}
     */
    static _lastLoadTime = 0;

    /**
     * Durée de vie du cache (3 minutes)
     * @private
     * @type {number}
     */
    static _cacheTTL = 3 * 60 * 1000;

    /**
     * Charge la configuration réalisme depuis l'API
     * @param {boolean} forceRefresh - Forcer le rechargement même si le cache est valide
     * @returns {Promise<Object>} - Configuration complète
     */
    static async load(forceRefresh = false) {
        const now = Date.now();

        // Utiliser le cache si valide
        if (!forceRefresh && this._cachedConfig && (now - this._lastLoadTime) < this._cacheTTL) {
            return this._cachedConfig;
        }

        try {
            const response = await fetch('/api/atak/realism/config');
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            if (!data.ok || !data.config) {
                throw new Error('Invalid config response format');
            }

            // Mettre à jour le cache
            this._cachedConfig = data.config;
            this._lastLoadTime = now;

            console.log('[AtakRealismConfig] Configuration loaded successfully', {
                domains: Object.keys(this._cachedConfig).length,
                cached: true,
                ttl: this._cacheTTL / 1000 + 's'
            });

            return this._cachedConfig;
        } catch (error) {
            console.error('[AtakRealismConfig] Failed to load config from API:', error);

            // Si le cache existe (même expiré), l'utiliser en fallback
            if (this._cachedConfig) {
                console.warn('[AtakRealismConfig] Using expired cache as fallback');
                return this._cachedConfig;
            }

            // Sinon, retourner un objet vide
            console.error('[AtakRealismConfig] No cache available, returning empty config');
            return {};
        }
    }

    /**
     * Récupère une valeur de configuration
     * @param {string} domain - Domaine de configuration (ex: 'radio_relays')
     * @param {string} key - Clé du paramètre (ex: 'relay_range_m')
     * @param {*} defaultValue - Valeur par défaut si non trouvée
     * @returns {*} - Valeur du paramètre ou defaultValue
     */
    static get(config, domain, key, defaultValue) {
        if (!config || typeof config !== 'object') {
            console.warn('[AtakRealismConfig] Invalid config object, returning default', {
                domain, key, defaultValue
            });
            return defaultValue;
        }

        const domainConfig = config[domain];
        if (!domainConfig || typeof domainConfig !== 'object') {
            console.warn('[AtakRealismConfig] Domain not found, returning default', {
                domain, key, defaultValue
            });
            return defaultValue;
        }

        const value = domainConfig[key];
        if (value === undefined || value === null) {
            console.warn('[AtakRealismConfig] Key not found, returning default', {
                domain, key, defaultValue
            });
            return defaultValue;
        }

        return value;
    }

    /**
     * Récupère une valeur de configuration avec chargement automatique
     * @param {string} domain - Domaine de configuration (ex: 'radio_relays')
     * @param {string} key - Clé du paramètre (ex: 'relay_range_m')
     * @param {*} defaultValue - Valeur par défaut si non trouvée
     * @returns {Promise<*>} - Valeur du paramètre ou defaultValue
     */
    static async getWithLoad(domain, key, defaultValue) {
        const config = await this.load();
        return this.get(config, domain, key, defaultValue);
    }

    /**
     * Récupère un domaine complet de configuration
     * @param {Object} config - Configuration chargée
     * @param {string} domain - Domaine à récupérer (ex: 'radio_relays')
     * @returns {Object|null} - Objet du domaine ou null
     */
    static getDomain(config, domain) {
        if (!config || typeof config !== 'object') {
            console.warn('[AtakRealismConfig] Invalid config object');
            return null;
        }

        return config[domain] || null;
    }

    /**
     * Invalide le cache (force le rechargement au prochain load)
     */
    static clearCache() {
        console.log('[AtakRealismConfig] Cache cleared');
        this._cachedConfig = null;
        this._lastLoadTime = 0;
    }

    /**
     * Vérifie si le cache est valide
     * @returns {boolean}
     */
    static isCacheValid() {
        const now = Date.now();
        return this._cachedConfig !== null && (now - this._lastLoadTime) < this._cacheTTL;
    }

    /**
     * Obtient des statistiques sur le cache
     * @returns {Object}
     */
    static getCacheStats() {
        const now = Date.now();
        const age = this._cachedConfig ? now - this._lastLoadTime : 0;
        const remaining = this._cachedConfig ? Math.max(0, this._cacheTTL - age) : 0;

        return {
            cached: this._cachedConfig !== null,
            valid: this.isCacheValid(),
            age: age,
            remaining: remaining,
            ttl: this._cacheTTL,
            domains: this._cachedConfig ? Object.keys(this._cachedConfig).length : 0
        };
    }
}

// Export pour utilisation dans d'autres modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AtakRealismConfig;
}

// Log de chargement
console.log('[AtakRealismConfig] Helper loaded, version 1.0.0');
