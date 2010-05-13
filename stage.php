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

$message = optional_param('message', null, PARAM_RAW); // commit message
$unstage = optional_param('unstage', null, PARAM_STRINGID); // stringid to unstage - other param required if non empty
$prune   = optional_param('prune', null, PARAM_INT);
$rebase  = optional_param('rebase', null, PARAM_INT);

require_login(SITEID, false);
require_capability('local/amos:stage', get_system_context());

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/amos/stage.php');
$PAGE->set_title('AMOS stage');
$PAGE->set_heading('AMOS stage');
$PAGE->requires->js_init_call('M.local_amos.init_stage');

if (!empty($message)) {
    // committing the stage
    require_sesskey();
    require_capability('local/amos:commit', get_system_context());
    $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
    $allowed = mlang_tools::list_allowed_languages($USER->id);
    $stage->prune($allowed);
    $stage->commit($message, array('source' => 'amos', 'userinfo' => fullname($USER) . ' <' . $USER->email . '>'));
    $stage->store();
    redirect($PAGE->url);
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

$output = $PAGE->get_renderer('local_amos');

// make sure that USER contains sesskey property
$sesskey = sesskey();
// create a renderable object that represents the stage
$stage = new local_amos_stage($USER);

/// Output starts here
echo $output->header();
echo $output->render($stage);
echo $output->footer();
