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
 * class_alias() mappings so that:
 *  - any external code still referring to the old global class names keeps working, and
 *  - previously serialized amos_component/amos_string/amos_stage objects (persisted to disk
 *    for in-progress translator stages and stashes) can still be unserialize()'d, since PHP
 *    embeds the literal class name used at serialization time.
 *
 * Do not add any new code here - this file must only register these aliases.
 *
 * @package   local_amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class_alias(\local_amos\local\amos_exception::class, 'mlang_exception');
class_alias(\local_amos\local\amos_component::class, 'mlang_component');
class_alias(\local_amos\local\amos_string::class, 'mlang_string');
class_alias(\local_amos\local\amos_stage::class, 'mlang_stage');
class_alias(\local_amos\local\amos_persistent_stage::class, 'mlang_persistent_stage');
class_alias(\local_amos\local\amos_stash::class, 'mlang_stash');
class_alias(\local_amos\local\amos_version::class, 'mlang_version');
class_alias(\local_amos\local\amos_tools::class, 'mlang_tools');
