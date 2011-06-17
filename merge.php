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
 * Merge all missing strings from branch to another and stage them
 *
 * @package    local
 * @subpackage amos
 * @copyright  2010 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/mlanglib.php');
require_once(dirname(__FILE__).'/merge_form.php');

require_login(SITEID, false);
require_capability('local/amos:commit', get_system_context()); // for langpack maintainers only

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/amos/merge.php');
navigation_node::override_active_url(new moodle_url('/local/amos/stage.php'));
$PAGE->set_title('AMOS ' . get_string('merge', 'local_amos'));
$PAGE->set_heading('AMOS ' . get_string('merge', 'local_amos'));

$mergeform = new local_amos_merge_form(null, local_amos_merge_options());

if ($data = $mergeform->get_data()) {
    $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());

    $sourceversion = mlang_version::by_code($data->sourceversion);
    $targetversion = mlang_version::by_code($data->targetversion);
    if (is_null($sourceversion) or is_null($targetversion)) {
        notice('Invalid version selected', new moodle_url('/local/amos/stage.php'));
    }

    $tree = mlang_tools::components_tree(array('branch' => $sourceversion->code, 'lang' => $data->language));
    $sourcecomponentnames = array_keys(reset(reset($tree)));
    unset($tree);

    foreach ($sourcecomponentnames as $sourcecomponentname) {
        // get a snapshot of both components and merge source into target
        $sourcecomponent = mlang_component::from_snapshot($sourcecomponentname, $data->language, $sourceversion);
        $targetcomponent = mlang_component::from_snapshot($sourcecomponent->name, $sourcecomponent->lang, $targetversion);
        mlang_tools::merge($sourcecomponent, $targetcomponent);
        $sourcecomponent->clear();
        // keep just strings that are defined in english
        $englishcomponent = mlang_component::from_snapshot($sourcecomponent->name, 'en', $targetversion);
        $targetcomponent->intersect($englishcomponent);
        $englishcomponent->clear();
        // stage the target
        $stage->add($targetcomponent);
        $targetcomponent->clear();
    }

    // prune the stage so that only committable strings are staged
    $allowed = mlang_tools::list_allowed_languages($USER->id);
    $stage->prune($allowed);
    // keep just really modified (that is new in this case) strings
    $stage->rebase();
    // and store the persistant stage
    $stage->store();

    // if no new strings are merged, inform the user
    if (!$stage->has_component()) {
        notice(get_string('nothingtomerge', 'local_amos'), new moodle_url('/local/amos/stage.php'));
    }

    if (!isset($SESSION->local_amos)) {
        $SESSION->local_amos = new stdClass();
    }
    $a          = new stdClass();
    $a->source  = $sourceversion->label;
    $a->target  = $targetversion->label;
    $SESSION->local_amos->presetcommitmessage = get_string('presetcommitmessage2', 'local_amos', $a);
}

redirect(new moodle_url('/local/amos/stage.php'));
