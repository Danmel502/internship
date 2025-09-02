document.addEventListener('DOMContentLoaded', () => {
    // Global state management
    const AppState = {
        removedFiles: {},
        searchTimeout: null,
        mutationObserver: null
    };

    // Configuration constants
    const CONFIG = {
        SEARCH_DELAY: 300,
        SELECT2_DELAY: 100,
        ALERT_HIDE_DELAY: 5000,
        IMAGE_TYPES: ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        FILE_ICONS: {
            pdf: '📄',
            doc: '📝',
            docx: '📝',
            txt: '📃',
            zip: '🗜️',
            rar: '🗜️'
        }
    };

    /** ✅ Toast Message Handler **/
    const ToastManager = {
        show(message, type = 'success') {
            const toastEl = document.getElementById('toastMessage');
            const toastBody = document.getElementById('toastContent');

            if (!toastEl || !toastBody) {
                console.warn('Toast elements not found');
                return;
            }

            toastEl.classList.remove('bg-success', 'bg-danger');
            toastEl.classList.add(type === 'error' ? 'bg-danger' : 'bg-success');
            toastBody.textContent = message;

            try {
                const toast = new bootstrap.Toast(toastEl);
                toast.show();
            } catch (error) {
                console.error('Error showing toast:', error);
            }
        }
    };

    /** 🔄 Enhanced Select2 Management - EDIT MODALS ONLY **/
const Select2Manager = {
    isAvailable() {
        return typeof $ !== 'undefined' && $.fn.select2;
    },

    // Replace the getConfig method in Select2Manager
getConfig(fieldType = 'system', currentValue = '') {
    const configs = {
        system: {
            placeholder: "Select or type a system name..."
        },
        module: {
            placeholder: "Select or type a module..."
        },
        feature: {
            placeholder: "Select or type a feature..."
        },
        client: {
            placeholder: "Select or type a client..."
        },
        source: {
            placeholder: "Select or type a source..."
        }
    };

    const config = configs[fieldType] || configs.system;

    return {
        placeholder: config.placeholder,
        allowClear: true,
        tags: true,
        tokenSeparators: [','],
        minimumInputLength: 0,
        width: '100%',
        selectOnClose: false,
        closeOnSelect: true,
        theme: 'bootstrap-5',
        // Remove AJAX - use static options from PHP
        createTag: (params) => {
            const term = $.trim(params.term);
            if (term === '') return null;
            
            return {
                id: term,
                text: term,
                newTag: true
            };
        },
        templateResult: (option) => {
            if (option.loading) return option.text;
            if (option.newTag) {
                return $(`<span class="text-primary"><strong>Create: "${option.text}"</strong></span>`);
            }
            return option.text;
        },
        templateSelection: (option) => option.text,
        escapeMarkup: (markup) => markup
    };
},

  initializeField(selector, fieldType = 'system', currentValue = '') {
    if (!this.isAvailable()) {
        console.warn('jQuery or Select2 not available');
        return;
    }

    const $element = $(selector);
    if ($element.length === 0) {
        console.warn(`Select2 element not found: ${selector}`);
        return;
    }

    // Skip add form dropdowns completely
    if ($element.closest('#addFeatureForm').length > 0) {
        console.log(`⚠️ SKIPPING ${selector} - This is handled by cascading dropdown system`);
        return;
    }

    // Only process edit modal dropdowns
    if (!$element.hasClass(`select2-${fieldType}-edit`)) {
        console.log(`⚠️ SKIPPING ${selector} - Not an edit modal dropdown`);
        return;
    }

    // Destroy existing instance
    if ($element.hasClass('select2-hidden-accessible')) {
        $element.select2('destroy');
    }

    try {
        const modal = $element.closest('.modal');
        const config = this.getConfig(fieldType, currentValue);
        config.dropdownParent = modal.length ? modal : $('body');

        // The options are already populated by PHP, just initialize Select2
        $element.select2(config)
            .on('select2:open', () => {
                setTimeout(() => {
                    const searchField = $('.select2-search__field');
                    searchField.focus();
                    
                    searchField.off('keydown.preventSpace').on('keydown.preventSpace', function(e) {
                        if (e.keyCode === 32) {
                            e.stopPropagation();
                        }
                        
                        if (e.keyCode === 13 || e.keyCode === 9) {
                            const currentText = $(this).val();
                            if (currentText && currentText.trim()) {
                                return true;
                            }
                        }
                    });
                }, CONFIG.SELECT2_DELAY);
            })
            .on('select2:select', (e) => {
                const data = e.params.data;
                if (data.newTag) {
                    console.log('🆕 New value added:', data.text);
                }
            })
            .on('select2:clear', function(e) {
                console.log('🗑️ Edit modal dropdown cleared:', $(this).attr('id'));
            })
            .on('select2:close', function(e) {
                const $this = $(this);
                const searchTerm = $('.select2-search__field').val();
                
                if (searchTerm && searchTerm.trim()) {
                    const trimmedTerm = searchTerm.trim();
                    const currentValue = $this.val();
                    
                    let exists = false;
                    $this.find('option').each(function() {
                        if ($(this).text().toLowerCase() === trimmedTerm.toLowerCase()) {
                            exists = true;
                            $(this).prop('selected', true);
                            return false;
                        }
                    });
                    
                    if (!exists && !currentValue) {
                        const newOption = new Option(trimmedTerm, trimmedTerm, true, true);
                        $this.append(newOption);
                    }
                    
                    $this.trigger('change');
                }
            });
    } catch (error) {
        console.error('Error initializing Select2:', error);
        ToastManager.show('Error initializing search dropdown', 'error');
    }
}
};

    /** ✅ Form Validation **/
    const FormValidator = {
        validateField(input, name) {
            let value = '';
            
            // Skip validation for add form dropdowns - let cascading dropdown handle it
            if (input.closest('#addFeatureForm') && input.classList.contains('cascading-dropdown')) {
                return true;
            }
            
            if (name === 'system_name' && input.classList.contains('select2-system-edit')) {
                value = $(input).val() || '';
            } else {
                value = input.value?.trim() || '';
            }
            
            const isValid = value !== '';
            
            if (isValid) {
                input.classList.remove('is-invalid');
                if (name === 'system_name' && input.classList.contains('select2-system-edit')) {
                    $(input).next('.select2-container').removeClass('is-invalid');
                }
            } else {
                input.classList.add('is-invalid');
                if (name === 'system_name' && input.classList.contains('select2-system-edit')) {
                    $(input).next('.select2-container').addClass('is-invalid');
                }
            }
            
            return isValid;
        },

        setupAddForm() {
            const addForm = document.getElementById('addFeatureForm');
            if (!addForm) return;

            addForm.addEventListener('submit', (e) => {
                // Let cascading dropdown handle add form validation
                console.log('📝 Add form submission - handled by cascading dropdown system');
            });
        }
    };

    /** 🔍 Search Management **/
    const SearchManager = {
        setup() {
            const searchInput = document.getElementById("searchInput");
            if (!searchInput) return;

            const pagination = document.querySelector(".pagination");

            searchInput.addEventListener("input", () => {
                if (AppState.searchTimeout) {
                    clearTimeout(AppState.searchTimeout);
                }

                AppState.searchTimeout = setTimeout(async () => {
                    const query = searchInput.value.trim();

                    if (!query) {
                        window.location.href = 'index.php';
                        return;
                    }

                    if (pagination) {
                        pagination.style.display = 'none';
                    }

                    try {
                        await this.performSearch(query);
                    } catch (error) {
                        console.error("Search error:", error);
                        this.showSearchError(error.message);
                    }
                }, CONFIG.SEARCH_DELAY);
            });
        },

        async performSearch(query) {
    const response = await fetch(`search.php?q=${encodeURIComponent(query)}`);
    
    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const result = await response.json();
    
    // Handle error responses from search.php
    if (result.error) {
        throw new Error(result.message || result.error);
    }
    
    // Extract the data array from the wrapped response
    const data = result.data || result || [];
    
    TableRenderer.render(data);
},

        showSearchError(message) {
            const tbody = document.querySelector("table tbody");
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error fetching results: ${message}</td></tr>`;
            }
        }
    };

    /** 📄 Table Rendering **/
    const TableRenderer = {
        render(data) {
            const tbody = document.querySelector("table tbody");
            if (!tbody) return;

            this.clearTable();

            if (!Array.isArray(data) || data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted">No results found.</td></tr>`;
                return;
            }

            data.forEach(item => {
                if (item._id) {
                    this.renderRow(item, tbody);
                }
            });
        },

        clearTable() {
            const tbody = document.querySelector("table tbody");
            if (tbody) {
                tbody.innerHTML = '';
            }
            
            document.querySelectorAll('.modal[data-generated="true"]').forEach(modal => {
                modal.remove();
            });
        },

        renderRow(item, tbody) {
    const sampleCell = this.createSampleCell(item);
    
    const row = `
        <tr>
            <td>
                <input type="checkbox" class="feature-checkbox form-check-input" 
                       value="${item._id}"
                       data-name="${Utils.escapeHTML(item.feature || 'Unknown')}">
            </td>
            <td>${Utils.escapeHTML(item.created_at || 'N/A')}</td>
            <td>${Utils.escapeHTML(item.system_name || 'N/A')}</td>
            <td>${Utils.escapeHTML(item.module || 'N/A')}</td>
            <td>${item.feature ? Utils.escapeHTML(item.feature) : '<span class="text-muted">N/A</span>'}</td>
            <td>
                <div class="text-truncate" style="max-width: 200px;" title="${Utils.escapeHTML(item.description || '')}">
                    ${Utils.escapeHTML(item.description || 'N/A')}
                </div>
            </td>
            <td>${Utils.escapeHTML(item.client || 'N/A')}</td>
            <td>${item.source ? Utils.escapeHTML(item.source) : '<span class="text-muted">N/A</span>'}</td>
            <td>${sampleCell}</td>
            <td>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-sm btn-success" 
                            data-bs-toggle="modal" 
                            data-bs-target="#editModal${item._id}">Edit</button>
                    <button class="btn btn-sm btn-danger" 
                            onclick="confirmDelete('${item._id}', '${Utils.escapeHTML(item.feature || item.system_name || 'Unknown')}')">Delete</button>
                </div>
            </td>
        </tr>
    `;

    tbody.insertAdjacentHTML('beforeend', row);
},

        createSampleCell(item) {
            if (!item.sample_file) {
                return '<span class="text-muted">N/A</span>';
            }

            const ext = item.sample_file.split('.').pop().toLowerCase();
            
            if (CONFIG.IMAGE_TYPES.includes(ext)) {
                return `<a href="${Utils.escapeHTML(item.sample_file)}" target="_blank">
                    <img src="${Utils.escapeHTML(item.sample_file)}" alt="Preview" class="preview-thumb">
                </a>`;
            }
            
            const icon = CONFIG.FILE_ICONS[ext] || '📁';
            const filename = item.sample_file.split('/').pop();
            const truncated = filename.length > 20 ? filename.slice(0, 17) + '...' : filename;
            
            return `${icon} <a href="${Utils.escapeHTML(item.sample_file)}" target="_blank" title="${Utils.escapeHTML(filename)}">${Utils.escapeHTML(truncated)}</a>`;
        }
    };

    /** 🔧 Utility Functions **/
    const Utils = {
        escapeHTML(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    };

    /** 🧹 Cleanup on page unload **/
    window.addEventListener('beforeunload', () => {
        if (AppState.mutationObserver) {
            AppState.mutationObserver.disconnect();
        }
        if (AppState.searchTimeout) {
            clearTimeout(AppState.searchTimeout);
        }
    });

    /** 🚀 Global Functions (exposed for inline event handlers) **/
    window.showToast = ToastManager.show.bind(ToastManager);

    /** 🏁 Application Initialization **/
    function initializeApp() {
        try {
            if (typeof $ !== 'undefined' && $.fn.select2) {
                // CRITICAL: Only initialize edit modals, NEVER add form dropdowns
                console.log('✅ Edit modal Select2 system initialized');
                console.log('⚠️ Add form dropdowns will be handled by cascading-dropdown.js');
                
            } else {
                console.warn('jQuery or Select2 not available, retrying in 100ms...');
                setTimeout(initializeApp, 100);
                return;
            }
            
            FormValidator.setupAddForm();
            SearchManager.setup();
            
            console.log('✅ Main application initialized successfully');
        } catch (error) {
            console.error('❌ Error initializing application:', error);
            ToastManager.show('Error initializing application', 'error');
        }
    }

    /** 🧱 Modal Management **/
const ModalManager = {
    async loadEditModal(featureId) {
        try {
            // Check if modal already exists
            const existingModal = document.getElementById(`editModal${featureId}`);
            if (existingModal) {
                return; // Modal already loaded
            }
            
            const response = await fetch(`get_edit_modal.php?id=${featureId}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const modalHTML = await response.text();
            
            // Create modal container and add to body
            const modalContainer = document.createElement('div');
            modalContainer.innerHTML = modalHTML;
            document.body.appendChild(modalContainer.firstElementChild);
            
            console.log(`✅ Edit modal loaded for feature: ${featureId}`);
            
        } catch (error) {
            console.error('Error loading edit modal:', error);
            ToastManager.show('Error loading edit form', 'error');
        }
    }
};

// Add click handler for edit buttons
document.addEventListener('click', async (e) => {
    if (e.target.classList.contains('btn-success') && e.target.textContent === 'Edit') {
        const target = e.target.getAttribute('data-bs-target');
        if (target && target.startsWith('#editModal')) {
            const featureId = target.replace('#editModal', '');
            await ModalManager.loadEditModal(featureId);
        }
    }
});

// Add to initializeApp()
function initializeApp() {
    try {
        // Remove Select2Manager.initializeEditModals(); line
        
        FormValidator.setupAddForm();
        SearchManager.setup();
        
        console.log('✅ Main application initialized successfully');
    } catch (error) {
        console.error('❌ Error initializing application:', error);
        ToastManager.show('Error initializing application', 'error');
    }
}

    // Start the application
    initializeApp();
});