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
 * Legacy compatibility shim for the classes previously defined in this file.
 *
 * The classes formerly named mlang_* have been moved into the local_amos\local namespace
 * and renamed to amos_* (see classes/local/). This file only keeps backward-compatible
 * class_alias() mappings so that any external code still referring to the old global class
 * names keeps working.
 *
 * Do not add any new code here - this file must only register these aliases.
 *
 * @package     local_amos
 * @subpackage  amos
 * @copyright   2010 David Mudrak <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class_alias(\local_amos\local\amos_parser::class, 'mlang_parser');
class_alias(\local_amos\local\amos_parser_factory::class, 'mlang_parser_factory');
class_alias(\local_amos\local\amos_parser_exception::class, 'mlang_parser_exception');
class_alias(\local_amos\local\amos_php_parser::class, 'mlang_php_parser');
