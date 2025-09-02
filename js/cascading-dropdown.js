/**
 * Enhanced Strict Cascading Dropdown System with Progressive Enabling
 * FIXED: New tag creation immediate registration and proper event handling
 */
$(document).ready(function () {
    'use strict';

    const CascadingDropdown = {
        // Configuration
        config: {
            apiUrl: 'controllers/get_cascading_data.php',
            delay: 250,
            theme: 'bootstrap-5'
        },

        // Define the hierarchy order
        hierarchy: ['#system_name', '#module', '#feature', '#client', '#source'],

        // Store current selection state for validation
        selectionState: {
            system_name: null,
            module: null,
            feature: null,
            client: null,
            source: null
        },

        // Initialize all cascading dropdowns
        init() {
            console.log('🚀 Initializing cascading dropdown system...');

            // Wait for DOM to be ready
   if (document.readyState !== 'complete') {
    setTimeout(() => this.init(), 100);
    return;
}
            
            // Clear any existing handlers first
            this.hierarchy.forEach(selector => {
                const $element = $(selector);
                if ($element.length) {
                    $element.off(); // Remove ALL event handlers
                    if ($element.hasClass('select2-hidden-accessible')) {
                        $element.select2('destroy');
                    }
                }
            });
            
            setTimeout(() => {
                this.initializeSelect2();
                this.restoreOldFormValues();
                this.setupSelectionTracking(); // This should be BEFORE the AJAX dropdowns
                this.initSystemDropdown();
                this.initModuleDropdown();
                this.initFeatureDropdown();
                this.initClientDropdown();
                this.initSourceDropdown();
                this.initializeDropdownStates();
                this.setupFileUploadToggles();
                console.log('✅ Cascading dropdown system initialized');
            }, 100);
        },

        // ADDED: Restore old form values for all cascading fields
        restoreOldFormValues() {
            console.log('🔄 Restoring old form values...');
            
            // Get old values from PHP session (if available)
            const oldValues = window.phpOldValues || {};
            
            this.hierarchy.forEach(selector => {
                const fieldName = selector.replace('#', '');
                const oldValue = oldValues[fieldName];
                const $element = $(selector);
                
                if (oldValue && oldValue.trim() !== '' && $element.length) {
                    console.log(`Restoring ${fieldName}: "${oldValue}"`);
                    
                    // Add option if it doesn't exist
                    if (!$element.find(`option[value="${oldValue}"]`).length) {
                        const newOption = new Option(oldValue, oldValue, true, true);
                        $element.append(newOption);
                    }
                    
                    // Set value and update state
                    $element.val(oldValue);
                    this.selectionState[fieldName] = oldValue;
                }
            });
        },

        // Initialize Select2 for all cascading dropdowns with basic config
        initializeSelect2() {
            console.log('🔧 Setting up basic Select2 for all dropdowns...');
            
            this.hierarchy.forEach((selector, index) => {
                const $element = $(selector);
                if ($element.length) {
                    console.log(`Setting up Select2 for ${selector}`);
                    
                    // Store the current selected option value before destroying
                    const currentValue = $element.val();
                    const currentText = $element.find('option:selected').text();
                    
                    if ($element.hasClass('select2-hidden-accessible')) {
                        $element.select2('destroy');
                    }
                    
                    $element.select2({
                        theme: this.config.theme,
                        width: '100%',
                        placeholder: $element.attr('placeholder') || 'Select an option...',
                        allowClear: false,
                        disabled: index > 0,
                        tags: true,
                        createTag: (params) => {
                            const term = $.trim(params.term);
                            if (term === '') return null;
                            
                            // Check if option already exists
                            const existingOption = $element.find(`option[value="${term}"]`).length > 0;
                            if (existingOption) return null;
                            
                            console.log(`🏷️ Creating new tag: "${term}" for ${selector}`);
                            
                            return {
                                id: term,
                                text: term,
                                newTag: true
                            };
                        },
                        templateResult: (option) => {
                            if (option.loading) return option.text;

                            // FIXED: Ensure newTag property is preserved and template shows correctly
                            if (option.newTag === true || (option.element && $(option.element).data('newTag'))) {
                                return $(
                                    `<span class="text-primary">
                                        <i class="fas fa-plus-circle me-1"></i>
                                        <strong>Create: "${$.trim(option.text)}"</strong>
                                    </span>`
                                );
                            }
                            return $('<span>').text(option.text);
                        },
                        templateSelection: (option) => {
                            return $('<span>').text(option.text);
                        },
                        escapeMarkup: (markup) => markup
                    });
                    
                    // Restore the selected value if it existed
                    if (currentValue && currentText && currentValue !== '') {
                        // Check if option still exists, if not create it
                        if (!$element.find(`option[value="${currentValue}"]`).length) {
                            const newOption = new Option(currentText, currentValue, true, true);
                            $element.append(newOption);
                        }
                        $element.val(currentValue).trigger('change');
                        
                        // Update selection state
                        const fieldName = selector.replace('#', '');
                        this.selectionState[fieldName] = currentValue;
                    }
                    
                    console.log(`${selector} Select2 initialized, disabled: ${index > 0}`);
                }
            });
        },

        // FIXED: Initialize dropdown states with proper helper text
        initializeDropdownStates() {
            console.log('🔒 Setting up progressive enabling states...');
            
            this.hierarchy.forEach((selector, index) => {
                if (index === 0) {
                    this.enableDropdown(selector);
                    console.log(`✅ ${selector} enabled (first dropdown)`);
                } else {
                    this.disableDropdown(selector);
                    console.log(`🔒 ${selector} disabled`);
                }
            });
        },

        // FIXED: Enable dropdown with proper helper text update
        enableDropdown(selector) {
    const $element = $(selector);
    if (!$element.length) return;

    console.log(`🔓 Enabling dropdown: ${selector}`);
    
    if ($element.hasClass('select2-hidden-accessible')) {
        // Clear Select2 cache before enabling
        const select2Instance = $element.data('select2');
        if (select2Instance && select2Instance.dataAdapter && select2Instance.dataAdapter._cache) {
            select2Instance.dataAdapter._cache.clear();
        }
        
        $element.prop('disabled', false).select2('enable');
        const $container = $element.next('.select2-container');
        $container.removeClass('select2-container--disabled');
        $container.find('.select2-selection').removeClass('select2-selection--disabled');
    }

    $element.removeClass('disabled-dropdown');
    this.updateDropdownHelperText(selector, false);
},

        // FIXED: Disable dropdown with proper helper text update
disableDropdown(selector) {
    const $element = $(selector);
    if (!$element.length) return;

    console.log(`🔒 Disabling dropdown: ${selector}`);
    
    // FIXED: Clear value completely before disabling
    if ($element.hasClass('select2-hidden-accessible')) {
        // Store the original placeholder before destroying
        const originalPlaceholder = $element.attr('placeholder') || $element.data('placeholder') || 'Select an option...';
        
        try {
            $element.select2('destroy');
        } catch (e) {
            console.warn('Select2 destroy failed:', e);
        }
        $element.val(null);
        $element.empty();
        $element.trigger('change.cascading');

        // Reinitialize Select2 before disabling with preserved placeholder
        $element.select2({
            theme: this.config.theme,
            width: '100%',
            placeholder: originalPlaceholder, // FIXED: Preserve the placeholder
            allowClear: false,
            disabled: true
        });
        
        const $container = $element.next('.select2-container');
        $container.addClass('select2-container--disabled');
        $container.find('.select2-selection').addClass('select2-selection--disabled');
    } else {
        $element.val('').prop('disabled', true);
    }

    $element.addClass('disabled-dropdown');
    
    // Update selection state
    const fieldName = selector.replace('#', '');
    this.selectionState[fieldName] = null;
    
    // FIXED: Always update helper text when disabling
    this.updateDropdownHelperText(selector, true);
},

        // FIXED: Update helper text to show dependency
        updateDropdownHelperText(selector, isDisabled) {
            const fieldName = selector.replace('#', '');
            const $helpText = $(`#${fieldName}_help`);
            
            if (!$helpText.length) {
                console.warn(`Helper text element not found: #${fieldName}_help`);
                return;
            }

            if (isDisabled) {
                const previousField = this.getPreviousFieldName(selector);
                $helpText.html(`
                    <i class="fas fa-lock text-muted"></i> 
                    <span class="text-muted">Please select ${previousField} first to enable this field</span>
                `);
                console.log(`📝 Updated helper text for ${selector}: disabled`);
            } else {
                // Restore original help text
                const originalTexts = {
                    '#system_name': `<i class="fas fa-info-circle"></i> Select or type a system name. You can create new entries.`,
                    '#module': `<i class="fas fa-info-circle"></i> Select or type a module name. You can create new entries.`,
                    '#feature': `<i class="fas fa-info-circle"></i> Select or type a feature name. You can create new entries.`,
                    '#client': `<i class="fas fa-info-circle"></i> Select or type a client name. You can create new entries.`,
                    '#source': `<i class="fas fa-info-circle"></i> Select or type a source name. You can create new entries.`
                };
                $helpText.html(originalTexts[selector] || 'Available options will appear based on your previous selections.');
                console.log(`📝 Updated helper text for ${selector}: enabled`);
            }
        },

        // Get the name of the previous field in the hierarchy
        getPreviousFieldName(selector) {
            const currentIndex = this.hierarchy.indexOf(selector);
            if (currentIndex <= 0) return '';

            const fieldNames = {
                '#system_name': 'System Name',
                '#module': 'Module', 
                '#feature': 'Feature',
                '#client': 'Client',
                '#source': 'Source'
            };

            const previousSelector = this.hierarchy[currentIndex - 1];
            return fieldNames[previousSelector] || 'previous field';
        },

    // FIXED: Track selection state changes with immediate new tag handling
setupSelectionTracking() {
    console.log('👀 Setting up selection tracking...');
    
    this.hierarchy.forEach(selector => {
        const $element = $(selector);
        
        if (!$element.length) {
            console.warn(`Element not found: ${selector}`);
            return;
        }

        $element.off('select2:select.cascading select2:clear.cascading select2:unselect.cascading change.cascading select2:open.cascading');
        
        // FIXED: Handle tag creation IMMEDIATELY when user types
        $element.on('select2:select.cascading', (e) => {
            e.stopImmediatePropagation();
            const selectedData = e.params.data;
            const fieldName = selector.replace('#', '');
            
            console.log(`📝 ${selector} selected:`, selectedData);
            
            // FIXED: Handle new tags immediately - don't wait
            if (selectedData.newTag === true) {
                console.log(`🆕 Processing new tag immediately: "${selectedData.text}"`);
                
                // Add the option to the select element right away
                const newOption = new Option(selectedData.text, selectedData.id, true, true);
                $(newOption).data('newTag', true);
                $element.append(newOption);
                
                // Force the value to be set
                $element.val(selectedData.id);
                
                // Update state immediately
                this.selectionState[fieldName] = selectedData.id;
                
                // Process cascading immediately
                this.handleProgressiveEnabling(selector, selectedData.id);
                this.updateDependentDropdowns(selector);
                
                // Update validation
                $element.removeClass('is-invalid').addClass('is-valid');
                this.updateSelect2Validation($element);
                
                console.log(`✅ New tag processed and cascading triggered for: "${selectedData.text}"`);
                return;
            }
            
            // Handle existing options
            const newValue = selectedData.id;
            if (newValue && newValue.trim() !== '') {
                this.selectionState[fieldName] = newValue;
                this.handleProgressiveEnabling(selector, newValue);
                this.updateDependentDropdowns(selector);
                
                $element.removeClass('is-invalid').addClass('is-valid');
                this.updateSelect2Validation($element);
                
                console.log(`✅ ${selector} processed successfully: "${newValue}"`);
            }
        });

        // FIXED: Handle change events for programmatic changes
        $element.on('change.cascading', (e) => {
            // Only handle if not triggered by select2:select
            if (e.namespace && e.namespace !== 'cascading') return;
            
            const fieldName = selector.replace('#', '');
            const newValue = $element.val();
            
            console.log(`🔄 ${selector} changed: "${newValue}"`);
            
            if (newValue && newValue.trim() !== '') {
                this.selectionState[fieldName] = newValue;
                this.handleProgressiveEnabling(selector, newValue);
                this.updateDependentDropdowns(selector);
            } else {
                this.selectionState[fieldName] = null;
                this.handleProgressiveEnabling(selector, null);
                this.updateDependentDropdowns(selector);
            }
        });

        // FIXED: Handle clear events properly
        $element.on('select2:clear.cascading select2:unselect.cascading', (e) => {
            e.stopImmediatePropagation();
            const fieldName = selector.replace('#', '');
            
            console.log(`🗑️ ${selector} cleared`);
            
            // FIXED: Ensure value is completely cleared
            setTimeout(() => {
                $element.val(null);
                $element.empty();
                $element.trigger('change.cascading');
                
                // Update selection state
                this.selectionState[fieldName] = null;
                
                // FIXED: Handle progressive disabling immediately
                this.handleProgressiveEnabling(selector, null);
                this.updateDependentDropdowns(selector);
                
                // Remove validation styling
                $element.removeClass('is-valid is-invalid');
                this.updateSelect2Validation($element);
                
                console.log(`✅ ${selector} fully cleared and dependents updated`);
            }, 10);
        });

        // ADDED: Force refresh when dropdown opens if it needs refresh
        $element.on('select2:open.cascading', (e) => {
            if ($element.data('needsRefresh')) {
                console.log(`🔄 Forcing refresh for ${selector} on open`);
                
                const select2Instance = $element.data('select2');
                if (select2Instance && select2Instance.dataAdapter) {
                    // Clear cache to force fresh query
                    if (select2Instance.dataAdapter._cache) {
                        select2Instance.dataAdapter._cache.clear();
                    }
                    
                    // Trigger empty search to load initial options
                    setTimeout(() => {
                        select2Instance.dataAdapter.query({ term: '', page: 1 }, (data) => {
                            // This will trigger the AJAX call and populate options
                            console.log(`✅ Refreshed data for ${selector}:`, data);
                        });
                    }, 50);
                }
                
                // Clear the refresh flag
                $element.removeData('needsRefresh');
            }
            
            // Focus search field
            setTimeout(() => {
                $('.select2-search__field').focus();
            }, 50);
        });
    });
},
        // Update Select2 validation styling
        updateSelect2Validation($select) {
            const $container = $select.next('.select2-container');
            const $selection = $container.find('.select2-selection');
            
            $selection.removeClass('is-invalid is-valid');
            
            if ($select.hasClass('is-invalid')) {
                $selection.addClass('is-invalid');
            } else if ($select.hasClass('is-valid')) {
                $selection.addClass('is-valid');
            }
        },

        // FIXED: Handle progressive enabling based on selection
        handleProgressiveEnabling(changedSelector, value) {
            const currentIndex = this.hierarchy.indexOf(changedSelector);
            console.log(`⚡ Handling progressive enabling for ${changedSelector} (index: ${currentIndex}), value: "${value}"`);
            
            if (value && value.trim() !== '') {
                // Value selected - enable next dropdown if it exists
                if (currentIndex < this.hierarchy.length - 1) {
                    const nextSelector = this.hierarchy[currentIndex + 1];
                    console.log(`✅ Enabling next dropdown: ${nextSelector}`);
                    
                    // Small delay to ensure Select2 is ready
                    setTimeout(() => {
                        this.enableDropdown(nextSelector);
                    }, 50);
                }
            } else {
                // FIXED: Value cleared - disable all subsequent dropdowns
                console.log(`🔒 Value cleared, disabling subsequent dropdowns`);
                for (let i = currentIndex + 1; i < this.hierarchy.length; i++) {
                    const subsequentSelector = this.hierarchy[i];
                    console.log(`🔒 Disabling: ${subsequentSelector}`);
                    this.disableDropdown(subsequentSelector);
                }
            }
        },

       updateDependentDropdowns(changedDropdown) {
    const currentIndex = this.hierarchy.indexOf(changedDropdown);
    
    // Clear all subsequent dropdowns when a parent changes
    for (let i = currentIndex + 1; i < this.hierarchy.length; i++) {
        const dependentSelector = this.hierarchy[i];
        const $dependent = $(dependentSelector);
        const fieldName = dependentSelector.replace('#', '');
        
        // Clear the selection state
        this.selectionState[fieldName] = null;
        
        if (!$dependent.prop('disabled')) {
            console.log(`🗑️ Clearing and refreshing: ${dependentSelector}`);
            
            // Clear current value
            $dependent.val(null).trigger('change');
            
            // Remove validation styling
            $dependent.removeClass('is-valid is-invalid');
            this.updateSelect2Validation($dependent);
            
            // FIXED: Clear cache AND force refresh on next open
            if ($dependent.hasClass('select2-hidden-accessible')) {
                const select2Instance = $dependent.data('select2');
                if (select2Instance && select2Instance.dataAdapter) {
                    // Clear cache
                    if (select2Instance.dataAdapter._cache) {
                        select2Instance.dataAdapter._cache.clear();
                    }
                    
                    // Mark as needing refresh
                    $dependent.data('needsRefresh', true);
                }
            }
        }
    }
},
        createBaseConfig(type, placeholder) {
            return {
                theme: this.config.theme,
                tags: true,
                tokenSeparators: [],
                placeholder: placeholder,
                allowClear: false,
                createTag: (params) => {
                    const term = $.trim(params.term);
                    if (term === '') return null;
                    
                    console.log(`🆕 Creating new tag: "${term}"`);
                    
                    return {
                        id: term,
                        text: term,
                        newTag: true
                    };
                },
                templateResult: (option) => {
                    if (option.loading) return option.text;

                    // FIXED: Ensure new tag template shows correctly
                    if (option.newTag === true) {
                        console.log(`🎨 Displaying create template for: "${option.text}"`);
                        return $(
                            `<span class="text-primary">
                                <i class="fas fa-plus-circle me-1"></i>
                                <strong>Create: "${$.trim(option.text)}"</strong>
                            </span>`
                        );
                    }
                    return $('<span>').text(option.text);
                },
                templateSelection: (option) => $('<span>').text(option.text),
                escapeMarkup: (markup) => markup
            };
        },

        // === DROPDOWN INITIALIZERS (for AJAX functionality) ===
        initSystemDropdown() {
            const $system = $('#system_name');
            if (!$system.length) return;

            setTimeout(() => {
                $system.select2('destroy');
                
                const config = {
                    ...this.createBaseConfig('system', 'Select or type a system name...'),
                    disabled: false,
                    ajax: {
                        url: this.config.apiUrl,
                        dataType: 'json',
                        delay: this.config.delay,
                        data: (params) => ({
                            q: params.term || '',
                            type: 'systems'
                        }),
                        processResults: (data, params) => {
    const results = this.processApiResults(data);
    
    // FIXED: Get search term from params instead of DOM
    const searchTerm = (params.term || '').trim();
    if (searchTerm !== '') {
        const termExists = results.results.some(item => 
            item.text.toLowerCase() === searchTerm.toLowerCase()
        );
        
        if (!termExists) {
            results.results.unshift({
                id: searchTerm,
                text: searchTerm,
                newTag: true
            });
        }
    }
    
    return results;
},
                        cache: false,
transport: function(params, success, failure) {
    // Force fresh requests by adding timestamp and random number
    if (params.data) {
        params.data._timestamp = Date.now();
        params.data._cache_buster = Math.random();
    }
    
    // Clear any existing AJAX cache
    $.ajaxSetup({ cache: false });
    
    return $.ajax({
        ...params,
        cache: false,
        headers: {
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache',
            'Expires': '0'
        }
    }).done(success).fail(failure);
}
                    }
                };

                $system.select2(config);
            }, 200);
        },

        initModuleDropdown() {
            const $module = $('#module');
            if (!$module.length) return;

            setTimeout(() => {
                const isDisabled = !$('#system_name').val();
                $module.select2('destroy');
                
                const config = {
                    ...this.createBaseConfig('module', 'Select or type a module name...'),
                    disabled: isDisabled,
                    ajax: {
                        url: this.config.apiUrl,
                        dataType: 'json',
                        delay: this.config.delay,
                        data: (params) => {
                            const data = {
                                q: params.term || '',
                                type: 'modules'
                            };
                            const systemValue = $('#system_name').val();
                            if (systemValue) {
                                data.type = 'modules_by_system';
                                data.system_name = systemValue;
                            }
                            return data;
                        },
                        processResults: (data, params) => {
    const results = this.processApiResults(data);
    
    // FIXED: Get search term from params instead of DOM
    const searchTerm = (params.term || '').trim();
    if (searchTerm !== '') {
        const termExists = results.results.some(item => 
            item.text.toLowerCase() === searchTerm.toLowerCase()
        );
        
        if (!termExists) {
            results.results.unshift({
                id: searchTerm,
                text: searchTerm,
                newTag: true
            });
        }
    }
    
    return results;
},
                        cache: false,
transport: function(params, success, failure) {
    // Force fresh requests by adding timestamp and random number
    if (params.data) {
        params.data._timestamp = Date.now();
        params.data._cache_buster = Math.random();
    }
    
    // Clear any existing AJAX cache
    $.ajaxSetup({ cache: false });
    
    return $.ajax({
        ...params,
        cache: false,
        headers: {
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache',
            'Expires': '0'
        }
    }).done(success).fail(failure);
}
                    }
                };

                $module.select2(config);
                
            }, 200);
        },
        
        initFeatureDropdown() {
            const $feature = $('#feature');
            if (!$feature.length) return;

            setTimeout(() => {
                const isDisabled = !$('#module').val();
                $feature.select2('destroy');
                
                const config = {
                    ...this.createBaseConfig('feature', 'Select or type a feature name...'),
                    disabled: isDisabled,
                    ajax: {
                        url: this.config.apiUrl,
                        dataType: 'json',
                        delay: this.config.delay,
                        data: (params) => {
                            const data = {
                                q: params.term || '',
                                type: 'features'
                            };
                            const systemValue = $('#system_name').val();
                            const moduleValue = $('#module').val();
                            if (moduleValue && systemValue) {
                                data.type = 'features_by_system_module';
                                data.system_name = systemValue;
                                data.module = moduleValue;
                            }
                            return data;
                        },
                        processResults: (data, params) => {
    const results = this.processApiResults(data);
    
    // FIXED: Get search term from params instead of DOM
    const searchTerm = (params.term || '').trim();
    if (searchTerm !== '') {
        const termExists = results.results.some(item => 
            item.text.toLowerCase() === searchTerm.toLowerCase()
        );
        
        if (!termExists) {
            results.results.unshift({
                id: searchTerm,
                text: searchTerm,
                newTag: true
            });
        }
    }
    
    return results;
},
                        cache: false,
transport: function(params, success, failure) {
    // Force fresh requests by adding timestamp and random number
    if (params.data) {
        params.data._timestamp = Date.now();
        params.data._cache_buster = Math.random();
    }
    
    // Clear any existing AJAX cache
    $.ajaxSetup({ cache: false });
    
    return $.ajax({
        ...params,
        cache: false,
        headers: {
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache',
            'Expires': '0'
        }
    }).done(success).fail(failure);
}
                    }
                };

                $feature.select2(config);
                
            }, 200);
        },

        initClientDropdown() {
            const $client = $('#client');
            if (!$client.length) return;

            setTimeout(() => {
                const isDisabled = !$('#feature').val();
                $client.select2('destroy');
                
                const config = {
                    ...this.createBaseConfig('client', 'Select or type a client name...'),
                    disabled: isDisabled,
                    ajax: {
                        url: this.config.apiUrl,
                        dataType: 'json',
                        delay: this.config.delay,
                        data: (params) => {
                            const data = {
                                q: params.term || '',
                                type: 'clients'
                            };
                            const systemValue = $('#system_name').val();
                            const moduleValue = $('#module').val();
                            const featureValue = $('#feature').val();
                            if (featureValue && moduleValue && systemValue) {
                                data.type = 'clients_by_system_module_feature';
                                data.system_name = systemValue;
                                data.module = moduleValue;
                                data.feature = featureValue;
                            }
                            return data;
                        },
                        processResults: (data, params) => {
    const results = this.processApiResults(data);
    
    // FIXED: Get search term from params instead of DOM
    const searchTerm = (params.term || '').trim();
    if (searchTerm !== '') {
        const termExists = results.results.some(item => 
            item.text.toLowerCase() === searchTerm.toLowerCase()
        );
        
        if (!termExists) {
            results.results.unshift({
                id: searchTerm,
                text: searchTerm,
                newTag: true
            });
        }
    }
    
    return results;
},
                        cache: false,
transport: function(params, success, failure) {
    // Force fresh requests by adding timestamp and random number
    if (params.data) {
        params.data._timestamp = Date.now();
        params.data._cache_buster = Math.random();
    }
    
    // Clear any existing AJAX cache
    $.ajaxSetup({ cache: false });
    
    return $.ajax({
        ...params,
        cache: false,
        headers: {
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache',
            'Expires': '0'
        }
    }).done(success).fail(failure);
}
                    }
                };

                $client.select2(config);
                
            }, 200);
        },

        initSourceDropdown() {
            const $source = $('#source');
            if (!$source.length) return;

            setTimeout(() => {
                const isDisabled = !$('#client').val();
                $source.select2('destroy');
                
                const config = {
                    ...this.createBaseConfig('source', 'Select or type a source name...'),
                    disabled: isDisabled,
                    ajax: {
                        url: this.config.apiUrl,
                        dataType: 'json',
                        delay: this.config.delay,
                        data: (params) => {
                            const data = {
                                q: params.term || '',
                                type: 'sources'
                            };
                            const systemValue = $('#system_name').val();
                            const moduleValue = $('#module').val();
                            const featureValue = $('#feature').val();
                            const clientValue = $('#client').val();
                            if (clientValue && featureValue && moduleValue && systemValue) {
                                data.type = 'sources_by_complete_hierarchy';
                                data.system_name = systemValue;
                                data.module = moduleValue;
                                data.feature = featureValue;
                                data.client = clientValue;
                            }
                            return data;
                        },
                        processResults: (data, params) => {
    const results = this.processApiResults(data);
    
    // FIXED: Get search term from params instead of DOM
    const searchTerm = (params.term || '').trim();
    if (searchTerm !== '') {
        const termExists = results.results.some(item => 
            item.text.toLowerCase() === searchTerm.toLowerCase()
        );
        
        if (!termExists) {
            results.results.unshift({
                id: searchTerm,
                text: searchTerm,
                newTag: true
            });
        }
    }
    
    return results;
},
                        cache: false,
transport: function(params, success, failure) {
    // Force fresh requests by adding timestamp and random number
    if (params.data) {
        params.data._timestamp = Date.now();
        params.data._cache_buster = Math.random();
    }
    
    // Clear any existing AJAX cache
    $.ajaxSetup({ cache: false });
    
    return $.ajax({
        ...params,
        cache: false,
        headers: {
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache',
            'Expires': '0'
        }
    }).done(success).fail(failure);
}
                    }
                };

                $source.select2(config);
                
            }, 200);
        },

        setupFileUploadToggles() {
    console.log('🔧 Setting up file upload toggles...');
    
    $('#uploadToggle').off('click.cascading').on('click.cascading', function () {
        $(this).addClass('active btn-success text-white').removeClass('btn-outline-success text-success');
        $('#urlToggle').removeClass('active btn-success text-white').addClass('btn-outline-success text-success');
        $('#sample_file').removeClass('d-none').prop('disabled', false);
        $('#file_url').addClass('d-none').prop('disabled', true).val('').removeClass('is-valid is-invalid');
    });

    $('#urlToggle').off('click.cascading').on('click.cascading', function () {
        $(this).addClass('active btn-success text-white').removeClass('btn-outline-success text-success');
        $('#uploadToggle').removeClass('active btn-success text-white').addClass('btn-outline-success text-success');
        $('#sample_file').addClass('d-none').prop('disabled', true).val('').removeClass('is-valid is-invalid');
        $('#file_url').removeClass('d-none').prop('disabled', false);
    });
},

        // === UTILS ===
        processApiResults(data) {
            if (!data || !data.success) {
                console.warn('API Error or no data:', data);
                return { results: [] };
            }
            return {
                results: (data.data || []).map(item => ({
                    id: item,
                    text: item
                }))
            };
        },

        clearAndRefreshDropdown(selector) {
            const $element = $(selector);
            if (!$element.length || $element.prop('disabled')) return;

            $element.val(null).trigger('change.cascading');

            setTimeout(() => {
                this.refreshDropdownWithFilter(selector);
            }, 100);
        },

       refreshDropdownWithFilter(selector) {
    const $element = $(selector);
    if (!$element.length || !$element.hasClass('select2-hidden-accessible') || $element.prop('disabled')) return;

    // FIXED: Don't destroy - just clear the cache
    const select2Instance = $element.data('select2');
    if (select2Instance && select2Instance.dataAdapter) {
        // Clear the cache but keep the instance alive
        if (select2Instance.dataAdapter._cache) {
            select2Instance.dataAdapter._cache.clear();
        }
        
        console.log(`🔄 Cleared cache for ${selector} without destroying instance`);
    }
},

        showToast(message, type = 'info') {
            if (window.showToast) {
                window.showToast(message, type);
            } else {
                console.log(`${type.toUpperCase()}: ${message}`);
            }
        }
    };

    // Enhanced form validation
    const FormValidator = {
        validateCascading() {
            let isValid = true;
            const requiredFields = [
                { selector: '#system_name', name: 'System Name' },
                { selector: '#module', name: 'Module' },
                { selector: '#feature', name: 'Feature' },
                { selector: '#client', name: 'Client' },
                { selector: '#source', name: 'Source' }
            ];

            requiredFields.forEach(field => {
                const $element = $(field.selector);
                const value = $element.val();

                if (!$element.prop('disabled')) {
                    if (!value || value.trim() === '') {
                        $element.addClass('is-invalid').removeClass('is-valid');
                        if ($element.hasClass('select2-hidden-accessible')) {
                            $element.next('.select2-container').find('.select2-selection').addClass('is-invalid');
                        }
                        isValid = false;
                    } else {
                        $element.removeClass('is-invalid').addClass('is-valid');
                        if ($element.hasClass('select2-hidden-accessible')) {
                            $element.next('.select2-container').find('.select2-selection').removeClass('is-invalid');
                        }
                    }
                }
            });

            return isValid;
        },

        setupFormSubmission() {
            $('#addFeatureForm').on('submit', (e) => {
                if (!this.validateCascading()) {
                    e.preventDefault();
                    CascadingDropdown.showToast('Please fill out all required fields.', 'error');

                    const $firstInvalid = $('.is-invalid').first();
                    if ($firstInvalid.length) {
                        $firstInvalid[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                        $firstInvalid.focus();
                    }
                }
            });
        }
    };

    // Add custom CSS for disabled dropdowns
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .select2-container--disabled .select2-selection {
                background-color: #f8f9fa !important;
                border-color: #dee2e6 !important;
                color: #6c757d !important;
                cursor: not-allowed !important;
                opacity: 0.65;
            }
            
            .select2-container--disabled .select2-selection__arrow {
                display: none !important;
            }
            
            .select2-container--disabled:hover .select2-selection {
                border-color: #dee2e6 !important;
            }
        `)
        .appendTo('head');

    // Initialize everything
    CascadingDropdown.init();
    FormValidator.setupFormSubmission();

    $('.select2-dropdown, #description').on('change input', () => {
        FormValidator.validateCascading();
    });

    console.log('🎯 Enhanced progressive cascading dropdown system ready');
    
    // Expose CascadingDropdown for potential external access
    window.CascadingDropdown = CascadingDropdown;
});