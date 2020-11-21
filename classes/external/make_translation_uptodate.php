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
 * Confirm the current possibly outdated translation as up to date.
 *
 * @package     local_amos
 * @category    external
 * @copyright   2020 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class make_translation_uptodate extends \external_api {

    /**
     * Describes the external function parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {

        return new \external_function_parameters([
            'originalid' => new \external_value(PARAM_INT, 'ID of the English original used as reference'),
            'translationid' => new \external_value(PARAM_INT, 'ID of the translation to be re-committed as up-to-date'),
        ]);
    }

    /**
     * Executes the external function.
     *
     * @param int $originalid
     * @param int $translationid
     * @return array
     */
    public static function execute(int $originalid, int $translationid): array {
        global $DB, $USER;

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/amos:commit', $context);

        [
            'translationid' => $translationid,
            'originalid' => $originalid,
        ] = self::validate_parameters(self::execute_parameters(), compact(
            'translationid',
            'originalid'
        ));

        $original = $DB->get_record('amos_strings', ['id' => $originalid], '*', MUST_EXIST);
        $translation = $DB->get_record('amos_translations', ['id' => $translationid], '*', MUST_EXIST);

        if ($original->component !== $translation->component || $original->strname !== $translation->strname) {
            throw new \invalid_parameter_exception('Invalid parameters: non-matching original and translation');
        }

        if ($original->since < $translation->since) {
            throw new \invalid_parameter_exception('Invalid parameters: original version lower than translation version');
        }

        if ($original->since == $translation->since && $original->timemodified < $translation->timemodified) {
            throw new \invalid_parameter_exception('Invalid parameters: original timemodified lower than translation timemodified');
        }

        $allowedlangs = \mlang_tools::list_allowed_languages($USER->id);

        if (!isset($allowedlangs['X']) && !isset($allowedlangs[$translation->lang])) {
            throw new \invalid_parameter_exception('Invalid parameters: not a maintainer of ' . $translation->lang);
        }

        $version = \mlang_version::by_code($original->since);

        $component = new \mlang_component($translation->component, $translation->lang, $version);
        $component->add_string(new \mlang_string($translation->strname, $translation->strtext));

        $stage = new \mlang_stage();
        $stage->add($component);

        $commitid = $stage->commit(get_string('markuptodate', 'local_amos'), [
            'source' => 'uptodate',
            'userid' => $USER->id,
            'userinfo' => fullname($USER) . ' <' . $USER->email . '>',
        ], true);

        $changes = $DB->get_records('amos_translations', ['commitid' => $commitid], '', 'id');

        if (count($changes) != 1) {
            throw new \coding_exception('Unexpected number of string changes found for commit id ' . $commitid);
        }

        $translation = reset($changes);

        return [
            'translationid' => $translation->id,
            'displaytranslationsince' => $version->label . '+',
        ];
    }

    /**
     * Describes the external function result value.
     *
     * @return \external_description
     */
    public static function execute_returns(): \external_description {

        return new \external_single_structure([
            'translationid' => new \external_value(PARAM_INT, 'ID of the new translation'),
            'displaytranslationsince' => new \external_value(PARAM_RAW, 'Label indicating version since the translation applies'),
        ]);
    }
}
