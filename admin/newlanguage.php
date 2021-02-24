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
 * Register new language
 *
 * @package     local_amos
 * @copyright   2010 David Mudrak <david.mudrak@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->dirroot . '/local/amos/admin/newlanguage_form.php');

require_login(SITEID, false);
require_capability('local/amos:manage', context_system::instance());

$cache = cache::make('local_amos', 'lists');
$cache->delete('languages');

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/amos/admin/newlanguage.php');
$PAGE->set_title('AMOS ' . get_string('newlanguage', 'local_amos'));
$PAGE->set_heading('AMOS ' . get_string('newlanguage', 'local_amos'));

$form = new local_amos_newlanguage_form();

if ($data = $form->get_data()) {
    $component = new mlang_component('langconfig', $data->langcode, mlang_version::oldest_version());
    $data->langname = mlang_string::fix_syntax($data->langname);
    $data->langnameint = mlang_string::fix_syntax($data->langnameint);
    $component->add_string(new mlang_string('thislanguage', $data->langname));
    $component->add_string(new mlang_string('thislanguageint', $data->langnameint));
    $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
    $stage->add($component);
    $stage->store();
    redirect(new moodle_url('/local/amos/stage.php'));
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
