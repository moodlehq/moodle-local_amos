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

/**
 * Provide data for the AMOS translator based on the provided filter settings.
 *
 * @package     local_amos
 * @category    external
 * @copyright   2020 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_translator_data extends \external_api {

    /**
     * Describe the external function parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {

        return new \external_function_parameters([
            'filterquery' => new \external_value(PARAM_RAW, 'Query string representing the filter form data.'),
        ]);
    }

    /**
     * Execute the external function.
     *
     * @param string $filterquery
     * @return array
     */
    public static function execute(string $filterquery): array {
        global $USER, $PAGE;

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/amos:stage', $context);

        ['filterquery' => $filterquery] = self::validate_parameters(self::execute_parameters(), compact('filterquery'));

        // Parse the query string and inject it as if it was part of HTTP POST request which is what the filter expects.
        parse_str($filterquery, $_POST);

        $filter = new \local_amos\output\filter(new \moodle_url('/local/amos/view.php'));
        $filter->set_data_session_default();

        // Make sure that $USER contains the sesskey property.
        sesskey();
        $translator = new \local_amos\output\translator($filter, $USER);

        return [
            'json' => json_encode($translator->export_for_template($PAGE->get_renderer('local_amos'))),
        ];
    }

    /**
     * Describe the external function result value.
     *
     * @return \external_description
     */
    public static function execute_returns(): \external_description {

        return new \external_single_structure([
            'json' => new \external_value(PARAM_RAW, 'JSON encoded template data'),
        ]);
    }
}
