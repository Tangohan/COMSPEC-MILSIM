/**
 * Helper pour la gestion de la configuration réalisme ATAK côté web.
 * Fournit des fonctions utilitaires pour fetch, cache et calculs.
 * 
 * @module AtakRealismConfigHelper
 */

(function(window) {
    'use strict';

    /**
     * Configuration réalisme ATAK - Helper
     */
    const AtakRealismConfig = {
        // Cache de la config
        _cache: null,
        _cacheTimestamp: null,
        _cacheTTL: 180000, // 3 minutes

        /**
         * Récupère la configuration réalisme depuis l'API.
         * Utilise un cache côté client pour éviter les appels répétés.
         * 
         * @param {boolean} forceRefresh - Force le rechargement depuis l'API
         * @returns {Promise<Object>} Configuration complète
         */
        async fetch(forceRefresh = false) {
            const now = Date.now();
            
            // Retourner depuis cache si valide
            if (!forceRefresh && this._cache && this._cacheTimestamp) {
                if (now - this._cacheTimestamp < this._cacheTTL) {
                    return this._cache;
                }
            }

            try {
                const response = await fetch('/api/atak/realism/config');
                const data = await response.json();

                if (data.ok && data.config) {
                    this._cache = data.config;
                    this._cacheTimestamp = now;
                    return data.config;
                }

                throw new Error(data.error || 'Failed to fetch realism config');
            } catch (error) {
                console.error('[AtakRealismConfig] Fetch error:', error);
                
                // Retourner cache périmé si disponible
                if (this._cache) {
                    console.warn('[AtakRealismConfig] Using stale cache');
                    return this._cache;
                }

                throw error;
            }
        },

        /**
         * Récupère un domaine spécifique de la config.
         * 
         * @param {string} domain - Nom du domaine (ex: 'radio_relays')
         * @returns {Promise<Object>} Configuration du domaine
         */
        async getDomain(domain) {
            const config = await this.fetch();
            return config[domain] || {};
        },

        /**
         * Calcule la portée effective d'un relais selon conditions météo.
         * 
         * @param {number} baseRange - Portée nominale en mètres
         * @param {Object} weather - Conditions météo
         * @param {number} weather.rain - Intensité pluie (0-1)
         * @param {number} weather.fog - Intensité brouillard (0-1)
         * @param {number} weather.overcast - Couverture nuageuse (0-1)
         * @param {number} weather.windKmh - Vitesse vent (km/h)
         * @returns {Promise<Object>} Résultat avec effective_range, multipliers, description
         */
        async calculateWeatherEffects(baseRange, weather) {
            const radioConfig = await this.getDomain('radio_relays');

            if (!radioConfig.weather_effects_enabled) {
                return {
                    effectiveRange: baseRange,
                    weatherMultiplier: 1.0,
                    windMultiplier: 1.0,
                    totalMultiplier: 1.0,
                    description: 'Effets météo désactivés'
                };
            }

            // Calculer multiplicateur météo
            const weatherMult = this._calculateWeatherMultiplier(radioConfig, weather);
            
            // Calculer multiplicateur vent
            const windMult = this._calculateWindMultiplier(radioConfig, weather.windKmh || 0);
            
            // Portée effective
            const totalMult = weatherMult * windMult;
            const effectiveRange = baseRange * totalMult;

            const description = this._getWeatherDescription(weather, weatherMult, windMult);

            return {
                effectiveRange: Math.round(effectiveRange),
                weatherMultiplier: weatherMult,
                windMultiplier: windMult,
                totalMultiplier: totalMult,
                description: description
            };
        },

        /**
         * Calcule le multiplicateur météo (pluie, brouillard, orage).
         * @private
         */
        _calculateWeatherMultiplier(radioConfig, weather) {
            const rain = weather.rain || 0;
            const fog = weather.fog || 0;
            const overcast = weather.overcast || 0;

            // Détecter orage
            const isStorm = (rain > 0.5) && (overcast > 0.7);

            if (isStorm) {
                return radioConfig.storm_range_multiplier || 0.60;
            }

            let mult = 1.0;

            // Pluie
            if (rain > 0.3) {
                mult = radioConfig.rain_range_multiplier || 0.85;
            }

            // Brouillard
            if (fog > 0.5) {
                const fogMult = radioConfig.fog_range_multiplier || 0.70;
                mult = Math.min(mult, fogMult);
            }

            return mult;
        },

        /**
         * Calcule le multiplicateur vent.
         * @private
         */
        _calculateWindMultiplier(radioConfig, windKmh) {
            const threshold = radioConfig.wind_threshold_kmh || 50;
            const penaltyPer10 = radioConfig.wind_range_penalty_per_10kmh || 0.05;

            if (windKmh <= threshold) {
                return 1.0;
            }

            const windOver = windKmh - threshold;
            const penalties = Math.floor(windOver / 10);
            const mult = 1.0 - (penalties * penaltyPer10);

            return Math.max(0.5, mult);
        },

        /**
         * Génère une description textuelle de l'effet météo.
         * @private
         */
        _getWeatherDescription(weather, weatherMult, windMult) {
            const rain = weather.rain || 0;
            const fog = weather.fog || 0;
            const overcast = weather.overcast || 0;
            const windKmh = weather.windKmh || 0;

            const isStorm = (rain > 0.5) && (overcast > 0.7);

            const parts = [];

            if (isStorm) {
                parts.push(`Orage (${Math.round(weatherMult * 100)}%)`);
            } else if (rain > 0.3) {
                parts.push(`Pluie (${Math.round(weatherMult * 100)}%)`);
            } else if (fog > 0.5) {
                parts.push(`Brouillard (${Math.round(weatherMult * 100)}%)`);
            } else {
                parts.push('Temps clair');
            }

            if (windMult < 1.0) {
                parts.push(`Vent ${Math.round(windKmh)} km/h (${Math.round(windMult * 100)}%)`);
            }

            return parts.join(' × ');
        },

        /**
         * Retourne les icônes météo appropriées selon conditions.
         * 
         * @param {Object} weather - Conditions météo
         * @returns {string} Emojis météo
         */
        getWeatherIcon(weather) {
            const rain = weather.rain || 0;
            const fog = weather.fog || 0;
            const overcast = weather.overcast || 0;
            const windKmh = weather.windKmh || 0;

            const isStorm = (rain > 0.5) && (overcast > 0.7);

            let icons = '';

            if (isStorm) {
                icons += '⛈️';
            } else if (rain > 0.3) {
                icons += '🌧️';
            } else if (fog > 0.5) {
                icons += '🌫️';
            }

            if (windKmh > 50) {
                icons += '💨';
            }

            return icons || '☀️';
        },

        /**
         * Vérifie si un type de control measure est activé.
         * 
         * @param {string} type - Type de control measure ('axis', 'ld', 'loa', 'phase_line', 'objective', 'checkpoint')
         * @returns {Promise<boolean>} True si activé
         */
        async isControlMeasureEnabled(type) {
            const cmConfig = await this.getDomain('control_measures');
            
            if (!cmConfig.enabled) {
                return false;
            }

            const typeMap = {
                'axis': 'axis_naming_enabled',
                'ld': 'ld_enabled',
                'loa': 'loa_enabled',
                'phase_line': 'phase_line_enabled',
                'objective': 'objective_enabled',
                'checkpoint': 'checkpoint_enabled'
            };

            const field = typeMap[type];
            return field ? (cmConfig[field] || false) : false;
        },

        /**
         * Retourne les paramètres d'un type de control measure.
         * 
         * @param {string} type - Type de control measure
         * @returns {Promise<Object>} Paramètres (prefix, color, radius, etc.)
         */
        async getControlMeasureParams(type) {
            const cmConfig = await this.getDomain('control_measures');

            const params = {
                enabled: cmConfig.enabled && await this.isControlMeasureEnabled(type),
                visibility: cmConfig.control_measure_visibility || 'team'
            };

            switch(type) {
                case 'axis':
                    params.prefix = cmConfig.axis_label_prefix || 'AXIS';
                    params.width = cmConfig.axis_default_width_m || 500;
                    break;
                case 'ld':
                    params.prefix = cmConfig.ld_label_prefix || 'LD';
                    params.color = cmConfig.ld_default_color || '#00ff00';
                    break;
                case 'loa':
                    params.prefix = cmConfig.loa_label_prefix || 'LOA';
                    params.color = cmConfig.loa_default_color || '#ff0000';
                    break;
                case 'phase_line':
                    params.prefix = cmConfig.phase_line_label_prefix || 'PL';
                    params.color = cmConfig.phase_line_default_color || '#ffff00';
                    break;
                case 'objective':
                    params.prefix = cmConfig.objective_label_prefix || 'OBJ';
                    params.radius = cmConfig.objective_default_radius_m || 200;
                    break;
                case 'checkpoint':
                    params.prefix = cmConfig.checkpoint_label_prefix || 'CP';
                    params.radius = cmConfig.checkpoint_default_radius_m || 50;
                    params.autoNumber = cmConfig.checkpoint_auto_number !== false;
                    break;
            }

            return params;
        },

        /**
         * Invalide le cache (force le prochain fetch à recharger depuis l'API).
         */
        invalidateCache() {
            this._cache = null;
            this._cacheTimestamp = null;
        }
    };

    // Export global
    window.AtakRealismConfig = AtakRealismConfig;

})(window);
