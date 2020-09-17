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
 * View staged strings and allow the user to commit them
 *
 * @package   local-amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/mlanglib.php');
require_once(dirname(__FILE__).'/importfile_form.php');
require_once(dirname(__FILE__).'/merge_form.php');
require_once(dirname(__FILE__).'/diff_form.php');
require_once(dirname(__FILE__).'/execute_form.php');

$message    = optional_param('message', null, PARAM_RAW); // commit message
$keepstaged = optional_param('keepstaged', false, PARAM_BOOL); // keep staged after commit
$unstage    = optional_param('unstage', null, PARAM_STRINGID); // stringid to unstage - other param required if non empty
$prune      = optional_param('prune', null, PARAM_INT);
$rebase     = optional_param('rebase', null, PARAM_INT);
$submit     = optional_param('submit', null, PARAM_INT);
$unstageall = optional_param('unstageall', null, PARAM_INT);
$download   = optional_param('download', null, PARAM_INT);
$propagate  = optional_param('propagate', null, PARAM_INT);

require_login(SITEID, false);
require_capability('local/amos:stage', context_system::instance());

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/amos/stage.php');
$PAGE->set_title('AMOS ' . get_string('stage', 'local_amos'));
$PAGE->set_heading('AMOS ' . get_string('stage', 'local_amos'));
$PAGE->requires->strings_for_js(array('commitmessageempty', 'unstage', 'unstageconfirm', 'unstaging',
    'confirmaction', 'stagestringsnocommit', 'diffstringmode'), 'local_amos');
$PAGE->requires->yui_module('moodle-local_amos-stage', 'M.local_amos.init_stage');

if (!empty($message)) {
    require_sesskey();
    require_capability('local/amos:commit', context_system::instance());
    if (empty($keepstaged)) {
        $clear = true;
    } else {
        $clear = false;
    }
    $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
    $allowed = mlang_tools::list_allowed_languages($USER->id);
    $stage->prune($allowed);
    list($numstrings, $listlanguages, $listcomponents) = mlang_stage::analyze($stage);
    $stage->commit($message, array('source' => 'amos', 'userid' => $USER->id, 'userinfo' => fullname($USER) . ' <' . $USER->email . '>'),
        false, null, $clear);
    $stage->store();
    // Automatically backport translations to lower versions if they apply.
    $listlanguages = array_filter(explode('/', $listlanguages));
    $listcomponents = array_filter(explode('/', $listcomponents));
    foreach ($listcomponents as $componentname) {
        mlang_tools::backport_translations($componentname, $listlanguages);
    }
    if ($clear) {
        if (empty($SESSION->local_amos->stagedcontribution)) {
            $nexturl = $PAGE->url;
        } else {
            $nexturl = new moodle_url('/local/amos/contrib.php', array('id' => $SESSION->local_amos->stagedcontribution->id));
        }
        unset($SESSION->local_amos->presetcommitmessage);
        unset($SESSION->local_amos->stagedcontribution);
    } else {
        $nexturl = $PAGE->url;
    }
    redirect($nexturl);
}
if (!empty($unstage)) {
    require_sesskey();
    $name = required_param('component', PARAM_ALPHANUMEXT);
    $branch = required_param('branch', PARAM_INT);
    $lang = required_param('lang', PARAM_ALPHANUMEXT);
    $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
    $component = $stage->get_component($name, $lang, mlang_version::by_code($branch));
    if ($component) {
        $component->unlink_string($unstage);
    }
    $stage->store();
    redirect($PAGE->url);
}
if (!empty($prune)) {
    require_sesskey();
    $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
    $allowed = mlang_tools::list_allowed_languages($USER->id);
    $stage->prune($allowed);
    $stage->store();
    redirect($PAGE->url);
}
if (!empty($rebase)) {
    require_sesskey();
    $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
    $stage->rebase();
    $stage->store();
    redirect($PAGE->url);
}
if (!empty($unstageall)) {
    require_sesskey();
    $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
    $stage->clear();
    $stage->store();
    unset($SESSION->local_amos->presetcommitmessage);
    unset($SESSION->local_amos->stagedcontribution);
    redirect($PAGE->url);
}
if (!empty($download)) {
    require_sesskey();
    $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
    $stage->send_zip("stage.zip");
}
if (!empty($submit)) {
    require_sesskey();
    $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
    $stash = mlang_stash::instance_from_stage($stage, $stage->userid);
    $stash->push();
    redirect(new moodle_url('/local/amos/stash.php', array('sesskey' => sesskey(), 'submit' => $stash->id)));
}
if (!empty($propagate)) {
    require_sesskey();
    $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
    $ver = optional_param_array('ver', array(), PARAM_INT);
    $num = null;
    if (!empty($ver) and is_array($ver)) {
        $versions = array();
        foreach ($ver as $versioncode) {
            $versions[] = mlang_version::by_code($versioncode);
        }
        if ($versions) {
            $num = $stage->propagate($versions);
            $stage->store();
        }
    }
    if (is_null($num)) {
        redirect($PAGE->url);
    } else {
        redirect(new moodle_url($PAGE->url, array('justpropagated' => $num)));
    }
}

$output = $PAGE->get_renderer('local_amos');

// make sure that USER contains sesskey property
$sesskey = sesskey();
// create a renderable object that represents the stage
$stage = new local_amos_stage($USER);
if (empty($stage->strings)) {
    unset($SESSION->local_amos->presetcommitmessage);
    unset($SESSION->local_amos->stagedcontribution);
} else {
    if (!empty($SESSION->local_amos->presetcommitmessage)) {
        $stage->presetmessage = $SESSION->local_amos->presetcommitmessage;
    }
    if (!empty($SESSION->local_amos->stagedcontribution)) {
        $stage->stagedcontribution = $SESSION->local_amos->stagedcontribution;
    }
}

/// Output starts here
echo $output->header();
echo $output->render($stage);
echo $output->footer();
