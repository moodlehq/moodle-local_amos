<?php

// This file is part of Moodle - http://moodle.org/
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
 * Executes the passed AMOScript and stages the result
 *
 * @package    local
 * @subpackage amos
 * @copyright  2011 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/mlanglib.php');
require_once(dirname(__FILE__).'/execute_form.php');

require_login(SITEID, false);
require_capability('local/amos:execute', context_system::instance());
require_capability('local/amos:stage', context_system::instance());

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/amos/execute.php');
navigation_node::override_active_url(new moodle_url('/local/amos/stage.php'));
$PAGE->set_title('AMOS ' . get_string('scriptexecute', 'local_amos'));
$PAGE->set_heading('AMOS ' . get_string('scriptexecute', 'local_amos'));

$executeform = new local_amos_execute_form(null, local_amos_execute_options());

if ($data = $executeform->get_data()) {
    $version = mlang_version::by_code($data->version);
    $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
    $instructions = mlang_tools::extract_script_from_text($data->script);
    if (!empty($instructions)) {
        foreach ($instructions as $instruction) {
            $changes = mlang_tools::execute($instruction, $version);
            if ($changes instanceof mlang_stage) {
                foreach ($changes->get_iterator() as $component) {
                    $stage->add($component, true);
                }
                $changes->clear();
            } elseif ($changes < 0) {
                throw new moodle_exception('error_during_amoscript_execution', 'local_amos', '', null, $changes);
            }
            unset($changes);
        }
    }
    $stage->rebase();
    $stage->store();

    if (!isset($SESSION->local_amos)) {
        $SESSION->local_amos = new stdClass();
    }
    $SESSION->local_amos->presetcommitmessage = $data->script;
}

if (!isset($stage) or !$stage->has_component()) {
    notice(get_string('nothingtostage', 'local_amos'), new moodle_url('/local/amos/stage.php'));
}

redirect(new moodle_url('/local/amos/stage.php'));
