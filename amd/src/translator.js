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
 * JS module for the AMOS translator.
 *
 * @module      local_amos/translator
 * @package     local_amos
 * @copyright   2020 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @function init
 */
export const init = () => {
    registerEventListeners();
};

/**
 * @function registerEventListeners
 */
const registerEventListeners = () => {
    let root = document.getElementById('amostranslator');

    root.addEventListener('click', e => {
        if (e.target.classList.contains('amostranslation') || e.target.classList.contains('amostranslationview')) {
            let item = e.target.closest('[data-region="amostranslatoritem"]');

            if (item.getAttribute('data-mode') == 'view') {
                translatorItemEditingOn(item, e.ctrlKey);
            }
        }
    });

    root.addEventListener('blur', e => {
        if (e.target.hasAttribute('data-region') && e.target.getAttribute('data-region') == 'amoseditor') {
            let item = e.target.closest('[data-region="amostranslatoritem"]');
            translatorItemEditingOff(item);
        }
    }, true);
};

/**
 * @function translatorItemEditingOn
 * @param {Element} item
 * @param {bool} [nocleaning=false] - turn editing on with nocleaning enabled
 */
const translatorItemEditingOn = (item, nocleaning = false) => {
    let textarea = item.querySelector('[data-region="amoseditor"]');
    let refHeight = item.querySelector('.amostranslation').clientHeight;

    textarea.setAttribute('data-previous', textarea.value);
    textarea.setAttribute('data-nocleaning', nocleaning ? 'nocleaning' : '');

    if (refHeight > 40) {
        textarea.style.height = (refHeight - 9) + 'px';
    }

    item.setAttribute('data-mode', 'edit');
    textarea.focus();
};

/**
 * @function translatorItemEditingOff
 * @param {Element} item
 */
const translatorItemEditingOff = (item) => {
    let textarea = item.querySelector('[data-region="amoseditor"]');
    let previoustext = textarea.getAttribute('data-previous');
    let nocleaning = textarea.getAttribute('data-nocleaning');
    let newtext = textarea.value;

    if (nocleaning !== 'nocleaning') {
        newtext = newtext.trim();
    }

    if (previoustext === newtext) {
        // The following line is intentionally here to remove added trailing/heading whitespace.
        textarea.value = previoustext;
        item.setAttribute('data-mode', 'view');

    } else {
        textarea.disabled = true;
    }
};

