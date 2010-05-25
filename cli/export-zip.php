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
 * Prepares language packages in Moodle ZIP format to be published
 *
 * @package   local_amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (isset($_SERVER['REMOTE_ADDR'])) {
    error_log('AMOS cli scripts can not be called via the web interface');
    exit;
}

// Do not set moodle cookie because we do not need it here, it is better to emulate session
define('NO_MOODLE_COOKIES', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/local/amos/cli/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');

// Let us get an information about existing components
$sql = "SELECT branch,lang,component,COUNT(stringid) AS numofstrings
          FROM {amos_repository}
         WHERE deleted=0
      GROUP BY branch,lang,component
      ORDER BY branch,lang,component";
$rs = $DB->get_recordset_sql($sql);
$tree = array();    // [branch][language][component] => numofstrings
foreach ($rs as $record) {
    $tree[$record->branch][$record->lang][$record->component] = $record->numofstrings;
}
$rs->close();
$packer = get_file_packer('application/zip');
$status = true; // success indicator
remove_dir(AMOS_EXPORT_ZIP_DIR, true);
foreach ($tree as $vercode => $languages) {
    $version = mlang_version::by_code($vercode);
    $md5 = '';
    foreach ($languages as $langcode => $components) {
        if ($langcode == 'en') {
            continue;
        }
        mkdir(AMOS_EXPORT_ZIP_DIR . '/' . $version->dir . '/' . $langcode, 0772, true);
        $zipfiles = array();
        $langname = $langcode; // fallback to be replaced by localized name
        foreach ($components as $componentname => $unused) {
            $component = mlang_component::from_snapshot($componentname, $langcode, $version);
            if ($component->has_string()) {
                $file = AMOS_EXPORT_ZIP_DIR . '/' . $version->dir . '/' . $langcode . '/' . $component->name . '.php';
                $component->export_phpfile($file);
                $zipfiles[$langcode . '/' . $component->name . '.php'] = $file;
            }
            if ($component->name == 'langconfig' and $component->has_string('thislanguage')) {
                $langname = $component->get_string('thislanguage')->text;
            }
            $component->clear();
        }
        $zipfile = AMOS_EXPORT_ZIP_DIR.'/'.$version->dir.'/'.$langcode.'.zip';
        $status = $status and $packer->archive_to_pathname($zipfiles, $zipfile);
        if ($status) {
            echo "$zipfile\n";
            remove_dir(AMOS_EXPORT_ZIP_DIR . '/' . $version->dir . '/' . $langcode);
        } else {
            exit(1);
        }
        $md5 .= $langcode . ',' . md5_file($zipfile) . ',' . $langname . "\n";
    }
    file_put_contents(AMOS_EXPORT_ZIP_DIR.'/'.$version->dir.'/'.'languages.md5', $md5);
}
exit(0);
