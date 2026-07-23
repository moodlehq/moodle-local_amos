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
 * Factory class for obtaining a parser
 *
 * @package     local_amos
 * @subpackage  amos
 * @copyright   2010 David Mudrak <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class amos_parser_factory {
    /**
     * Returns an instance of parser for the given format of data
     *
     * @param string $format format of data like 'php', 'xml', 'csv' etc (alphanumerical only)
     * @return instance of a class implementing {@see amos_parser} interface
     */
    public static function get_parser($format) {

        $format = clean_param($format, PARAM_ALPHANUM);
        $classname = __NAMESPACE__ . '\\amos_' . $format . '_parser';
        if (class_exists($classname)) {
            return call_user_func("$classname::get_instance");
        } else {
            throw new coding_error('No such parser implemented');
        }
    }
}
