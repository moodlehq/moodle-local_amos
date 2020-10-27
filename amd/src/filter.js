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
import {get_string as getString} from 'core/str';

/**
 * Initialise the module and register events handlers.
 *
 * @function init
 */
export const init = () => {
    registerEventListeners();
    updateCounterOfSelectedComponents();
    updateCounterOfSelectedLanguages();
    showUsedAdvancedOptions();
    scrollToFirstSelectedComponent();
};

/**
 * @function registerEventListeners
 * @param {Element} root
 */
const registerEventListeners = () => {
    let root = document.getElementById('amosfilter');
    let fcmp = document.getElementById('amosfilter_fcmp');
    let componentSearch = document.getElementById('amosfilter_fcmp_search');
    let flng = document.getElementById('amosfilter_flng');
    let languageSearch = document.getElementById('amosfilter_flng_search');
    let fver = document.getElementById('amosfilter_fver');
    let flast = document.getElementById('amosfilter_flast');

    // Click event delegation.
    root.addEventListener('click', e => {
        if (!e.target.hasAttribute('data-action')) {
            return;
        }

        let action = e.target.getAttribute('data-action');
        let region = e.target.closest('[data-region]').getAttribute('data-region');

        if (region == 'amosfilter_fcmp') {
            handleComponentSelectorAction(e, fcmp, action);
        }

        if (region == 'amosfilter_buttons' && action == 'togglemoreoptions') {
            toggleMoreOptions(e, root);
        }
    });

    // Input change event delegation.
    root.addEventListener('change', e => {
        if (e.target.id.startsWith('amosfilter_fcmp_')) {
            updateCounterOfSelectedComponents();
        }

        if (e.target.id == 'amosfilter_flng') {
            updateCounterOfSelectedLanguages();
        }

        if (e.target.id == 'amosfilter_flast') {
            if (flast.checked) {
                fver.setAttribute('disabled', 'disabled');
            } else {
                fver.removeAttribute('disabled');
            }
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

    let selectorComponentItem = `:scope [data-region="amosfilter_fcmp_item"]:not(.hidden) input[name="fcmp[]"]`;

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
    let fcmp = document.getElementById('amosfilter_fcmp');
    let counter = document.getElementById('amosfilter_fcmp_counter');
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
    let flng = document.getElementById('amosfilter_flng');
    let counter = document.getElementById('amosfilter_flng_counter');
    let count = flng.querySelectorAll(':scope option:checked').length;

    if (count == 0) {
        counter.classList.add('badge-danger');
    } else {
        counter.classList.remove('badge-danger');
    }

    counter.textContent = count;
};

/**
 * @function toggleMoreOptions
 * @param {Event} e
 * @param {Element} root
 */
const toggleMoreOptions = async(e, root) => {
    e.preventDefault();

    if (root.getAttribute('data-level-showadvanced') == '0') {
        root.setAttribute('data-level-showadvanced', 1);
        e.target.innerText = await getString('lessfilteringoptions', 'local_amos');

    } else {
        root.setAttribute('data-level-showadvanced', 0);
        e.target.innerText = await getString('morefilteringoptions', 'local_amos');
        showUsedAdvancedOptions();
    }
};

/**
 * Make advanced options that have been set, visible even in basic mode.
 *
 * @function showUsedAdvancedOptions
 */
const showUsedAdvancedOptions = () => {
    let root = document.getElementById('amosfilter');

    root.querySelectorAll(':scope [data-level="advanced"][data-level-control]').forEach(item => {
        let control = document.getElementById(item.getAttribute('data-level-control'));
        let forceshow = false;

        if (control.tagName.toLowerCase() == 'input'
                && control.getAttribute('type') == 'checkbox'
                && control.checked) {
            forceshow = true;
        }

        if (control.tagName.toLowerCase() == 'input'
                && control.getAttribute('type') == 'text'
                && control.value !== '') {
            forceshow = true;
        }

        if (control.tagName.toLowerCase() == 'select'
                && control.hasAttribute('data-default')
                && !control.hasAttribute('disabled')) {
            let selected = control.querySelectorAll(':scope option:checked');

            if (selected.length != 1) {
                forceshow = true;

            } else {
                selected.forEach(option => {
                    if (option.value !== control.getAttribute('data-default')) {
                        forceshow = true;
                    }
                });
            }
        }

        if (forceshow) {
            item.setAttribute('data-level-forceshow', 1);
        } else {
            item.removeAttribute('data-level-forceshow');
        }
    });

    root.querySelectorAll(':scope [data-level="advanced"][data-level-control]').forEach(item => {
        let control = document.getElementById(item.getAttribute('data-level-control'));

        if (control.tagName.toLowerCase() == 'fieldset') {
            if (control.querySelector('[data-level-forceshow]')) {
                item.setAttribute('data-level-forceshow', 1);
            } else {
                item.removeAttribute('data-level-forceshow');
            }
        }
    });
};

/**
 * @function scrollToFirstSelectedComponent
 */
const scrollToFirstSelectedComponent = () => {
    let comp = document.getElementById('amosfilter_fcmp').querySelector('input[id^="amosfilter_fcmp_f_"]:checked');

    if (comp) {
        comp.scrollIntoView(false);
    }
};
