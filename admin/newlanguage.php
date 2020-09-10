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
 * Register new language
 *
 * @package   local-amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once(dirname(dirname(__FILE__)).'/mlanglib.php');
require_once(dirname(__FILE__).'/newlanguage_form.php');

require_login(SITEID, false);
require_capability('local/amos:manage', context_system::instance());

$cache = cache::make('local_amos', 'listlanguages');
$cache->delete('langs');

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/amos/admin/newlanguage.php');
$PAGE->set_title('AMOS ' . get_string('newlanguage', 'local_amos'));
$PAGE->set_heading('AMOS ' . get_string('newlanguage', 'local_amos'));

$form = new local_amos_newlanguage_form();

if ($data = $form->get_data()) {
    $component = new mlang_component('langconfig', $data->langcode, mlang_version::by_code(mlang_version::MOODLE_40));
    $data->langname = mlang_string::fix_syntax($data->langname);
    $data->langnameint = mlang_string::fix_syntax($data->langnameint);
    $component->add_string(new mlang_string('thislanguage', $data->langname));
    $component->add_string(new mlang_string('thislanguageint', $data->langnameint));
    $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
    $stage->add($component);
    $stage->store();
    redirect(new moodle_url('/local/amos/stage.php'));
}

/// Output starts here
echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
