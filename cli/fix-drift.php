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
 * Detects and fixes drifts in the English strings
 *
 * Parameters:
 * --execute - execute required steps to make AMOS and Git repo synced
 *
 * @package     local_amos
 * @copyright   2011 David Mudrak <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/amos/cli/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->dirroot . '/local/amos/locallib.php');
require_once($CFG->libdir.'/clilib.php');

[$options, $unrecognised] = cli_get_params([
    'execute' => false,
]);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL . '  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognised));
}

cli_writeln('Updating remote tracking branches in ' . AMOS_REPO_MOODLE);

$git = new \local_amos\local\git(AMOS_REPO_MOODLE);
$git->exec('remote update --prune');

$plugins = \local_amos\local\util::standard_components_tree();

$stages = [];
$cliresult = 0;

// Load the location of standard plugin types and core subsystems.
$componentsjson = (array) json_decode(implode(PHP_EOL, $git->exec('show origin/main:lib/components.json')), true);
$plugintypelocations = [];

foreach ($componentsjson['plugintypes'] as $plugtype => $pluglocation) {
    if (str_starts_with($pluglocation, 'public/')) {
        $plugintypelocations[$plugtype] = substr($pluglocation, 7);
    } else {
        $plugintypelocations[$plugtype] = $pluglocation;
    }
}

foreach (preg_split('|\R|', get_config('local_amos', 'plugintypelocations'), -1, PREG_SPLIT_NO_EMPTY) as $line) {
    if (preg_match('|^\s*([a-z][a-z0-9]*)\s+(.+)$|', $line, $matches)) {
        $plugintypelocations[$matches[1]] = $matches[2];
    }
}

cli_writeln('Seeking for differences between AMOS and Git');

foreach ($plugins as $versioncode => $plugintypes) {
    $version = mlang_version::by_code($versioncode);

    if ($git->has_remote_branch($version->branch)) {
        $gitbranch = 'origin/' . $version->branch;

    } else if ($versioncode == max(array_keys($plugins))) {
        $gitbranch = 'origin/main';

    } else {
        cli_error('Git branch not found', 3);
    }

    cli_writeln('- Processing version ' . $version->label . ' on ' . $gitbranch);

    foreach ($plugintypes as $legacyname => $frankenstylename) {
        // Component moodle.org was replaced with a local plugin and strings were dropped from 2.0 and 2.1.
        if ($legacyname == 'moodle.org') {
            continue;
        }

        // Prepare an empty component containing the fixes.
        $fixcomponent = new mlang_component($legacyname, 'en', $version);

        // Get the most recent snapshot from the AMOS repository.
        $amoscomponent = mlang_component::from_snapshot($legacyname, 'en', $version);

        // Get the location of the plugin.
        if ($frankenstylename == 'core') {
            $plugintype = 'core';
            $pluginname = null;
        } else {
            $plugintype = substr($frankenstylename, 0, strpos($frankenstylename, '_'));
            $pluginname = substr($frankenstylename, strpos($frankenstylename, '_') + 1);
        }

        if ($version->code <= 21) {
            // Before 2.2 beta, reports were originally under admin.
            $plugintypelocations['report'] = 'admin/report';
        } else {
            $plugintypelocations['report'] = 'report';
        }

        if ($plugintype == 'core') {
            $filepath = 'lang/en/' . $legacyname . '.php';

        } else if (!isset($plugintypelocations[$plugintype])) {
            cli_writeln("!! Unknown plugin type '{$plugintype}'", STDERR);
            $cliresult = 1;
            continue;

        } else {
            $filepath = $plugintypelocations[$plugintype] . '/' . $pluginname . '/lang/en/' . $legacyname . '.php';
        }

        if ($version->code >= 501) {
            $filepath = 'public/' . $filepath;
        }

        // Get the most recent snapshot from the git repository.
        $gitcmd = ' show ' . escapeshellarg($gitbranch) . ':' . escapeshellarg($filepath) . ' 2> /dev/null';
        $gitout = $git->exec($gitcmd, false, $gitstatus);

        if ($gitstatus == 128) {
            // The $filepath does not exist in the $gitbranch. Probably removed from core to the plugins directory.
            // Previously we used to delete all the strings, it was a mistake. Better to fail loudly and let the site
            // admins to fix the list of standard plugins.
            if ($amoscomponent->has_string()) {
                cli_error("Supposedly standard component '{$frankenstylename}' file '{$filepath}' not found in {$gitbranch}", 2);
            }
            // No string file and nothing in AMOS - that is correct.
            continue;
        }

        if ($gitstatus <> 0) {
            cli_error("Fatal error {$gitstatus} executing {$gitcmd}\n");
            exit($gitstatus);
        }

        $tmp = make_upload_directory('amos/temp/fix-drift/'.$version->dir);
        $dumpfile = $tmp.'/'.$legacyname.'.php';
        file_put_contents($dumpfile, implode("\n", $gitout));

        $gitcomponent = mlang_component::from_phpfile($dumpfile, 'en', $version, time());

        foreach ($amoscomponent as $amosstring) {
            $gitstring = $gitcomponent->get_string($amosstring->id);

            if (is_null($gitstring)) {
                cli_writeln("<< AMOS ONLY: {$version->dir} [{$amosstring->id},{$frankenstylename}]");
                $fixstring = clone($amosstring);
                $fixstring->deleted = true;
                $fixstring->clean_text();
                $fixcomponent->add_string($fixstring);
                continue;
            }

            if ($gitstring->text !== $amosstring->text) {
                cli_writeln("!= AMOS GIT DIFF: {$version->dir} [{$amosstring->id},{$frankenstylename}]");
                $gitstring->clean_text();
                $fixcomponent->add_string($gitstring);
                continue;
            }
        }

        foreach ($gitcomponent as $gitstring) {
            $amosstring = $amoscomponent->get_string($gitstring->id);

            if (is_null($amosstring)) {
                cli_writeln(">> GIT ONLY: {$version->dir} [{$gitstring->id},{$frankenstylename}]");
                $gitstring->clean_text();
                $fixcomponent->add_string($gitstring);
                continue;
            }
        }

        if ($fixcomponent->has_string()) {
            $stages[$version->code] = $stages[$version->code] ?? new mlang_stage();
            $stages[$version->code]->add($fixcomponent);
        }

        $fixcomponent->clear();
        $amoscomponent->clear();
        $gitcomponent->clear();
    }
}

foreach ($stages as $versioncode => $stage) {
    [$x, $y, $z] = mlang_stage::analyze($stage);

    if ($x > 0) {
        cli_writeln("There are {$x} string changes prepared for sync execution on branch {$versioncode}");
        cli_writeln("JENKINS:SET-STATUS-UNSTABLE");

        if ($options['execute'] && $cliresult == 0) {
            cli_writeln("Executing");
            $stage->commit('Fixing the drift between Git and AMOS repository', [
                'source' => 'fixdrift',
                'userinfo' => 'AMOS-bot <amos@moodle.org>',
            ]);
        }
    }
}

exit($cliresult);
