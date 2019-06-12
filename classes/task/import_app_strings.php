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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Provides the {@link \local_amos\task\import_app_strings} class.
 *
 * @package     local_amos
 * @category    task
 * @copyright   2019 Pau Ferrer Ocaña <pau@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_amos\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/amos/mlanglib.php');

/**
 * Imports the strings used in the Moodle apps.
 *
 * @copyright 2019 Pau Ferrer Ocaña <pau@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_app_strings extends \core\task\scheduled_task {

    /** @var \local_amos\local\git client to use */
    protected $git;

    /**
     * Return the task name.
     *
     * @return string
     */
    public function get_name() {
        return 'Import Moodle Apps strings';
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        $langindexfile = get_config('local_amos', 'applangindexfile');
        mtrace('Loading langindex from '.$langindexfile);
        $file = file_get_contents($langindexfile);
        $strings = json_decode($file, true);

        $records = array();
        foreach ($strings as $key => $value) {
            if ($value != 'local_moodlemobileapp') {
                $record = new \StdClass();
                $record->appid = $key;
                $exp = explode('/', $value, 2);
                $record->component = $exp[0];
                if (count($exp) == 2) {
                    $record->stringid = $exp[1];
                } else {
                    $exp = explode('.', $key, 3);

                    if (count($exp) == 3) {
                        $record->stringid = $exp[2];
                    } else {
                        $record->stringid = $exp[1];
                    }
                }
                $records[$key] = $record;
            }
        }

        if (count($records) > 0) {
            $DB->delete_records('amos_app_strings');
            $DB->insert_records('amos_app_strings',  $records);

            mtrace(count($records) .' app strings inserted');
        }
    }

}
