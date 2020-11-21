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
 * Parameters:
 * --execute - execute required steps to make AMOS and Git repo synced
 *
 * @package    local
 * @subpackage amos
 * @copyright  2011 David Mudrak <david@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/amos/cli/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->dirroot . '/local/amos/locallib.php');
require_once($CFG->libdir.'/clilib.php');

list($options, $unrecognized) = cli_get_params(array('execute' => false));

$plugins = \local_amos\local\util::standard_components_tree();

$stage = new mlang_stage();

$cliresult = 0;

foreach ($plugins as $versioncode => $plugintypes) {
    $version = mlang_version::by_code($versioncode);

    if ($version->branch == 'MOODLE_40_STABLE') {
        $gitbranch = 'origin/master';
    } else {
        $gitbranch = 'origin/' . $version->branch;
    }

    foreach ($plugintypes as $legacyname => $frankenstylename) {

        // moodle.org was replaced with a local plugin and strings were dropped from 2.0 and 2.1
        if ($legacyname == 'moodle.org') {
            continue;
        }

        // prepare an empty component containing the fixes
        $fixcomponent = new mlang_component($legacyname, 'en', $version);

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
        // see core_component::fetch_plugintypes() in lib/classes/component.php
        // when adding a new one.
        $basedirs = [
            'mod'                   => 'mod',
            'auth'                  => 'auth',
            'enrol'                 => 'enrol',
            'message'               => 'message/output',
            'block'                 => 'blocks',
            'filter'                => 'filter',
            'editor'                => 'lib/editor',
            'format'                => 'course/format',
            'profilefield'          => 'user/profile/field',
            'report'                => 'report',
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
            'quizaccess'            => 'mod/quiz/accessrule',
            'scormreport'           => 'mod/scorm/report',
            'workshopform'          => 'mod/workshop/form',
            'workshopallocation'    => 'mod/workshop/allocation',
            'workshopeval'          => 'mod/workshop/eval',
            'local'                 => 'local',
            'tool'                  => 'admin/tool',
            'gradingform'           => 'grade/grading/form',
            'assignsubmission'      => 'mod/assign/submission',
            'assignfeedback'        => 'mod/assign/feedback',
            'booktool'              => 'mod/book/tool',
            'cachestore'            => 'cache/stores',
            'cachelock'             => 'cache/locks',
            'tinymce'               => 'lib/editor/tinymce/plugins',
            'atto'                  => 'lib/editor/atto/plugins',
            'calendartype'          => 'calendar/type',
            'logstore'              => 'admin/tool/log/store',
            'availability'          => 'availability/condition',
            'ltisource'             => 'mod/lti/source',
            'ltiservice'            => 'mod/lti/service',
            'antivirus'             => 'lib/antivirus',
            'dataformat'            => 'dataformat',
            'search'                => 'search/engine',
            'media'                 => 'media/player',
            'fileconverter'         => 'files/converter',
            'mlbackend'             => 'lib/mlbackend',
            'customfield'           => 'customfield/field',
            'forumreport'           => 'mod/forum/report',
            'h5plib'                => 'h5p/h5plib',
            'contenttype'           => 'contentbank/contenttype',
        ];

        if ($version->code <= 21) {
            // since 2.2 beta, reports have moved
            $basedirs['report'] = 'admin/report';
        }

        if ($plugintype == 'core') {
            $filepath = 'lang/en/'.$legacyname.'.php';
        } else if (!isset($basedirs[$plugintype])) {
            fputs(STDERR, "!! Unknown plugin type '{$plugintype}'\n");
            $cliresult = 1;
            continue;
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
            // The $filepath does not exist in the $gitbranch. Probably removed from core to the plugins directory.
            // Previously we used to delete all the strings, it was a mistake. Better to fail loudly and let the site
            // admins to fix the list of standard plugins.
            if ($amoscomponent->has_string()) {
                fputs(STDERR, "SUPPOSEDLY STANDARD COMPONENT '{$frankenstylename}' FILE '{$filepath}' NOT FOUND IN {$gitbranch}\n");
                exit(2);
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

        $gitcomponent = mlang_component::from_phpfile($dumpfile, 'en', $version, time());

        foreach ($amoscomponent as $amosstring) {
            $gitstring = $gitcomponent->get_string($amosstring->id);

            if (is_null($gitstring)) {
                fputs(STDOUT, "<< AMOS ONLY: {$version->dir} [{$amosstring->id},{$frankenstylename}]\n");
                $fixstring = clone($amosstring);
                $fixstring->deleted = true;
                $fixstring->clean_text();
                $fixcomponent->add_string($fixstring);
                continue;
            }

            if ($gitstring->text !== $amosstring->text) {
                fputs(STDOUT, "!= AMOS GIT DIFF: {$version->dir} [{$amosstring->id},{$frankenstylename}]\n");
                $gitstring->clean_text();
                $fixcomponent->add_string($gitstring);
                continue;
            }
        }

        foreach ($gitcomponent as $gitstring) {
            $amosstring = $amoscomponent->get_string($gitstring->id);

            if (is_null($amosstring)) {
                fputs(STDOUT, ">> GIT ONLY: {$version->dir} [{$gitstring->id},{$frankenstylename}]\n");
                $gitstring->clean_text();
                $fixcomponent->add_string($gitstring);
                continue;
            }
        }

        if ($fixcomponent->has_string()) {
            $stage->add($fixcomponent);
        }

        $fixcomponent->clear();
        $amoscomponent->clear();
        $gitcomponent->clear();
    }
}

list($x, $y, $z) = mlang_stage::analyze($stage);
if ($x > 0) {
    fputs(STDOUT, "There are $x string changes prepared for sync execution\n");
    fputs(STDOUT, "JENKINS:SET-STATUS-UNSTABLE\n");
    if ($options['execute']) {
        fputs(STDOUT, "Executing\n");
        $stage->commit('Fixing the drift between Git and AMOS repository', array('source' => 'fixdrift', 'userinfo' => 'AMOS-bot <amos@moodle.org>'));
    }
}

exit($cliresult);
