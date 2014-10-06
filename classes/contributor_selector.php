<?php
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
 * @package     local_amos
 * @copyright   2014 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/user/selector/lib.php');

class local_amos_contributor_selector extends user_selector_base {

    /**
     * Constructor.
     *
     * @param string $name
     * @param array $options
     */
    public function __construct($name, $options = array()) {
        $options['accesscontext'] = context_system::instance();
        $options['multiselect'] = false;
        $options['extrafields'] = array('email');
        parent::__construct($name, $options);
    }

    public function find_users($search) {
        global $DB;

        list($searchsql, $searchparams) = $this->search_sql($search, 'u');
        list($sortsql, $sortparams) = users_order_by_sql('u', $search, context_system::instance());

        $fields = "SELECT ".$this->required_fields_sql('u');
        $countfields = "SELECT COUNT(*)";

        $sql = " FROM {user} u
                WHERE ${searchsql}";

        $order = " ORDER BY ${sortsql}";

        if (!$this->is_validating()) {
            $foundcount = $DB->count_records_sql($countfields.$sql, array_merge($searchparams));
            if ($foundcount > $this->maxusersperpage) {
                return $this->too_many_results($search, $foundcount);
            }
        }

        $found = $DB->get_records_sql($fields.$sql.$order, array_merge($searchparams, $sortparams));

        if (empty($found)) {
            return array();
        }

        if ($search) {
            $groupname = get_string('potusersmatching', 'core_role', $search);
        } else {
            $groupname = get_string('potusers', 'core_role');
        }

        return array($groupname => $found);
    }
}
