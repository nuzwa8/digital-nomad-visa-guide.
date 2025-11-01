/**
 * SSM Digital Nomad Guide (JavaScript)
 * Handles UI mounting, (AJAX) calls, filtering, and rendering the country list.
 * All code runs within a single top-level Immediately Invoked Function Expression (IIFE).
 */
(() => {
    'use strict';

    // Global data object localized from (PHP)
    const data = window.ssmData || {};
    let countriesData = []; // To store all fetched countries

    // --------------------------------------------------------------------------
    /** Part 1 — IIFE Scope, Utilities, and Initialization */
    // --------------------------------------------------------------------------

    /**
     * Handles all (WordPress AJAX) requests.
     * @param {string} action The wp_ajax action name.
     * @param {object} requestData Data to send with the request.
     * @returns {Promise<object>} The JSON response data.
     */
    const wpAjax = (action, requestData = {}) => {
        return new Promise((resolve, reject) => {
            if (!data.ajax_url || !data.nonce) {
                console.error("AJAX Error: Missing ajax_url or nonce in ssmData.", data);
                return reject(new Error(data.strings.error_title + ': AJAX information is unavailable.'));
            }

            const payload = {
                action: action,
                nonce: data.nonce,
                ...requestData
            };

            jQuery.post(data.ajax_url, payload)
                .done(response => {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        // Handle server-side errors (e.g. nonce failure)
                        const errorMessage = response.data && response.data.message ? response.data.message : data.strings.error_title + ': Server error.';
                        reject(new Error(errorMessage));
                    }
                })
                .fail((jqXHR, textStatus, errorThrown) => {
                    // Handle network or (HTTP) errors
                    reject(new Error(data.strings.error_title + `: Network error or ${errorThrown}`));
                });
        });
    };

    /**
     * Clones and mounts an HTML template by its ID.
     * @param {string} templateId The ID of the <template> element.
     * @returns {HTMLElement|null} The mounted content fragment.
     */
    const mountTemplate = (templateId) => {
        const template = document.getElementById(templateId);
        if (!template) {
            console.warn(`Template Warning: Template ID "${templateId}" not found.`);
            return null;
        }
        // Use cloneNode(true) to get a deep clone of the template content
        return document.importNode(template.content, true);
    };

    /**
     * Simple (HTML) escaping function (security/XSS).
     * @param {string} str The string to escape.
     * @returns {string} The escaped string.
     */
    const escapeHtml = (str) => {
        if (typeof str !== 'string') return '';
        return str.replace(/[&<>"']/g, function (match) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[match];
        });
    };

    // --------------------------------------------------------------------------
    /** Part 2 — Core Functionality: Fetching and Rendering */
    // --------------------------------------------------------------------------

    /**
     * Fetches the country list via (AJAX) and stores it.
     */
    const fetchCountries = async () => {
        const loader = document.getElementById('ssm-dng-loader');
        if (loader) loader.style.display = 'block';

        try {
            const response = await wpAjax('ssm_dng_get_country_list');
            countriesData = Object.values(response.countries).map(country => ({
                // Ensure every country has a slug for filtering reference
                ...country,
                slug: country.name ? country.name.toLowerCase().replace(/[^a-z0-9]+/g, '-') : ''
            }));
            // Render the initial list after fetching
            renderCountryList(countriesData);
        } catch (error) {
            const listContainer = document.getElementById('ssm-dng-country-list');
            if (listContainer) {
                listContainer.innerHTML = `<div class="ssm-dng-error-message"><p>${error.message}</p></div>`;
            }
            console.error('AJAX Fetch Error:', error);
        } finally {
            if (loader) loader.style.display = 'none';
        }
    };

    /**
     * Renders the list of countries into the container.
     * @param {Array} countries The array of country objects to render.
     */
    const renderCountryList = (countries) => {
        const container = document.getElementById('ssm-dng-country-list');
        if (!container) return;

        // Clear previous card content
        const listGrid = container;
        listGrid.querySelectorAll('.ssm-dng-card, .ssm-dng-no-data').forEach(el => el.remove()); 

        if (countries.length === 0) {
            const noData = document.createElement('p');
            noData.className = 'ssm-dng-no-data';
            noData.textContent = data.strings.no_countries;
            listGrid.appendChild(noData);
            return;
        }

        const cardTemplate = mountTemplate('ssm-dng-country-card-template');

        if (!cardTemplate) return;

        countries.forEach(country => {
            const card = document.importNode(cardTemplate.content, true); // Use content
            const cardRoot = card.querySelector('.ssm-dng-card');
            
            // NOTE: Directly manipulating template (HTML) string for replacement is complex and prone to errors.
            // Safer way: query selectors inside the cloned fragment and update attributes/text content.
            if (cardRoot) {
                cardRoot.setAttribute('data-country-slug', escapeHtml(country.slug));
                cardRoot.querySelector('.ssm-dng-country-title').innerHTML = `${escapeHtml(country.flag)} ${escapeHtml(country.name)}`;
                cardRoot.querySelector('[data-ssm-field="income"]').textContent = escapeHtml(country.income || 'N/A');
                cardRoot.querySelector('[data-ssm-field="tax"]').textContent = escapeHtml(country.tax || 'N/A');
                cardRoot.querySelector('[data-ssm-field="cost_of_living"]').textContent = escapeHtml(country.cost_of_living || 'N/A');
                cardRoot.querySelector('[data-ssm-field="family"]').textContent = escapeHtml(country.family || 'N/A');
                cardRoot.querySelector('[data-ssm-field="guide"]').textContent = escapeHtml(country.guide || data.strings.no_countries);
                cardRoot.querySelector('.ssm-dng-apply-link').setAttribute('href', escapeHtml(country.link || '#'));
            }
            
            listGrid.appendChild(card);
        });
    };

    // --------------------------------------------------------------------------
    /** Part 3 — Filtering and Search Logic */
    // --------------------------------------------------------------------------

    /**
     * Helper to check if a numeric income (in EUR) falls into a given category.
     * NOTE: This is complex due to (PHP) income strings. We keep the simplified text check.
     * @param {string} incomeStr The income string from the data.
     * @param {string} category 'low', 'medium', or 'high'.
     * @returns {boolean}
     */
    const checkIncomeCategory = (incomeStr, category) => {
        if (!incomeStr) return false;
        const incomeLower = incomeStr.toLowerCase();
        
        if (category === 'low') {
            // Approx < €2,000
            return incomeLower.includes('less than €2,000') || incomeLower.includes('€1,500'); 
        } else if (category === 'medium') {
            // Approx €2,000 to €4,000
            return incomeLower.includes('€2,000 to €4,000') || incomeLower.includes('€2,100') || incomeLower.includes('€3,280') || incomeLower.includes('medium');
        } else if (category === 'high') {
            // Approx > €4,000
            return incomeLower.includes('more than €4,000') || incomeLower.includes('€5,000') || incomeLower.includes('high');
        }
        return false;
    };


    /**
     * Filters the global countriesData based on search text and income dropdown.
     */
    const applyFilters = () => {
        const searchInput = document.getElementById('ssm-dng-search');
        const incomeFilter = document.getElementById('ssm-dng-filter-income');
        
        if (!searchInput || !incomeFilter) return;

        const searchText = searchInput.value.toLowerCase().trim();
        const selectedIncome = incomeFilter.value;

        const filteredCountries = countriesData.filter(country => {
            // 1. Search Filter (Name, Income, Guide)
            const matchesSearch = !searchText || (
                (country.name && country.name.toLowerCase().includes(searchText)) ||
                (country.income && country.income.toLowerCase().includes(searchText)) ||
                (country.guide && country.guide.toLowerCase().includes(searchText))
            );

            // 2. Income Filter
            const matchesIncome = !selectedIncome || checkIncomeCategory(country.income, selectedIncome);

            return matchesSearch && matchesIncome;
        });

        renderCountryList(filteredCountries);
    };

    /**
     * Attaches event listeners for filtering and resetting.
     */
    const setupListeners = () => {
        const searchInput = document.getElementById('ssm-dng-search');
        const incomeFilter = document.getElementById('ssm-dng-filter-income');
        const resetButton = document.getElementById('ssm-dng-reset-filters');

        if (searchInput) {
            searchInput.addEventListener('input', applyFilters);
        }
        if (incomeFilter) {
            incomeFilter.addEventListener('change', applyFilters);
        }
        if (resetButton) {
            resetButton.addEventListener('click', () => {
                if (searchInput) searchInput.value = '';
                if (incomeFilter) incomeFilter.value = '';
                renderCountryList(countriesData); // Render the original, unfiltered list
            });
        }
    };

    // --------------------------------------------------------------------------
    /** Part 4 — Initialization */
    // --------------------------------------------------------------------------

    /**
     * Mounts the main application template and initializes data fetching.
     */
    const initDashboard = () => {
        const root = document.getElementById('ssm-dng-root');
        if (!root) {
            console.warn('Initialization Error: Root element #ssm-dng-root not found.');
            return;
        }

        const templateContent = mountTemplate('ssm-dng-dashboard-template');
        if (templateContent) {
            // Clear the "Loading..." text and append the template structure
            root.innerHTML = ''; 
            root.appendChild(templateContent);
        }

        // 1. Fetch data
        fetchCountries();
        
        // 2. Setup listeners for user interaction
        setupListeners();

        console.log('SSM Digital Nomad Guide: Dashboard initialized successfully.');
    };


    // Wait for the (DOM) to be fully loaded before starting the app
    document.addEventListener('DOMContentLoaded', () => {
        // We only initialize if our root element exists on the page (admin or front-end shortcode)
        if (document.getElementById('ssm-dng-root')) {
            initDashboard();
        }
    });

})(); // End of IIFE
