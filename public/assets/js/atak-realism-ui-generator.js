/**
 * G\u00e9n\u00e9rateur UI admin depuis sch\u00e9ma JSON
 * Cr\u00e9e automatiquement les champs HTML selon type (slider, toggle, dropdown, color...)
 * 
 * Usage :
 *   const generator = new AtakRealismUIGenerator(schemaJson);
 *   generator.renderDomain('radio_relays', containerElement);
 */

class AtakRealismUIGenerator {
    constructor(schema) {
        this.schema = schema;
        this.values = {};
    }
    
    /**
     * Charger valeurs actuelles config
     */
    loadValues(configJson) {
        this.values = configJson;
    }
    
    /**
     * Rendre un domaine complet avec tous ses param\u00e8tres
     */
    renderDomain(domainKey, containerElement) {
        const domain = this.schema.domains[domainKey];
        
        if (!domain) {
            console.error(`Domaine ${domainKey} introuvable`);
            return;
        }
        
        containerElement.innerHTML = '';
        
        // Header domaine
        const header = document.createElement('div');
        header.className = 'domain-header';
        header.innerHTML = `
            <h3>${domain.label}</h3>
            <p class="text-muted">${domain.description}</p>
        `;
        containerElement.appendChild(header);
        
        // Param\u00e8tres
        const paramsContainer = document.createElement('div');
        paramsContainer.className = 'parameters-container';
        
        Object.keys(domain.parameters).forEach(paramKey => {
            const param = domain.parameters[paramKey];
            const paramDiv = this.renderParameter(domainKey, paramKey, param);
            paramsContainer.appendChild(paramDiv);
        });
        
        containerElement.appendChild(paramsContainer);
    }
    
    /**
     * Rendre un param\u00e8tre individuel
     */
    renderParameter(domainKey, paramKey, param) {
        const container = document.createElement('div');
        container.className = 'parameter-row';
        container.dataset.domain = domainKey;
        container.dataset.param = paramKey;
        
        // Si d\u00e9pend d'un autre param\u00e8tre, v\u00e9rifier visibilit\u00e9
        if (param.depends_on) {
            container.dataset.dependsOn = param.depends_on;
            container.style.display = 'none'; // Cach\u00e9 par d\u00e9faut
        }
        
        const currentValue = this.values[domainKey]?.[paramKey] ?? param.default;
        
        // Label + help
        const labelDiv = document.createElement('div');
        labelDiv.className = 'parameter-label';
        labelDiv.innerHTML = `
            <label for="${domainKey}_${paramKey}">
                ${param.label}
                ${param.help ? `<span class="help-icon" data-bs-toggle="tooltip" title="${param.help}">\u2753</span>` : ''}
            </label>
            <small class="text-muted d-block">${param.description}</small>
        `;
        container.appendChild(labelDiv);
        
        // Input selon type
        const inputDiv = document.createElement('div');
        inputDiv.className = 'parameter-input';
        
        switch (param.type) {
            case 'toggle':
                inputDiv.innerHTML = this.renderToggle(domainKey, paramKey, param, currentValue);
                break;
            case 'slider':
                inputDiv.innerHTML = this.renderSlider(domainKey, paramKey, param, currentValue);
                break;
            case 'dropdown':
                inputDiv.innerHTML = this.renderDropdown(domainKey, paramKey, param, currentValue);
                break;
            case 'multi-select':
                inputDiv.innerHTML = this.renderMultiSelect(domainKey, paramKey, param, currentValue);
                break;
            case 'color':
                inputDiv.innerHTML = this.renderColorPicker(domainKey, paramKey, param, currentValue);
                break;
            case 'text':
                inputDiv.innerHTML = this.renderText(domainKey, paramKey, param, currentValue);
                break;
            case 'number':
                inputDiv.innerHTML = this.renderNumber(domainKey, paramKey, param, currentValue);
                break;
            default:
                inputDiv.innerHTML = `<input type="text" class="form-control" value="${currentValue}" />`;
        }
        
        container.appendChild(inputDiv);
        
        return container;
    }
    
    renderToggle(domain, param, config, value) {
        const checked = value ? 'checked' : '';
        return `
            <div class="form-check form-switch">
                <input 
                    class="form-check-input" 
                    type="checkbox" 
                    id="${domain}_${param}" 
                    name="${domain}[${param}]"
                    ${checked}
                    data-type="toggle"
                />
                <label class="form-check-label" for="${domain}_${param}">
                    ${value ? '\u2705 Activ\u00e9' : '\u274c D\u00e9sactiv\u00e9'}
                </label>
            </div>
        `;
    }
    
    renderSlider(domain, param, config, value) {
        const min = config.validation?.min ?? config.min ?? 0;
        const max = config.validation?.max ?? config.max ?? 100;
        const step = config.step ?? 1;
        const unit = config.unit ?? '';
        
        return `
            <div class="slider-container">
                <input 
                    type="range" 
                    class="form-range" 
                    id="${domain}_${param}" 
                    name="${domain}[${param}]"
                    min="${min}" 
                    max="${max}" 
                    step="${step}" 
                    value="${value}"
                    data-type="slider"
                />
                <div class="slider-value">
                    <strong id="${domain}_${param}_value">${value}</strong> ${unit}
                </div>
            </div>
        `;
    }
    
    renderDropdown(domain, param, config, value) {
        const options = config.options || [];
        const optionsHtml = options.map(opt => {
            const selected = opt.value === value ? 'selected' : '';
            return `<option value="${opt.value}" ${selected}>${opt.label}</option>`;
        }).join('');
        
        return `
            <select 
                class="form-select" 
                id="${domain}_${param}" 
                name="${domain}[${param}]"
                data-type="dropdown"
            >
                ${optionsHtml}
            </select>
        `;
    }
    
    renderMultiSelect(domain, param, config, value) {
        const options = config.options || [];
        const selectedValues = Array.isArray(value) ? value : [];
        
        const optionsHtml = options.map(opt => {
            const checked = selectedValues.includes(opt.value) ? 'checked' : '';
            return `
                <div class="form-check">
                    <input 
                        class="form-check-input" 
                        type="checkbox" 
                        id="${domain}_${param}_${opt.value}" 
                        name="${domain}[${param}][]"
                        value="${opt.value}"
                        ${checked}
                        data-type="multi-select"
                    />
                    <label class="form-check-label" for="${domain}_${param}_${opt.value}">
                        ${opt.label}
                    </label>
                </div>
            `;
        }).join('');
        
        return `<div class="multi-select-container">${optionsHtml}</div>`;
    }
    
    renderColorPicker(domain, param, config, value) {
        return `
            <div class="color-picker-container">
                <input 
                    type="color" 
                    class="form-control form-control-color" 
                    id="${domain}_${param}" 
                    name="${domain}[${param}]"
                    value="${value}"
                    data-type="color"
                />
                <span class="color-hex">${value}</span>
            </div>
        `;
    }
    
    renderText(domain, param, config, value) {
        const maxLength = config.validation?.maxLength ?? 255;
        return `
            <input 
                type="text" 
                class="form-control" 
                id="${domain}_${param}" 
                name="${domain}[${param}]"
                value="${value}"
                maxlength="${maxLength}"
                data-type="text"
            />
        `;
    }
    
    renderNumber(domain, param, config, value) {
        const min = config.validation?.min ?? 0;
        const max = config.validation?.max ?? 999999;
        const unit = config.unit ?? '';
        
        return `
            <div class="number-input-container">
                <input 
                    type="number" 
                    class="form-control" 
                    id="${domain}_${param}" 
                    name="${domain}[${param}]"
                    value="${value}"
                    min="${min}"
                    max="${max}"
                    data-type="number"
                />
                <span class="input-unit">${unit}</span>
            </div>
        `;
    }
    
    /**
     * Attacher \u00e9v\u00e9nements interactifs (sliders, toggles, d\u00e9pendances...)
     */
    attachEvents() {
        // Sliders : maj valeur affich\u00e9e
        document.querySelectorAll('input[data-type="slider"]').forEach(slider => {
            slider.addEventListener('input', (e) => {
                const valueSpan = document.getElementById(e.target.id + '_value');
                if (valueSpan) {
                    valueSpan.textContent = e.target.value;
                }
            });
        });
        
        // Toggles : maj label Activ\u00e9/D\u00e9sactiv\u00e9
        document.querySelectorAll('input[data-type="toggle"]').forEach(toggle => {
            toggle.addEventListener('change', (e) => {
                const label = e.target.nextElementSibling;
                if (label) {
                    label.textContent = e.target.checked ? '\u2705 Activ\u00e9' : '\u274c D\u00e9sactiv\u00e9';
                }
                
                // G\u00e9rer d\u00e9pendances (afficher/masquer param\u00e8tres d\u00e9pendants)
                const paramKey = e.target.id.split('_').pop();
                document.querySelectorAll(`[data-depends-on="${paramKey}"]`).forEach(dependent => {
                    dependent.style.display = e.target.checked ? 'flex' : 'none';
                });
            });
            
            // Initialiser \u00e9tat d\u00e9pendances au chargement
            toggle.dispatchEvent(new Event('change'));
        });
        
        // Color pickers : maj hex affich\u00e9
        document.querySelectorAll('input[data-type="color"]').forEach(picker => {
            picker.addEventListener('input', (e) => {
                const hexSpan = e.target.nextElementSibling;
                if (hexSpan) {
                    hexSpan.textContent = e.target.value;
                }
            });
        });
        
        // Tooltips Bootstrap
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(el => new bootstrap.Tooltip(el));
    }
    
    /**
     * Extraire valeurs du formulaire
     */
    extractValues() {
        const config = {};
        
        // Tous les domaines
        Object.keys(this.schema.domains).forEach(domainKey => {
            config[domainKey] = {};
            
            // Tous les param\u00e8tres du domaine
            Object.keys(this.schema.domains[domainKey].parameters).forEach(paramKey => {
                const input = document.getElementById(`${domainKey}_${paramKey}`);
                
                if (!input) return;
                
                const type = input.dataset.type;
                
                switch (type) {
                    case 'toggle':
                        config[domainKey][paramKey] = input.checked;
                        break;
                    case 'slider':
                    case 'number':
                        config[domainKey][paramKey] = parseFloat(input.value);
                        break;
                    case 'multi-select':
                        const checked = document.querySelectorAll(`input[name="${domainKey}[${paramKey}][]"]:checked`);
                        config[domainKey][paramKey] = Array.from(checked).map(cb => cb.value);
                        break;
                    default:
                        config[domainKey][paramKey] = input.value;
                }
            });
        });
        
        return config;
    }
    
    /**
     * Valider config c\u00f4t\u00e9 client
     */
    validate(config) {
        const errors = [];
        
        Object.keys(this.schema.domains).forEach(domainKey => {
            const domain = this.schema.domains[domainKey];
            
            Object.keys(domain.parameters).forEach(paramKey => {
                const param = domain.parameters[paramKey];
                const value = config[domainKey]?.[paramKey];
                
                if (value === undefined || value === null) return;
                
                const validation = param.validation || {};
                
                // Min/max nombres
                if (validation.min !== undefined && value < validation.min) {
                    errors.push(`${param.label} doit \u00eatre \u2265 ${validation.min}`);
                }
                
                if (validation.max !== undefined && value > validation.max) {
                    errors.push(`${param.label} doit \u00eatre \u2264 ${validation.max}`);
                }
                
                // MaxLength texte
                if (validation.maxLength !== undefined && typeof value === 'string') {
                    if (value.length > validation.maxLength) {
                        errors.push(`${param.label} doit faire max ${validation.maxLength} caract\u00e8res`);
                    }
                }
                
                // Enum (dropdown)
                if (validation.enum && !validation.enum.includes(value)) {
                    errors.push(`${param.label} : valeur invalide`);
                }
                
                // Pattern (regex)
                if (validation.pattern && typeof value === 'string') {
                    const regex = new RegExp(validation.pattern);
                    if (!regex.test(value)) {
                        errors.push(`${param.label} : format invalide`);
                    }
                }
            });
        });
        
        return {
            valid: errors.length === 0,
            errors
        };
    }
}

// Export pour usage global
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AtakRealismUIGenerator;
}
