<?php

// This file is part of Moodle - http://moodle.org/
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

/**
 * Propagates deletions of English strings into the other lang packs
 *
 * The procedure is known as reverse cleanup. This script takes the most recent
 * snapshot of every component in the English lang pack. If a string removal is
 * part of the snapshot, the script propagates such removal into all other
 * languages. If the string is already removed in the other language, the
 * removing commit is not recorded.
 *
 * @package   local_amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/local/amos/cli/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');

$mem = memory_get_usage();
echo "LOADING COMPONENTS TREE... ";
$tree = mlang_tools::components_tree();
echo "DONE\n";
foreach ($tree as $vercode => $languages) {
    $version = mlang_version::by_code($vercode);
    foreach ($languages['en'] as $componentname => $unused) {
        $memprev = $mem;
        $mem = memory_get_usage();
        $memdiff = $memprev < $mem ? '+' : '-';
        $memdiff = $memdiff . abs($mem - $memprev);
        echo "{$version->label} {$componentname} [{$mem} {$memdiff}]\n";
        $english = mlang_component::from_snapshot($componentname, 'en', $version, null, true, true);
        foreach ($english->get_iterator() as $string) {
            if ($string->deleted) {
                $stage = new mlang_stage();
                foreach (array_keys($tree[$vercode]) as $otherlang) {
                    if ($otherlang == 'en') {
                        continue;
                    }
                    $other = new mlang_component($english->name, $otherlang, $english->version);
                    $other->add_string(new mlang_string($string->id, '[deleted]', null, 1));
                    $stage->add($other);
                }
                $stage->rebase();
                if ($stage->has_component()) {
                    $msg = <<<EOF
Propagating removal of the string

The string {$string->id} was removed from the English language pack by
{$string->extra->userinfo} at {$string->timemodified}. Their commit message was:
{$string->extra->commitmsg}
{$string->extra->commithash}
EOF;
                    echo "COMMIT removal of '{$string->id}' from '{$english->name}'\n";
                    $stage->commit($msg, array('source' => 'bot', 'userinfo' => 'David Mudrak <david@moodle.com>'), true);
                }
                $stage->clear();
                unset($stage);
            }
        }
    }
}
echo "DONE\n";
