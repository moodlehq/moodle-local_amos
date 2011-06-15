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
 * Detects and fixes drifts in the English strings
 *
 * @package    local
 * @subpackage amos
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/local/amos/cli/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->dirroot . '/local/amos/locallib.php');

$plugins = local_amos_standard_plugins();

foreach ($plugins as $versionnumber => $plugintypes) {
    $version = mlang_version::by_dir($versionnumber);

    if ($version->branch == 'MOODLE_21_STABLE') {
        $gitbranch = 'origin/master';
    } else {
        $gitbranch = 'origin/' . $version->branch;
    }

    foreach ($plugintypes as $legacyname => $frankenstylename) {

        // get the most recent snapshot from the AMOS repository
        $amoscomponent = mlang_component::from_snapshot($legacyname, 'en', $version);

        // get the location of the plugin
        if ($frankenstylename == 'core') {
            $plugintype = 'core';
            $pluginname = null;
        } else {
            $plugintype = substr($frankenstylename, 0, strpos($frankenstylename, '_'));
            $pluginname = substr($frankenstylename, strpos($frankenstylename, '_') + 1);
        }
        // very hacky way to get plugin basedirs for all versions
        $basedirs = array(
            'mod'                   => 'mod',
            'auth'                  => 'auth',
            'enrol'                 => 'enrol',
            'message'               => 'message/output',
            'block'                 => 'blocks',
            'filter'                => 'filter',
            'editor'                => 'lib/editor',
            'format'                => 'course/format',
            'profilefield'          => 'user/profile/field',
            'report'                => 'admin/report',
            'coursereport'          => 'course/report',
            'gradeexport'           => 'grade/export',
            'gradeimport'           => 'grade/import',
            'gradereport'           => 'grade/report',
            'mnetservice'           => 'mnet/service',
            'webservice'            => 'webservice',
            'repository'            => 'repository',
            'portfolio'             => 'portfolio',
            'qtype'                 => 'question/type',
            'qformat'               => 'question/format',
            'qbehaviour'            => 'question/behaviour',
            'plagiarism'            => 'plagiarism',
            'theme'                 => 'theme',
            'assignment'            => 'mod/assignment/type',
            'datafield'             => 'mod/data/field',
            'datapreset'            => 'mod/data/preset',
            'quiz'                  => 'mod/quiz/report',
            'workshopform'          => 'mod/workshop/form',
            'workshopallocation'    => 'mod/workshop/allocation',
            'workshopeval'          => 'mod/workshop/eval',
            'local'                 => 'local',
        );

        if ($plugintype == 'core') {
            $filepath = 'lang/en/'.$legacyname.'.php';
        } else {
            $filepath = $basedirs[$plugintype].'/'.$pluginname.'/lang/en/'.$legacyname.'.php';
        }

        // get the most recent snapshot from the git repository
        chdir(AMOS_REPO_MOODLE);
        $gitout = array();
        $gitstatus = 0;
        $gitcmd = AMOS_PATH_GIT . " show {$gitbranch}:{$filepath} 2> /dev/null";
        exec($gitcmd, $gitout, $gitstatus);

        if ($gitstatus == 128) {
            // the $filepath does not exist in the $gitbranch
            if ($amoscomponent->has_string()) {
                fputs(STDERR, "!! '{$filepath}' does not exist in {$gitbranch}\n");
            }
            // no string file and nothing in AMOS - that is correct
            continue;
        }

        if ($gitstatus <> 0) {
            fputs(STDERR, "FATAL ERROR {$gitstatus} EXECUTING {$gitcmd}\n");
            exit($gitstatus);
        }

        $tmp = make_upload_directory('amos/temp/fix-drift/'.$version->dir);
        $dumpfile = $tmp.'/'.$legacyname.'.php';
        file_put_contents($dumpfile, implode("\n", $gitout));

        $gitcomponent = mlang_component::from_phpfile($dumpfile, 'en', $version);

        foreach ($amoscomponent->get_iterator() as $amosstring) {
            $gitstring = $gitcomponent->get_string($amosstring->id);

            if (is_null($gitstring)) {
                fputs(STDOUT, "<< AMOS ONLY: {$version->dir} [{$amosstring->id},{$frankenstylename}]\n");
            }
        }

        foreach ($gitcomponent->get_iterator() as $gitstring) {
            $amosstring = $amoscomponent->get_string($gitstring->id);

            if (is_null($amosstring)) {
                fputs(STDOUT, ">> GIT ONLY: {$version->dir} [{$gitstring->id},{$frankenstylename}]\n");
            }
        }

    }
}
