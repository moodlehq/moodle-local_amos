// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * JS module for the AMOS translator filter.
 *
 * @module      local_amos/filter
 * @package     local_amos
 * @copyright   2020 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {debounce} from 'core/utils';

const SELECTORS = {
    ROOT: {
        ID: 'amosfilter',
        REGION: 'amosfilter'
    },
    FCMP: {
        ID: 'amosfilter_fcmp',
        REGION: 'amosfilter_fcmp'
    },
    FCMPITEM: {
        REGION: 'amosfilter_fcmp_item'
    },
    FCMPCOUNTER: {
        ID: 'amosfilter_fcmp_counter'
    },
    FCMPSEARCH: {
        ID: 'amosfilter_fcmp_search'
    },
    FLNG: {
        ID: 'amosfilter_flng',
        REGION: 'amosfilter_flng'
    },
    FLNGCOUNTER: {
        ID: 'amosfilter_flng_counter'
    },
    FLNGSEARCH: {
        ID: 'amosfilter_flng_search'
    }
};

/**
 * Initialise the module and register events handlers.
 *
 * @function init
 */
export const init = () => {
    registerEventListeners();
    updateCounterOfSelectedComponents();
    updateCounterOfSelectedLanguages();
};

/**
 * @function registerEventListeners
 * @param {Element} root
 */
const registerEventListeners = () => {
    let root = document.getElementById(SELECTORS.ROOT.ID);
    let fcmp = document.getElementById(SELECTORS.FCMP.ID);
    let componentSearch = document.getElementById(SELECTORS.FCMPSEARCH.ID);
    let flng = document.getElementById(SELECTORS.FLNG.ID);
    let languageSearch = document.getElementById(SELECTORS.FLNGSEARCH.ID);

    // Click event delegation.
    root.addEventListener('click', e => {
        if (!e.target.hasAttribute('data-action')) {
            return;
        }

        let action = e.target.getAttribute('data-action');
        let region = e.target.closest('[data-region]').getAttribute('data-region');

        if (region == SELECTORS.FCMP.REGION) {
            handleComponentSelectorAction(e, fcmp, action);
        }
    });

    // Input change event delegation.
    root.addEventListener('change', e => {
        if (e.target.getAttribute('id').startsWith('amosfilter_fcmp_')) {
            updateCounterOfSelectedComponents();
        }
        if (e.target.getAttribute('id') == SELECTORS.FLNG.ID) {
            updateCounterOfSelectedLanguages();
        }
    });

    // Prevent form submission on pressing Enter in the component search and language input.
    [componentSearch, languageSearch].forEach(inputField => {
        inputField.addEventListener('keypress', e => {
            if (e.keyCode == 13) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
    });

    // Handle component search.
    componentSearch.addEventListener('input', debounce(e => {
        handleComponentSearch(e, componentSearch, fcmp);
    }, 200));

    // Handle language search.
    languageSearch.addEventListener('input', debounce(e => {
        handleLanguageSearch(e, languageSearch, flng);
    }, 200));
};

/**
 * @function handleComponentSelectorAction
 * @param {Event} e
 * @param {Element} fcmp
 * @param {string} action
 */
const handleComponentSelectorAction = (e, fcmp, action) => {

    let selectorComponentItem = `:scope [data-region="${SELECTORS.FCMPITEM.REGION}"]:not(.hidden) input[name="fcmp[]"]`;

    if (action == 'selectstandard') {
        e.preventDefault();
        let selectorByType = type => `${selectorComponentItem}[data-component-type="${type}"]`;
        fcmp.querySelectorAll(`${selectorByType('core')}, ${selectorByType('standard')}`).forEach(item => {
            item.checked = true;
        });
    }

    if (action == 'selectapp') {
        e.preventDefault();
        fcmp.querySelectorAll(`${selectorComponentItem}[data-component-app]`).forEach(item => {
            item.checked = true;
        });
    }

    if (action == 'selectall') {
        e.preventDefault();
        fcmp.querySelectorAll(`${selectorComponentItem}`).forEach(item => {
            item.checked = true;
        });
    }

    if (action == 'selectnone') {
        e.preventDefault();
        fcmp.querySelectorAll(`${selectorComponentItem}`).forEach(item => {
            item.checked = false;
        });
    }

    updateCounterOfSelectedComponents();
};

/**
 * @function handleComponentSearch
 * @param {Event} e
 * @param {Element} inputField
 * @param {Element} fcmp
 */
const handleComponentSearch = (e, inputField, fcmp) => {
    let needle = inputField.value.toString().replace(/^ +| +$/, '').toLowerCase();

    fcmp.querySelectorAll(':scope label[for^="amosfilter_fcmp_f_"]').forEach(item => {
        let row = item.closest('[data-region="amosfilter_fcmp_item"]');
        if (needle == '' || item.innerText.toString().toLowerCase().indexOf(needle) !== -1) {
            row.classList.remove('hidden');
        } else {
            row.classList.add('hidden');
        }
    });
};

/**
 * @function updateCounterOfSelectedComponents
 */
const updateCounterOfSelectedComponents = () => {
    let fcmp = document.getElementById(SELECTORS.FCMP.ID);
    let counter = document.getElementById(SELECTORS.FCMPCOUNTER.ID);
    let count = fcmp.querySelectorAll(':scope input[name="fcmp[]"]:checked').length;

    if (count == 0) {
        counter.classList.add('badge-danger');
    } else {
        counter.classList.remove('badge-danger');
    }

    counter.textContent = count;
};

/**
 * @function handleLanguageSearch
 * @param {Event} e
 * @param {Element} inputField
 * @param {Element} flng
 */
const handleLanguageSearch = (e, inputField, flng) => {
    let needle = inputField.value.toString().replace(/^ +| +$/, '').toLowerCase();

    flng.querySelectorAll(':scope option').forEach(item => {
        if (needle == '' || item.text.toString().toLowerCase().indexOf(needle) !== -1) {
            item.classList.remove('hidden');
            item.removeAttribute('disabled');
        } else {
            item.classList.add('hidden');
            item.setAttribute('disabled', 'disabled');
        }
    });
};

/**
 * @function updateCounterOfSelectedLanguages
 */
const updateCounterOfSelectedLanguages = () => {
    let flng = document.getElementById(SELECTORS.FLNG.ID);
    let counter = document.getElementById(SELECTORS.FLNGCOUNTER.ID);
    let count = flng.querySelectorAll(':scope option:checked').length;

    if (count == 0) {
        counter.classList.add('badge-danger');
    } else {
        counter.classList.remove('badge-danger');
    }

    counter.textContent = count;
};

