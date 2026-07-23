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

namespace local_amos\local;

/**
 * Every parser must implement this interface
 *
 * @package     local_amos
 * @subpackage  amos
 * @copyright   2010 David Mudrak <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface amos_parser {
    /**
     * Returns singleton instance of the parser
     *
     * Direct contruction and cloning of instances should not be allowed
     */
    public static function get_instance();

    /**
     * Parses data and adds strings into given component
     *
     * @param mixed $data data to parse, typically a file content
     * @param amos_component $component component to add strings to
     * @param int $format the data format on the input, defaults to the one used since 2.0
     */
    public function parse($data, amos_component $component, $format = 2);
}
