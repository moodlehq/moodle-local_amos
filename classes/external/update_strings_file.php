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
 * Provides class {@see \local_amos\external\update_strings_file}.
 *
 * @package     local_amos
 * @category    external
 * @copyright   2012 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_amos\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/externallib.php');

/**
 * Implements external function update_strings_file used e.g. by plugins directory to update plugin strings.
 *
 * @package     local_amos
 * @category    external
 * @copyright   2012, 2019, 2020 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_strings_file extends \external_api {

    /**
     * Describes parameters of the {@see execute()} method
     */
    public static function execute_parameters() {

        return new \external_function_parameters(
            [
                'userinfo' => new \external_value(PARAM_RAW, 'Name and email of the author to record into AMOS commit'),
                'message' => new \external_value(PARAM_RAW, 'Message describing the change to be committed into AMOS repository'),
                'components' => new \external_multiple_structure(
                    new \external_single_structure(
                        [
                            'componentname' => new \external_value(PARAM_COMPONENT, 'The full component name (eg. mod_stampcoll)'),
                            'moodlebranch' => new \external_value(PARAM_FILE, 'The Moodle branch for this component (eg. 2.3)'),
                            'language' => new \external_value(PARAM_SAFEDIR, 'The code of the language (eg. en)'),
                            'stringfilename' => new \external_value(PARAM_FILE, 'The name of the strings file (eg. stampcoll.php)'),
                            'stringfilecontent' => new \external_value(PARAM_RAW, 'The content of the strings file.'),
                            'version' => new \external_value(PARAM_RAW, 'The version of the component.', VALUE_OPTIONAL),
                        ]
                    )
                )
            ]
        );
    }

    /**
     * Imports component strings from a PHP strings file
     *
     * @param string $userinfo Name and email of the author to record into AMOS commit
     * @param string $message Message describing the change to be committed into AMOS repository
     * @param array $components of objects
     */
    public static function execute($userinfo, $message, $components) {
        global $CFG;

        require_once($CFG->dirroot.'/local/amos/locallib.php');
        require_once($CFG->dirroot.'/local/amos/mlanglib.php');
        require_once($CFG->dirroot.'/local/amos/mlangparser.php');

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(),
                ['userinfo' => $userinfo, 'message' => $message, 'components' => $components]);

        $userinfo = $params['userinfo'];
        $message = $params['message'];
        $components = $params['components'];

        // Validate the context.
        $context = \context_system::instance();
        self::validate_context($context);

        // Check capability.
        require_capability('local/amos:importstrings', $context);

        // Load all known standard plugins and core subsystems.
        $standardplugins = \local_amos\local\util::standard_components_tree();

        // Reorder components to process low versions first.
        usort($components, function($a, $b) {
            $aver = \mlang_version::by_dir($a['moodlebranch']);
            $bver = \mlang_version::by_dir($b['moodlebranch']);

            if ($aver->code == $bver->code) {
                return 0;

            } else if ($aver->code < $bver->code) {
                return -1;

            } else {
                return 1;
            }
        });

        // Results to be returned.
        $results = [];

        // List of processed components for later auto-merge.
        $componentnames = [];

        // List of new and changed workplace translations.
        $workplacestrings = [];

        // Iterate over all passed components and process them.
        foreach ($components as $component) {
            $stage = new \mlang_stage();

            if (substr($component['stringfilename'], -4) !== '.php') {
                throw new \invalid_parameter_exception('The strings file name does not have .php extension');
            }

            $componentname = \mlang_component::name_from_filename($component['stringfilename']);

            if (empty($component['language'])) {
                throw new \invalid_parameter_exception('Invalid language code');
            }
            $componentlanguage = $component['language'];

            $componentversion = \mlang_version::by_dir($component['moodlebranch']);
            if (is_null($componentversion) || $componentversion->code <= 19) {
                throw new \invalid_parameter_exception('Unsupported Moodle branch');
            }

            // If it is an activity module, make sure the name does not collide with a core subsystem.
            if (strpos($component['componentname'], 'mod_') === 0) {
                foreach ($standardplugins as $frankenstylenames) {
                    if (isset($frankenstylenames[$componentname])
                            && $frankenstylenames[$componentname] === 'core_'.$componentname) {
                        $exceptionmsg = 'The name of your activity module collides with a name of a core subsystem in Moodle';
                        throw new \invalid_parameter_exception($exceptionmsg);
                    }
                }
                unset($xstandardplugins);
            }

            // Okay, this looks promising. Let's start tracking the result.
            $result = [
                'componentname' => $componentname,
                'moodlebranch' => $componentversion->dir,
                'language' => $componentlanguage
            ];

            // Make sure we do not try to import English strings for a standard plugin.
            if ($componentlanguage === 'en') {
                if (isset($standardplugins[$componentversion->code][$componentname])) {
                    $result['status'] = 'error';
                    $result['message'] = 'Unable to import strings for a standard plugin';
                    $results[] = $result;
                    continue;
                }
            }

            // Create a new empty mlang_component instance.
            $mlangcomponent = new \mlang_component($componentname, $componentlanguage, $componentversion);

            // Parse the content of the PHP file and store all detected strings there.
            $parser = \mlang_parser_factory::get_parser('php');
            // The following will throw exception if the syntax is wrong.
            $parser->parse($component['stringfilecontent'], $mlangcomponent);

            // Gather some statistics about found strings.
            $tmpstage = new \mlang_stage();
            $tmpstage->add($mlangcomponent);
            list($numofstrings, $listlanguages, $listcomponents) = \mlang_stage::analyze($tmpstage);
            $result['found'] = $numofstrings;
            $tmpstage->rebase(null, true);
            list($numofstrings, $listlanguages, $listcomponents) = \mlang_stage::analyze($tmpstage);
            $result['changes'] = $numofstrings;

            // Save changed or new workplace string keys in a variable.
            if ($userinfo === 'Moodle Workplace <workplace@moodle.com>') {
                $message = 'Strings for '.$component->componentname.' '.$component->version;
            }

            // Clear $tmpstage.
            $tmpstage->clear();
            unset($tmpstage);
            $result['status'] = 'ok';

            // Stage the component.
            $stage->add($mlangcomponent);

            // Remember the names of components we processed.
            foreach ($stage as $processedcomponent) {
                $componentnames[$processedcomponent->name] = true;
            }

            // Rebase and mark missing strings as deleted.
            $stage->rebase(null, true);

            // The following will throw exception if the commit fails.
            $stage->commit($message, ['source' => 'import', 'userinfo' => $userinfo], true);

            $mlangcomponent->clear();
            unset($mlangcomponent);

            $results[] = $result;
        }

        // Auto-merge updated components.
        foreach (array_keys($componentnames) as $componentname) {
            \mlang_tools::backport_translations($componentname);
        }

        // Done! Thank you for calling this web service.
        return $results;
    }

    /**
     * Describes the return value of the {@see execute()} method
     *
     * @return \external_multiple_structure
     */
    public static function execute_returns() {

        return new \external_multiple_structure(
            new \external_single_structure(
                [
                    'componentname' => new \external_value(PARAM_COMPONENT, 'Component name as registered in AMOS (eg. stampcoll)'),
                    'moodlebranch' => new \external_value(PARAM_FILE, 'Moodle branch for this component (eg. 2.3)'),
                    'language' => new \external_value(PARAM_SAFEDIR, 'Language code (e.g. en)'),
                    'status' => new \external_value(PARAM_ALPHA, 'ok|error'),
                    'message' => new \external_value(PARAM_RAW, 'Additional status information', VALUE_OPTIONAL),
                    'found' => new \external_value(PARAM_INT, 'Number of detected strings in the given file', VALUE_OPTIONAL),
                    'changes' => new \external_value(PARAM_INT, 'Number of committed changes including removals', VALUE_OPTIONAL),
                ]
            )
        );
    }
}
