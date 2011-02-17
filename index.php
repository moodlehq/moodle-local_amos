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
 * AMOS home page
 *
 * @package   local-amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');

require_login(SITEID, false);

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/amos/index.php');
$PAGE->set_title('AMOS Home page');
$PAGE->set_heading('AMOS Home page');

$output = $PAGE->get_renderer('local_amos');

/// Output starts here
echo $output->header();
echo $output->heading(get_string('amos', 'local_amos'), 1);
echo $output->container(get_string('about', 'local_amos'));

echo $output->heading(get_string('privileges', 'local_amos'));
$caps = array();
if (has_capability('local/amos:manage', get_system_context())) {
    $caps[] = get_string('amos:manage', 'local_amos');
}
if (has_capability('local/amos:stage', get_system_context())) {
    $caps[] = get_string('amos:stage', 'local_amos');
}
if (has_capability('local/amos:commit', get_system_context())) {
    $allowed = mlang_tools::list_allowed_languages($USER->id);
    if (empty($allowed)) {
        $allowed = get_string('committablenone', 'local_amos');
    } elseif (!empty($allowed['X'])) {
        $allowed = get_string('committableall', 'local_amos');
    } else {
        $allowed = implode(', ', $allowed);
    }
    $caps[] = get_string('amos:commit', 'local_amos') . ' (' . $allowed . ')';
}
if (empty($caps)) {
    get_string('privilegesnone', 'local_amos');
} else {
    $caps = '<li>' . implode("</li>\n<li>", $caps) . '</li>';
    echo html_writer::tag('ul', $caps);
}

echo $output->footer();
