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
 * Provides the {@link \local_amos\external\plugin_translation_stats} trait.
 *
 * @package   local_amos
 * @category  external
 * @copyright 2019 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_amos\external;

defined('MOODLE_INTERNAL') || die();

/**
 * Trait implementing the plugin_translation_stats external function.
 */
trait plugin_translation_stats {

    /**
     * Describes parameters of the {@link plugin_translation_stats()} method
     */
    public static function plugin_translation_stats_parameters() {
        return new \external_function_parameters([
            'component' => new \external_value(PARAM_COMPONENT, 'Name of the component to obtain stats for'),
        ]);
    }

    /**
     * Returns stats about the given plugin / component translations.
     *
     * @param string $component
     * @return stdClass
     */
    public static function plugin_translation_stats($component) {

        // Validate parameters.
        $params = self::validate_parameters(self::plugin_translation_stats_parameters(), ['component' => $component]);
        $component = $params['component'];

        // Validate the context.
        $context = \context_system::instance();
        self::validate_context($context);

        $statsman = new \local_amos_stats_manager();

        $data = $statsman->get_component_stats($component);

        if ($data === false) {
            throw new \invalid_parameter_exception('Stats requested for an unknown component.');
        }

        return $data;
    }

    /**
     * Describes the return value of the {@link plugin_translation_stats()} method.
     *
     * @return external_description
     */
    public static function plugin_translation_stats_returns() {
        return new \external_single_structure([
            'lastmodified' => new \external_value(PARAM_INT, 'Timestamp of when the data was last modified'),
            'langnames' => new \external_multiple_structure(
                new \external_single_structure([
                    'lang' => new \external_value(PARAM_SAFEDIR, 'Language code'),
                    'name' => new \external_value(PARAM_TEXT, 'International name of the language followed by its code')
                ])
            ),
            'branches' => new \external_multiple_structure(
                new \external_single_structure([
                    'branch' => new \external_value(PARAM_FILE, 'Moodle branch (eg. 3.6)'),
                    'languages' => new \external_multiple_structure(
                        new \external_single_structure([
                            'lang' => new \external_value(PARAM_SAFEDIR, 'Language code'),
                            'numofstrings' => new \external_value(PARAM_INT, 'Number of strings in the language pack'),
                            'ratio' => new \external_value(PARAM_INT, 'Completeness of the translation'),
                        ])
                    ),
                ])
            )
        ]);
    }
}
