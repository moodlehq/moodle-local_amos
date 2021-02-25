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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * View staged strings and allow the user to commit them
 *
 * @package     local_amos
 * @copyright   2010 David Mudrak <david.mudrak@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/amos/locallib.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->dirroot . '/local/amos/importfile_form.php');
require_once($CFG->dirroot . '/local/amos/execute_form.php');

$message = optional_param('message', null, PARAM_RAW);
$keepstaged = optional_param('keepstaged', false, PARAM_BOOL);
$unstage = optional_param('unstage', null, PARAM_STRINGID);
$prune = optional_param('prune', null, PARAM_INT);
$rebase = optional_param('rebase', null, PARAM_INT);
$submit = optional_param('submit', null, PARAM_INT);
$unstageall = optional_param('unstageall', null, PARAM_INT);
$download = optional_param('download', null, PARAM_INT);

require_login(SITEID, false);
require_capability('local/amos:stage', context_system::instance());

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/amos/stage.php');
$PAGE->set_title('AMOS ' . get_string('stage', 'local_amos'));
$PAGE->set_heading('AMOS ' . get_string('stage', 'local_amos'));

if (!empty($message)) {
    require_sesskey();
    require_capability('local/amos:commit', context_system::instance());
    if (empty($keepstaged)) {
        $clear = true;
    } else {
        $clear = false;
    }
    $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
    $allowed = mlang_tools::list_allowed_languages();
    $stage->prune($allowed);
    list($numstrings, $listlanguages, $listcomponents) = mlang_stage::analyze($stage);
    $stage->commit($message, ['source' => 'amos', 'userid' => $USER->id, 'userinfo' => fullname($USER) . ' <' . $USER->email . '>'],
        false, null, $clear);

    // Invalidate the cache of all known languages.
    cache::make('local_amos', 'lists')->delete('languages');

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
    $name = required_param('component', PARAM_ALPHANUMEXT);
    $branch = required_param('branch', PARAM_INT);
    $lang = required_param('langcode', PARAM_ALPHANUMEXT);
    $confirm = optional_param('confirm', false, PARAM_BOOL);

    if (!$confirm) {
        $output = $PAGE->get_renderer('local_amos');
        echo $output->header();
        echo $output->heading(get_string('unstaging', 'local_amos'));

        $a = [
            'component' => $name,
            'language' => $lang,
            'stringid' => $unstage,
        ];

        echo $output->confirm(
            get_string('unstageconfirmlong', 'local_amos', $a),
            new moodle_url($PAGE->url, [
                'unstage' => $unstage,
                'sesskey' => sesskey(),
                'confirm' => true,
                'component' => $name,
                'branch' => $branch,
                'langcode' => $lang,
            ]),
            $PAGE->url
        );

        echo $output->footer();
        die();
    }

    require_sesskey();
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
    $allowed = mlang_tools::list_allowed_languages();
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

$output = $PAGE->get_renderer('local_amos');

// Make sure that USER contains sesskey property.
$sesskey = sesskey();

// Create a renderable object that represents the stage.
$stageui = new \local_amos\output\stage($USER);

if (empty($stageui->strings)) {
    unset($SESSION->local_amos->presetcommitmessage);
    unset($SESSION->local_amos->stagedcontribution);

} else {
    if (!empty($SESSION->local_amos->presetcommitmessage)) {
        $stageui->presetmessage = $SESSION->local_amos->presetcommitmessage;
    }

    if (!empty($SESSION->local_amos->stagedcontribution)) {
        $stageui->stagedcontribution = $SESSION->local_amos->stagedcontribution;
    }
}

echo $output->header();
echo $output->render($stageui);
echo $output->footer();
