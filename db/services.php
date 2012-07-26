<?php

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
 * AMOS web services
 *
 * @package   local_amos
 * @category  webservice
 * @copyright 2012 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = array(
        'local_amos_update_strings_file' => array(
                'classname'   => 'local_amos_external',
                'methodname'  => 'update_strings_file',
                'classpath'   => 'local/amos/externallib.php',
                'description' => 'Imports strings from a string file.',
                'type'        => 'write',
        )
);

$services = array(
        'AMOS Import web service' => array(
                'functions' => array(
                    'local_amos_update_strings_file'
                ),
                'restrictedusers' => 1,
                'enabled' => 1,
        ),
);
