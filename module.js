// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Javascript functions for AMOS
 *
 * @package   local-amos
 * @copyright 2010 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @namespace Local amos namespace
 */
M.local_amos = {

    /**
     * Initialize JS support for the main translation page, called from view.php
     *
     * @param {Object} Y YUI instance
     */
    init_translator:function(Y) {
        filter = Y.one('#amosfilter');

        // add select All / None links to the components field
        filter.one('#amosfilter_fcmp_actions').set('innerHTML',
            '<a href="#" id="amosfilter_fcmp_actions_all">All</a> / <a href="#" id="amosfilter_fcmp_actions_none">None</a>');
        filter.one('#amosfilter_fcmp_actions_all').on('click', function(e) {
            filter.one('select#amosfilter_fcmp').get('options').set('selected', true);
        });
        filter.one('#amosfilter_fcmp_actions_none').on('click', function(e) {
            filter.one('select#amosfilter_fcmp').get('options').set('selected', false);
        });

        // add select All / None links to the languages field
        filter.one('#amosfilter_flng_actions').set('innerHTML',
            '<a href="#" id="amosfilter_flng_actions_all">All</a> / <a href="#" id="amosfilter_flng_actions_none">None</a>');
        filter.one('#amosfilter_flng_actions_all').on('click', function(e) {
            filter.one('select#amosfilter_flng').get('options').set('selected', true);
        });
        filter.one('#amosfilter_flng_actions_none').on('click', function(e) {
            filter.one('select#amosfilter_flng').get('options').set('selected', false);
        });
    }
}
