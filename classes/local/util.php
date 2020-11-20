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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_amos\local;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/amos/mlanglib.php');

/**
 * Utilities and helper methods.
 *
 * @package     local_amos
 * @copyright   2020 David Mudrák <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class util {

    /**
     * Makes sure there is a zero-width space after non-word characters in the given string
     *
     * This is used to wrap long strings like 'A,B,C,D,...,x,y,z' in the translator
     *
     * @link http://www.w3.org/TR/html4/struct/text.html#h-9.1
     * @link http://www.fileformat.info/info/unicode/char/200b/index.htm
     *
     * @param string $text plain text
     * @return string
     */
    public static function add_breaks($text) {
        return preg_replace('/([,])(\S)/', '$1'."\xe2\x80\x8b".'$2', $text);
    }

    /**
     * Returns versions on which components were standard ones.
     *
     * @return array
     */
    public static function standard_components_range_versions(): array {

        $minmax = [];

        $list = get_config('local_amos', 'standardcomponents');

        foreach (explode(PHP_EOL, $list) as $line) {
            $parts = preg_split('/\s+/', $line, null, PREG_SPLIT_NO_EMPTY);

            if (empty($parts)) {
                continue;
            }

            if ($parts[0] !== clean_param($parts[0], PARAM_COMPONENT)) {
                debugging('Unexpected standardcomponents line starting with: ' . $parts[0], DEBUG_DEVELOPER);
                continue;
            }

            if (count($parts) == 1) {
                $minmax[$parts[0]] = [PHP_INT_MIN, PHP_INT_MAX];

            } else if (count($parts) == 2) {
                if ($parts[1] > 0) {
                    $minmax[$parts[0]] = [$parts[1], PHP_INT_MAX];

                } else {
                     $minmax[$parts[0]] = [PHP_INT_MIN, -$parts[1]];
                }

            } else if (count($parts) == 3) {
                if ($parts[1] > 0 && $parts[2] < 0) {
                    $minmax[$parts[0]] = [$parts[1], -$parts[2]];

                } else if ($parts[1] < 0 && $parts[2] > 0) {
                    $minmax[$parts[0]] = [$parts[2], -$parts[1]];

                } else {
                    debugging('Unexpected standardcomponents line versions range: ' . $line, DEBUG_DEVELOPER);
                    continue;
                }

            } else {
                debugging('Unexpected standardcomponents line syntax: ' . $line, DEBUG_DEVELOPER);
            }
        }

        return $minmax;
    }

    /**
     * Returns a tree of standard components.
     *
     * @return array (int)versioncode => (string)legacyname => (string)frankenstylename
     */
    public static function standard_components_tree(): array {

        $tree = [];

        foreach (\mlang_version::list_all() as $mlangversion) {
            $tree[$mlangversion->code] = [];
        }

        $minmax = static::standard_components_range_versions();

        foreach (array_keys($tree) as $version) {
            $tree[$version]['moodle'] = 'core';
            foreach ($minmax as $component => [$min, $max]) {
                if ($min <= $version && $version <= $max) {
                    [$type, $name] = \core_component::normalize_component($component);

                    if ($type === 'core' || $type === 'mod') {
                        $filename = $name;

                    } else {
                        $filename = $type . '_' . $name;
                    }

                    $tree[$version][$filename] = $type . '_' . $name;
                }
            }
        }

        return $tree;
    }

    /**
     * Returns a list of components that were/are standard on at least some version.
     *
     * @return array (string)legacyname => (string)frankenstylename
     */
    public static function standard_components_list(): array {

        $list = [];

        foreach (static::standard_components_tree() as $sublist) {
            $list += $sublist;
        }

        return $list;
    }

    /**
     * Returns a list of components that are standard in the latest known version.
     *
     * @return array (string)legacyname => (string)frankenstylename
     */
    public static function standard_components_in_latest_version(): array {

        $tree = static::standard_components_tree();
        $latestversioncode = max(array_keys($tree));

        return $tree[$latestversioncode];
    }
}
