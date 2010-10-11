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
 * AMOS log
 *
 * @todo      use the same filter as translator has to allow fine grained log output
 * @package   local-amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot . '/local/amos/locallib.php');
require_once($CFG->dirroot . '/local/amos/log_form.php');

require_login(SITEID);

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/amos/log.php');
$PAGE->set_title('AMOS Log');
$PAGE->set_heading('AMOS Log');

$output = $PAGE->get_renderer('local_amos');

$filterform = new local_amos_log_form();
$filter = array();
if ($formdata = $filterform->get_data()) {
    $filter = (array)$formdata;
    if (empty($formdata->langenabled)) {
        unset($filter['lang']);
    }
    if (empty($formdata->componentenabled)) {
        unset($filter['component']);
    }
}
$records = new local_amos_log($filter);

/// Output starts here
echo $output->header();
$filterform->display();
echo $output->render($records);
echo $output->footer();
