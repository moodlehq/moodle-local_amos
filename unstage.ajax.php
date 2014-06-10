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
 * Unstage a single string via AJAX request from the stage page
 *
 * @package   local
 * @supackage amos
 * @copyright 2011 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
//require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/mlanglib.php');

require_login(SITEID, false);
if (!has_capability('local/amos:stage', context_system::instance())) {
    header('HTTP/1.1 403 Forbidden');
    die();
}
if (!confirm_sesskey()) {
    header('HTTP/1.1 403 Forbidden');
    die();
}

$name    = required_param('component', PARAM_ALPHANUMEXT);
$branch  = required_param('branch', PARAM_INT);
$lang    = required_param('lang', PARAM_ALPHANUMEXT);
$unstage = required_param('unstage', PARAM_STRINGID);

$response = new stdClass();
$response->success = true;
$response->error = '';

$stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
$component = $stage->get_component($name, $lang, mlang_version::by_code($branch));
if ($component) {
    $component->unlink_string($unstage);
    $stage->store();
} else {
    $response->success = false;
    $response->error = 'Unable to get load component';
}

header('Content-Type: application/json; charset: utf-8');
echo json_encode($response);
