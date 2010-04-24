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
 * Defines and prints AMOS navigation tabs
 *
 * @package   local_amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if (!isset($currenttab)) {
    $currenttab = 'translator';
}

$tabs       = array();
$row        = array();
$inactive   = array();
$activated  = array();

// top level tabs
$row[] = new tabobject('translator', new moodle_url('/local/amos/view.php'), 'Translator');
$row[] = new tabobject('stage', new moodle_url('/local/amos/stage.php'), 'Stage');

$tabs[] = $row;
print_tabs($tabs, $currenttab, $inactive, $activated);
