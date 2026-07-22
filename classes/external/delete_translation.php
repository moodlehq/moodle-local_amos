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

        $DB->get_record('amos_translations', ['id' => $translationid], '*', MUST_EXIST);
        $DB->delete_records('amos_translations', ['id' => $translationid]);

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
}
