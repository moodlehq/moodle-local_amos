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
require_once($CFG->dirroot . '/local/amos/renderer.php');

/**
 * Provide data for the AMOS translator based on the provided filter settings.
 *
 * @package     local_amos
 * @category    external
 * @copyright   2020 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_string_timeline extends \external_api {

    /**
     * Describe the external function parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {

        return new \external_function_parameters([
            'component' => new \external_value(PARAM_ALPHANUMEXT, 'Component containing the string.'),
            'strname' => new \external_value(PARAM_STRINGID, 'String identifier'),
            'language' => new \external_value(PARAM_ALPHANUMEXT, 'Language code.'),
        ]);
    }

    /**
     * Execute the external function.
     *
     * @param string $filterquery
     * @return array
     */
    public static function execute(string $component, string $strname, string $language): array {
        global $DB;

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/amos:stage', $context);

        [
            'component' => $component,
            'strname' => $strname,
            'language' => $language,
        ] = self::validate_parameters(self::execute_parameters(), compact(
            'component',
            'strname',
            'language'
        ));

        $sql = "SELECT -s.id AS id, 'en' AS lang, s.strtext, s.since, s.timemodified,
                       c.userinfo, c.commitmsg, c.commithash, c.source
                  FROM {amos_strings} s
                  JOIN {amos_commits} c ON c.id = s.commitid
                 WHERE component = :component1
                   AND strname = :strname1

                 UNION

                SELECT s.id, s.lang, s.strtext, s.since, s.timemodified,
                       c.userinfo, c.commitmsg, c.commithash, c.source
                  FROM {amos_translations} s
                  JOIN {amos_commits} c ON c.id = s.commitid
                 WHERE component = :component2
                   AND lang = :lang
                   AND strname = :strname2

              ORDER BY since, timemodified, id";

        $params = [
            'component1' => $component,
            'component2' => $component,
            'lang' => $language,
            'strname1' => $strname,
            'strname2' => $strname,
        ];

        $records = $DB->get_records_sql($sql, $params);

        if (empty($records)) {
            throw new \invalid_parameter_exception('Invalid timeline parameters');
        }

        $results = [];
        $preven = '';
        $prevtr = '';

        foreach ($records as $record) {
            $encell = [];
            $langcell = [];

            if ($record->lang === 'en') {
                $cell = &$encell;
                $blank = &$langcell;
                $prev = $preven;

            } else {
                $cell = &$langcell;
                $blank = &$encell;
                $prev = $prevtr;
            }

            $blank['hascontent'] = false;

            $cell = [
                'hascontent' => true,
                'langcode' => $record->lang,
                'displaysince' => s(\mlang_version::by_code($record->since)->label . '+'),
                'displaydate' => s(\local_amos_renderer::commit_datetime($record->timemodified)),
                'userinfo' => $record->userinfo,
                'commitmsg' => $record->commitmsg,
                'hascommithash' => false,
                'commitsource' => $record->source,
            ];

            if ($record->strtext === null) {
                $displaytext = \html_writer::tag('del', s($prev));
            } else {
                $displaytext = s($record->strtext);
            }

            $cell['displaytext'] = \local_amos\local\util::add_breaks($displaytext);

            if ($record->commithash) {
                $cell['hascommithash'] = true;
                $cell['commithash'] = $record->commithash;

                if ($record->lang == 'en') {
                    $cell['commiturl'] = 'https://github.com/moodle/moodle/commit/' . $record->commithash;

                } else {
                    $cell['commiturl'] = 'https://github.com/mudrd8mz/moodle-lang/commit/' . $record->commithash;
                }
            }

            $results[] = [
                'english' => $encell,
                'translation' => $langcell,
            ];

            if ($record->lang == 'en') {
                $preven = $record->strtext;
            } else {
                $prevtr = $record->strtext;
            }
        }

        $results = array_reverse($results);

        return [
            'component' => $component,
            'strname' => $strname,
            'language' => $language,
            'changes' => $results,
        ];
    }

    /**
     * Describe the external function result value.
     *
     * Data are returned as list of tuples. Each tuple represents one row in the timeline table.
     * Each row has either left (first) or right (second) item with data.
     *
     * @return \external_description
     */
    public static function execute_returns(): \external_description {

        $info = new \external_single_structure([
            'hascontent' => new \external_value(PARAM_BOOL, 'Does this item have data to display.'),
            'langcode' => new \external_value(PARAM_ALPHANUMEXT, 'Language code', VALUE_OPTIONAL),
            'displaysince' => new \external_value(PARAM_RAW, 'Formatted version since this applies', VALUE_OPTIONAL),
            'displaydate' => new \external_value(PARAM_RAW, 'Formatted date and time of string change', VALUE_OPTIONAL),
            'userinfo' => new \external_value(PARAM_RAW, 'Author name and email', VALUE_OPTIONAL),
            'commitmsg' => new \external_value(PARAM_RAW, 'Commit message', VALUE_OPTIONAL),
            'hascommithash' => new \external_value(PARAM_BOOL, 'Is commit hash info present?', VALUE_OPTIONAL),
            'commithash' => new \external_value(PARAM_RAW, 'Commit hash', VALUE_OPTIONAL),
            'commiturl' => new \external_value(PARAM_URL, 'Commit URL', VALUE_OPTIONAL),
            'commitsource' => new \external_value(PARAM_RAW, 'Commit source', VALUE_OPTIONAL),
            'displaytext' => new \external_value(PARAM_RAW, 'Formatted string text content', VALUE_OPTIONAL),
        ]);

        return new \external_single_structure([
            'component' => new \external_value(PARAM_ALPHANUMEXT, 'Component containing the string.'),
            'strname' => new \external_value(PARAM_STRINGID, 'String identifier'),
            'language' => new \external_value(PARAM_ALPHANUMEXT, 'Language code.'),
            'changes' => new \external_multiple_structure(
                new \external_single_structure([
                    'english' => $info,
                    'translation' => $info,
                ])
            )
        ]);
    }
}
