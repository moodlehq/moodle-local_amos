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

/**
 * Provides {@see \local_amos\testable_amos_cli_logger} class.
 *
 * @package     local_amos
 * @category    test
 * @copyright   2013 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_amos;

use amos_cli_logger;

/**
 * Dummy implementation of the AMOS logger suitable for unit testing
 *
 * @copyright 2013 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testable_amos_cli_logger extends amos_cli_logger {
    /**
     * Logs a message
     *
     * @param string $job the AMOS CLI job providing the message
     * @param string $message the message to log
     * @param string $level error, warning or info level
     */
    public function log($job, $message, $level = self::LEVEL_INFO) {
    }
}
