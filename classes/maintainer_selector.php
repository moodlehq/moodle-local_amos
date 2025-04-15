<?php
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
 * Provides {@see local_amos_maintainer_selector} class.
 *
 * @package     local_amos
 * @copyright   2014 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/user/selector/lib.php');

/**
 * Allows to select language pack maintainer.
 *
 * @package     local_amos
 * @copyright   2014 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_amos_maintainer_selector extends user_selector_base {

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

    /**
     * Find users based on search criteria
     *
     * @param string $search
     * @return array
     */
    public function find_users($search) {
        global $DB;

        $available = get_users_by_capability(context_system::instance(), 'local/amos:commit', 'u.id');

        list($permsql, $permparams) = $DB->get_in_or_equal(array_keys($available), SQL_PARAMS_NAMED, 'permparam');
        list($searchsql, $searchparams) = $this->search_sql($search, 'u');
        list($sortsql, $sortparams) = users_order_by_sql('u', $search, context_system::instance());

        $fields = "SELECT ".$this->required_fields_sql('u');
        $countfields = "SELECT COUNT(*)";

        $sql = " FROM {user} u
                WHERE u.id $permsql AND $searchsql";

        $order = " ORDER BY $sortsql";

        if (!$this->is_validating()) {
            $foundcount = $DB->count_records_sql($countfields.$sql, array_merge($permparams, $searchparams));
            if ($foundcount > $this->maxusersperpage) {
                return $this->too_many_results($search, $foundcount);
            }
        }

        $found = $DB->get_records_sql($fields.$sql.$order, array_merge($permparams, $searchparams, $sortparams));

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
