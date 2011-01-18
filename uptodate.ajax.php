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
 * Mark a single string as up-to-date using AJAX
 *
 * @package   local_amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
//require_once($CFG->dirroot . '/local/amos/locallib.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');

require_login(SITEID, false);
if (!has_capability('local/amos:commit', get_system_context())) {
    header('HTTP/1.1 403 Forbidden');
    die();
}
if (!confirm_sesskey()) {
    header('HTTP/1.1 403 Forbidden');
    die();
}

$amosid = optional_param('amosid', null, PARAM_INT);

if (is_null($amosid)) {
    header('HTTP/1.1 400 Bad Request');
    die();
}

$allowedlangs = mlang_tools::list_allowed_languages($USER->id);
$allowed = false;
if (!empty($allowedlangs['X'])) {
    // can commit into all languages
    $allowed = true;
} else {
    $string = $DB->get_record('amos_repository', array('id' => $amosid), 'lang');
    $allowed = !empty($allowedlangs[$string->lang]);
}

if (!$allowed) {
    header('HTTP/1.1 403 Forbidden');
    die();
}

$timeupdated = time();
$DB->set_field('amos_repository', 'timeupdated', $timeupdated, array('id' => $amosid));

header('Content-Type: application/json; charset: utf-8');
$response = new stdclass();
$response->timeupdated = $timeupdated;
echo json_encode($response);
