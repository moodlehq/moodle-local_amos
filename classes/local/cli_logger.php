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
 * Provides logging facilities for AMOS cli jobs
 *
 * Simply prints log messages to the STDOUT (info and warnings) and STDERR (errors).
 *
 * @package   local_amos
 * @copyright 2012 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cli_logger {
    /** Error log message level. */
    const LEVEL_ERROR = 100;

    /** Warning log message level. */
    const LEVEL_WARNING = 200;

    /** Informational log message level. */
    const LEVEL_INFO = 300;

    /** Debugging log message level. */
    const LEVEL_DEBUG = 400;

    /**
     * Logs a message
     *
     * @param string $job the AMOS CLI job providing the message
     * @param string $message the message to log
     * @param string $level error, warning or info level
     */
    public function log($job, $message, $level = self::LEVEL_INFO) {

        $formatted = $this->format_message($job, $message, $level);
        $output = $this->get_output($job, $level);
        fwrite($output, $formatted);
    }

    /**
     * Prepares the log message to print
     *
     * @param string $job the AMOS CLI job providing the message
     * @param string $message the message to log
     * @param string $level error, warning or info level
     * @return string
     */
    protected function format_message($job, $message, $level) {

        $job = trim($job);
        $message = trim($message);
        switch ($level) {
            case self::LEVEL_ERROR:
                $prefix = "\033[0;31m[ERR]\033[0m ";
                break;
            case self::LEVEL_WARNING:
                $prefix = "\033[0;31m[WRN]\033[0m ";
                break;
            case self::LEVEL_DEBUG:
                $prefix = '[DBG] ';
                break;
            default:
                $prefix = '[INF] ';
        }

        return $prefix . $job . ': ' . $message . PHP_EOL;
    }

    /**
     * Choose appropriate output stream handlers for the message
     *
     * @param string $job the AMOS CLI job providing the message
     * @param string $level error, warning or info level
     * @return file
     */
    protected function get_output($job, $level) {
        if ($level < self::LEVEL_WARNING) {
            return STDERR;
        } else {
            return STDOUT;
        }
    }
}
