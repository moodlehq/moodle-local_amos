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
 * Helper class to merge one string file into another
 *
 * @package   local_amos
 * @copyright 2013 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cli_merge_files {
    /** @var cli_logger */
    protected $logger;

    /**
     * Instantiate the merge helper
     *
     * @param cli_logger $logger
     */
    public function __construct(cli_logger $logger) {
        $this->logger = $logger;
    }

    /**
     * Logs a message
     *
     * @param string $message message to log
     * @param string $level message level
     */
    public function log($message, $level = cli_logger::LEVEL_INFO) {
        $this->logger->log('merge-files', $message, $level);
    }

    /**
     * Returns all strings defined in the given file
     *
     * This is supposed to be used on trusted sources only, so plain require() is ok here.
     *
     * @param string $filename
     * @return array
     */
    public function load_strings_from_file($filename) {
        $string = [];
        require($filename);
        return $string;
    }

    /**
     * Replaces strings declaration in the file with changed ones
     *
     * If false is returned, the $filecontents may or may not be modified. The
     * caller should not consider its value valid.
     *
     * @param string $filecontents the file contents to be modified
     * @param array $fromstrings list of strings and their values to be replaced
     * @param array $tostrings list of strings and their new values
     * @return int|false number of replaced strings, or false on error
     */
    public function replace_strings_in_file(&$filecontents, array $fromstrings, array $tostrings) {

        $changes = 0;
        $realchanges = 0;

        foreach ($tostrings as $changeid => $changetext) {
            if (!isset($fromstrings[$changeid])) {
                $this->log('Attempting to merge an orphaned change: "' . $changeid . '"', cli_logger::LEVEL_WARNING);
                continue;
            }
            if ($fromstrings[$changeid] !== $changetext) {
                $changes++;
                // This is known to have troubles to find the string if it contains certain characters such as backslashes.
                $pattern = '/(^\s*\$string\s*\[\s*\'' . preg_quote($changeid, '/') . '\'\s*\]\s*=\s*)(\'|")' .
                    preg_quote(str_replace("'", "\\'", $fromstrings[$changeid]), '/') . '(\\2\s*)(;[^;]*\s*$)/m';
                if (!preg_match($pattern, $filecontents)) {
                    $this->log('String "' . $changeid . '" not found', cli_logger::LEVEL_DEBUG);
                    continue;
                }
                $replacement = '$1' . var_export($changetext, true) . '$4';
                $count = 0;
                $filecontents = preg_replace($pattern, $replacement, $filecontents, -1, $count);
                if (!$count) {
                    $this->log('No change done by preg_replace()', cli_logger::LEVEL_DEBUG);
                }
                if (is_null($filecontents)) {
                    $this->log('Error in preg_replace()', cli_logger::LEVEL_DEBUG);
                    return false;
                }
                $realchanges += $count;
            } else {
                $this->log('String "' . $changeid . '" identical, no update needed', cli_logger::LEVEL_DEBUG);
            }
        }

        if ($changes <> $realchanges) {
            $this->log('Expected changes: ' . $changes . ', real changes: ' . $realchanges, cli_logger::LEVEL_WARNING);
        }

        return $realchanges;
    }

    /**
     * Executes the merge process
     *
     * @return int|bool number of modified strings or false on error
     */
    public function merge_changes() {

        if (!$this->is_ready()) {
            $this->log('Helper not ready to merge changes', cli_logger::LEVEL_ERROR);
            return false;
        }

        $this->load_main_file();
        $this->load_changes_file();

        $merged = $this->replace_main_file_strings();

        if ($merged) {
            $this->rewrite_main_file();
        } else if ($merged === 0) {
            $this->log('No strings changed', cli_logger::LEVEL_DEBUG);
        } else {
            $this->log('Error while merging strings', cli_logger::LEVEL_ERROR);
        }

        return $merged;
    }
}
