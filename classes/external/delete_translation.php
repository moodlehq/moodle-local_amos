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

namespace local_amos\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');

/**
 * Permanently delete a single translation record from the AMOS history.
 *
 * @package     local_amos
 * @category    external
 * @copyright   2026 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_translation extends \core_external\external_api {

    /**
     * Describes the external function parameters.
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {

        return new \core_external\external_function_parameters([
            'translationid' => new \core_external\external_value(PARAM_INT, 'ID of the amos_translations record to delete'),
        ]);
    }

    /**
     * Executes the external function.
     *
     * @param int $translationid
     * @return array
     */
    public static function execute(int $translationid): array {
        global $DB;

        /** @var \context $context */
        $context = \core\context\system::instance();
        self::validate_context($context);
        require_capability('local/amos:manage', $context);

        [
            'translationid' => $translationid,
        ] = self::validate_parameters(self::execute_parameters(), compact(
            'translationid'
        ));

        $record = $DB->get_record('amos_translations', ['id' => $translationid], '*', MUST_EXIST);
        $DB->delete_records('amos_translations', ['id' => $translationid]);

        self::schedule_zip_rebuild($record->lang, $record->since);

        return [
            'translationid' => $translationid,
        ];
    }

    /**
     * Describes the external function result value.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {

        return new \core_external\external_single_structure([
            'translationid' => new \core_external\external_value(PARAM_INT, 'ID of the deleted amos_translations record'),
        ]);
    }

    /**
     * Remembers that the given language pack needs its ZIP rebuilt on the next export run
     *
     * Permanently deleting a translation record bypasses the normal commit mechanism that the
     * export-zip CLI job relies on to detect recently modified languages. This records the
     * affected language and version in the plugin config so that the next run of the export-zip
     * job can pick it up and rebuild the affected ZIP package.
     *
     * @param string $lang the language code affected by the deletion
     * @param int $since the STABLE branch code the deleted record was valid since
     */
    protected static function schedule_zip_rebuild(string $lang, int $since) {

        if ($lang === 'en_fix') {
            // Local English fixes never trigger a language pack rebuild.
            return;
        }

        $pending = json_decode((string) get_config('local_amos', 'pendingzipsrebuild'), true);

        if (!is_array($pending)) {
            $pending = [];
        }

        if (!isset($pending[$lang]) || $since < $pending[$lang]) {
            $pending[$lang] = $since;
        }

        set_config('pendingzipsrebuild', json_encode($pending), 'local_amos');
    }
}
