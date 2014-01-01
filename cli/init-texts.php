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
 * Populate the amos_texts table
 *
 * @package     local_amos
 * @subpackage  cli
 * @copyright   2013 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->dirroot . '/local/amos/cli/utilslib.php');

$logger = new amos_cli_logger();

$rs = $DB->get_recordset('amos_repository', array('textid' => null), '', 'id, text');

foreach ($rs as $record) {
    $texthash = sha1($record->text);
    $known = $DB->get_record('amos_texts', array('texthash' => $texthash), 'id', IGNORE_MISSING);
    if ($known === false) {
        $action = 'N'; // New
        $textid = $DB->insert_record('amos_texts', array('texthash' => $texthash, 'text' => $record->text), true, true);
    } else {
        $action = 'R'; // Reused
        $textid = $known->id;
    }
    $DB->set_field('amos_repository', 'textid', $textid, array('id' => $record->id));
    $logger->log('init-texts', $action.' '.$texthash.' '.$record->id);
}
