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
 * JS module for the AMOS stage.
 *
 * @module      local_amos/stage
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
    let root = document.getElementById('amosstagestrings');

    root.addEventListener('click', e => {
        if (e.target.getAttribute('data-action') == 'toggle-diffmode') {
            e.preventDefault();
            let item = e.target.closest('[data-region="amosstageitem"]');

            if (item.getAttribute('data-diffmode') == 'chunks') {
                item.setAttribute('data-diffmode', 'blocks');
            } else {
                item.setAttribute('data-diffmode', 'chunks');
            }
        }
    });
};
