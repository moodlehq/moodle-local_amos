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

import {call as fetchMany} from 'core/ajax';
import Config from 'core/config';
import Notification from 'core/notification';

/**
 * @function init
 */
export const init = () => {
    registerEventListeners();
    turnAllMissingForEditing();
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
                translatorItemEditingOn(item, e.ctrlKey).focus();
            }
        }
    });

    root.addEventListener('blur', e => {
        if (e.target.hasAttribute('data-region') && e.target.getAttribute('data-region') == 'amoseditor') {
            translatorItemSave(e.target);
        }
    }, true);
};

/**
 * @function translatorItemEditingOn
 * @param {Element} item
 * @param {bool} [nocleaning=false] - turn editing on with nocleaning enabled
 * @return {Element}
 */
const translatorItemEditingOn = (item, nocleaning = false) => {
    let textarea = item.querySelector('[data-region="amoseditor"]');
    let refHeight = item.querySelector('.amostranslation').clientHeight;

    item.setAttribute('data-nocleaning', nocleaning ? '1' : '0');
    textarea.setAttribute('data-previous', textarea.value);

    if (refHeight > 40) {
        textarea.style.height = (refHeight - 9) + 'px';
    }

    item.setAttribute('data-mode', 'edit');

    return textarea;
};

/**
 * @function translatorItemSave
 * @param {Element} textarea
 * @return {Promise}
 */
const translatorItemSave = (textarea) => {
    let item = textarea.closest('[data-region="amostranslatoritem"]');
    let nocleaning = item.getAttribute('data-nocleaning');
    let previoustext = textarea.getAttribute('data-previous');
    let newtext = textarea.value;

    if (nocleaning === '1') {
        newtext = newtext.trim();
    }

    if (previoustext === newtext && nocleaning !== '1') {
        // Remove eventually added trailing/heading whitespace and just switch back.
        textarea.value = previoustext;
        item.setAttribute('data-mode', 'view');

        return Promise.resolve(false);

    } else {
        // Send the translation to the stage if the translation has changed or nocleaning applies.
        item.classList.add('staged');
        textarea.disabled = true;

        return stageTranslatedString(item, newtext)

        .then(response => {
            item.setAttribute('data-mode', 'view');
            textarea.disabled = false;
            textarea.removeAttribute('data-previous');
            textarea.innerHTML = textarea.value = response.translation;
            item.querySelector('[data-region="amostranslationview"]').innerHTML = response.displaytranslation;
            item.querySelector('[data-region="displaytranslationsince"]').innerHTML = response.displaytranslationsince + ' | ';

            window.console.log(response);

            return true;

        }).catch(Notification.exception);
    }
};

/**
 * @function stageTranslatedString
 * @param {Element} item
 * @param {string} text
 * @returns {Promise}
 */
const stageTranslatedString = (item, text) => {

    return fetchMany([{
        methodname: 'local_amos_stage_translated_string',
        args: {
            stageid: Config.sesskey,
            originalid: item.getAttribute('data-originalid'),
            lang: item.getAttribute('data-language'),
            text: text,
            translationid: parseInt(item.getAttribute('data-translationid')) || 0,
            nocleaning: item.getAttribute('data-nocleaning'),
        },
    }])[0];
};

/**
 * @function turnAllMissingForEditing
 */
const turnAllMissingForEditing = () => {
    let root = document.getElementById('amostranslator');
    let missingItems = root.querySelectorAll(':scope [data-region="amostranslatoritem"].missing');
    missingItems.forEach(translatorItemEditingOn);
};