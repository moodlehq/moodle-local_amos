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
 * Provides {@see \local_amos\local\testable_amos_tools} class.
 *
 * @package     local_amos
 * @category    test
 * @copyright   2010 David Mudrak <david.mudrak@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_amos\local;

/**
 * Makes protected method accessible for testing purposes
 *
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testable_amos_tools extends amos_tools {
    /**
     * Turn protected method to public to be able to test it directly.
     *
     * @param string $newstyle
     */
    public static function legacy_component_name($newstyle) { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod
        return parent::legacy_component_name($newstyle);
    }
}
