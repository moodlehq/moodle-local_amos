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
 * Provides the {@link \local_amos\external\update_strings_file} trait.
 *
 * @package   local_amos
 * @category  external
 * @copyright 2012, 2019 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_amos\external;

defined('MOODLE_INTERNAL') || die();

/**
 * Trait implementing the update_strings_file external function.
 */
trait update_strings_file {

    /**
     * Describes parameters of the {@link update_strings_file()} method
     */
    public static function update_strings_file_parameters() {
        return new \external_function_parameters(
            array(
                'userinfo' => new \external_value(PARAM_RAW, 'Name and email of the author to record into AMOS commit'),
                'message' => new \external_value(PARAM_RAW, 'Message describing the change to be committed into AMOS repository'),
                'components' => new \external_multiple_structure(
                    new \external_single_structure(
                        array(
                            'componentname' => new \external_value(PARAM_COMPONENT, 'The full component name (eg. mod_stampcoll)'),
                            'moodlebranch' => new \external_value(PARAM_FILE, 'The Moodle branch for this component (eg. 2.3)'),
                            'language' => new \external_value(PARAM_SAFEDIR, 'The code of the language (eg. en)'),
                            'stringfilename' => new \external_value(PARAM_FILE, 'The name of the strings file (eg. stampcoll.php)'),
                            'stringfilecontent' => new \external_value(PARAM_RAW, 'The content of the strings file.'),
                        )
                    )
                )
            )
        );
    }

    /**
     * Imports component strings from a PHP strings file
     *
     * @param string $components
     */
    public static function update_strings_file($userinfo, $message, $components) {
        global $CFG;
        require_once($CFG->dirroot.'/local/amos/locallib.php');
        require_once($CFG->dirroot.'/local/amos/mlanglib.php');
        require_once($CFG->dirroot.'/local/amos/mlangparser.php');

        // Validate parameters.
        $params = self::validate_parameters(self::update_strings_file_parameters(),
                array('userinfo' => $userinfo, 'message' => $message, 'components' => $components));

        $userinfo = $params['userinfo'];
        $message = $params['message'];
        $components = $params['components'];

        // Validate the context.
        $context = \context_system::instance();
        self::validate_context($context);

        // Check capability.
        require_capability('local/amos:importstrings', $context);

        // Load all known standard plugins and core subsystems.
        $standardplugins = local_amos_standard_plugins();

        // Iterate over all passed components and process them.
        $results = array();
        $stage = new \mlang_stage();
        foreach ($components as $component) {
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
                foreach ($standardplugins as $moodleversion => $frankenstylenames) {
                    if (isset($frankenstylenames[$componentname])
                            && $frankenstylenames[$componentname] === 'core_'.$componentname) {
                        $exceptionmsg = 'The name of your activity module collides with a name of a core subsystem in Moodle';
                        throw new \invalid_parameter_exception($exceptionmsg);
                    }
                }
                unset($xstandardplugins);
            }

            // Okay, this looks promising. Let's start tracking the result.
            $result = array(
                'componentname' => $componentname,
                'moodlebranch' => $componentversion->dir,
                'language' => $componentlanguage
            );

            // Make sure we do not try to import English strings for a standard plugin.
            if ($componentlanguage === 'en') {
                if (isset($standardplugins[$componentversion->dir][$componentname])) {
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
            $tmpstage->clear();
            unset($tmpstage);
            $result['status'] = 'ok';

            // Stage the component.
            $stage->add($mlangcomponent);
            $mlangcomponent->clear();
            unset($mlangcomponent);

            $results[] = $result;
        }

        // Populate the list of staged components for later auto-merge.
        $componentnames = array();
        foreach ($stage->get_iterator() as $component) {
            $componentnames[] = $component->name;
        }

        // Rebase and eventually commit the stage with string modifications.
        $stage->rebase(null, true);
        // The following will throw exception if the commit fails.
        $stage->commit($message, array('source' => 'import', 'userinfo' => $userinfo), true);

        // Auto-merge updated components.
        foreach ($componentnames as $componentname) {
            \mlang_tools::auto_merge($componentname);
        }

        // Done! Thank you for calling this web service.
        return $results;
    }

    /**
     * Describes the return value of the {@link update_strings_file()} method
     *
     * @return external_description
     */
    public static function update_strings_file_returns() {
        return new \external_multiple_structure(
            new \external_single_structure(
                array(
                    'componentname' => new \external_value(PARAM_COMPONENT, 'Component name as registered in AMOS (eg. stampcoll)'),
                    'moodlebranch' => new \external_value(PARAM_FILE, 'Moodle branch for this component (eg. 2.3)'),
                    'language' => new \external_value(PARAM_SAFEDIR, 'Language code (e.g. en)'),
                    'status' => new \external_value(PARAM_ALPHA, 'ok|error'),
                    'message' => new \external_value(PARAM_RAW, 'Additional status information', VALUE_OPTIONAL),
                    'found' => new \external_value(PARAM_INT, 'Number of detected strings in the given file', VALUE_OPTIONAL),
                    'changes' => new \external_value(PARAM_INT, 'Number of committed changes including removals', VALUE_OPTIONAL),
                )
            )
        );
    }
}
