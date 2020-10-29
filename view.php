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
 * Main AMOS translation page
 *
 * Displays strings filter and the translation table.
 *
 * @package   local-amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');

require_login(SITEID, false);
require_capability('local/amos:stage', context_system::instance());

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/amos/view.php');
$PAGE->set_title('AMOS ' . get_string('translatortool', 'local_amos'));
$PAGE->set_heading('AMOS ' . get_string('translatortool', 'local_amos'));

$output = $PAGE->get_renderer('local_amos');

$filter = new \local_amos\output\filter($PAGE->url);
$filter->set_data_default();

// Make sure that $USER contains the sesskey property.
sesskey();

$translator = new \local_amos\output\translator($filter, $USER);

echo $output->header();
echo $output->render($filter);

if (empty($translator->strings)) {
    if ($translator->currentpage > 1) {
        echo $output->heading(get_string('nostringsfoundonpage', 'local_amos', $translator->currentpage));
        echo html_writer::div(
            html_writer::link(new moodle_url($PAGE->url, ['fpg' => 1]), get_string('gotofirst', 'local_amos')) . ' | '.
            html_writer::link(new moodle_url($PAGE->url, ['fpg' => $translator->currentpage - 1]),
                get_string('gotoprevious', 'local_amos')),
            'text-center');

    } else {
        echo $output->heading(get_string('nostringsfound', 'local_amos'));
    }

    echo $output->footer();
    exit();
}

echo $output->render($translator);
echo $output->box('', 'googlebranding', 'googlebranding');
echo $output->footer();
