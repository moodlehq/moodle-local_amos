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
 * External function to add a string translation into a persistent AMOS stage.
 *
 * @package     local_amos
 * @category    external
 * @copyright   2020 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stage_translated_string extends \external_api {

    /**
     * Describes the external function parameters.
     *
     * @return \external_function_parameters
     */
    public static function execute_parameters(): \external_function_parameters {

        return new \external_function_parameters([
            'stageid' => new \external_value(PARAM_RAW, 'Identifier of persistent AMOS stage to add the translation to'),
            'originalid' => new \external_value(PARAM_INT, 'Identifier of English string used as a source for translation'),
            'lang' => new \external_value(PARAM_SAFEDIR, 'Code of the language'),
            'text' => new \external_value(PARAM_RAW, 'Raw text of the translation'),
            'translationid' => new \external_value(PARAM_INT, 'Identifier of translation being updated', VALUE_DEFAULT, null),
            'nocleaning' => new \external_value(PARAM_BOOL, 'Skip implicit cleaning of the input string', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Executes the external function.
     */
    public static function execute(string $stageid, int $originalid, string $lang, string $text,
            ?int $translationid = null, ?bool $nocleaning = false): array {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/local/amos/mlanglib.php');

        ['stageid' => $stageid, 'originalid' => $originalid, 'lang' => $lang, 'text' => $text, 'translationid' => $translationid,
            'nocleaning' => $nocleaning ] = self::validate_parameters(self::execute_parameters(), compact('stageid', 'originalid',
            'lang', 'text', 'translationid', 'nocleaning'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/amos:stage', $context);

        $english = $DB->get_record('amos_strings', ['id' => $originalid], 'id, strname, component, since', MUST_EXIST);

        if (empty($translationid)) {
            $since = $english->since;

        } else {
            $current = $DB->get_record('amos_translations', [
                'id' => $translationid,
                'component' => $english->component,
                'strname' => $english->strname,
            ], 'id, since', MUST_EXIST);

            if ($english->since > $current->since) {
                $since = $english->since;

            } else {
                $since = $current->since;
            }
        }

        $version = \mlang_version::by_code($since);

        if (!$version->translatable) {
            throw new \invalid_parameter_exception('Target version not translatable');
        }

        $string = new \mlang_string($english->strname, $text);
        $string->nocleaning = $nocleaning;
        $string->clean_text();

        $component = new \mlang_component($english->component, $lang, $version);
        $component->add_string($string);

        $stage = \mlang_persistent_stage::instance_for_user($USER->id, $stageid);
        $stage->add($component, true);
        $stage->store();
        \mlang_stash::autosave($stage);

        $response = [
            'translation' => $text,
            'displaytranslation' => \local_amos\local\util::add_breaks(s($string->text)),
            'displaytranslationsince' => $version->label . '+',
            'nocleaning' => $string->nocleaning,
            'warnings' => [],
        ];

        return $response;
    }

    /**
     * Describes the external function result value.
     *
     * @return \external_description
     */
    public static function execute_returns(): \external_description {

        return new \external_single_structure([
            'translation' => new \external_value(PARAM_RAW, 'Raw text of the staged translation'),
            'displaytranslation' => new \external_value(PARAM_RAW, 'HTML text of the staged translation to display'),
            'displaytranslationsince' => new \external_value(PARAM_RAW, 'Label indicating version since the translation applies'),
            'nocleaning' => new \external_value(PARAM_BOOL, 'Whether the implicit cleaning was skipped'),
            'warnings' => new \external_warnings(),
        ]);
    }
}
