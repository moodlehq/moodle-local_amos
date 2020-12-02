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

/**
 * Provides the {@link \local_amos\task\import_workplace_plugins} class.
 *
 * @package     local_amos
 * @category    task
 * @copyright   2019 David Mudrák <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_amos\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/amos/mlanglib.php');

/**
 * Imports the strings used in the Moodle for Workplace plugins.
 *
 * @copyright 2019 David Mudrák <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_workplace_plugins extends \core\task\scheduled_task {

    /** @var \local_amos\local\git client to use */
    protected $git;

    /**
     * Return the task name.
     *
     * @return string
     */
    public function get_name() {
        return 'Import Workplace plugins strings';
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $CFG;

        $repo = $CFG->dataroot.'/amos/repos/moodle-workplace-pluginstrings';
        $key = '/var/www/.ssh/moodle-workplace-pluginstrings.key';

        $this->git = new \local_amos\local\git($repo, $key);

        foreach ($this->get_branches_to_process() as $branchname => $job) {
            $mver = \mlang_version::by_branch('MOODLE_'.$job['version'].'_STABLE');

            if ($mver === null) {
                mtrace(' unknown moodle version MOODLE_'.$job['version'].'_STABLE');
                continue;
            }

            mtrace(' Importing strings from '.$job['plugin'].' for '.$mver->branch);

            $path = $this->export_branch_to_temp($branchname);
            $code = new \local_amos\local\source_code($path);
            $info = $code->get_version_php();

            $components = [];

            foreach ($code->get_included_string_files() as $componentname => $stringfiles) {
                $stringfilecontent = reset($stringfiles);
                $stringfilepath = key($stringfiles);

                $component = [
                    'componentname' => $componentname,
                    'moodlebranch' => $mver->dir,
                    'language' => 'en',
                    'stringfilename' => basename($stringfilepath),
                    'stringfilecontent' => $stringfilecontent,
                ];

                $components[] = $component;
            }

            $results = \local_amos\external\update_strings_file::execute(
                'Moodle Workplace <workplace@moodle.com>',
                'Strings for '.$componentname.' '.$info['version'],
                $components
            );

            $results = \external_api::clean_returnvalue(\local_amos\external\update_strings_file::execute_returns(), $results);

            foreach ($results as $result) {
                mtrace('  '.$result['status'].' '.$result['componentname'].' '.$result['changes'].'/'.$result['found'].' changes');
            }
        }
    }

    /**
     * Exports the given branch into a temporary location for processing.
     *
     * @param string $branchname
     * @return string full path to the temporary location with the exported files
     */
    protected function export_branch_to_temp(string $branchname) : string {

        $target = make_request_directory();

        $this->git->exec('archive --format=tar --prefix=/ '.escapeshellarg($branchname).
            ' | (cd '.escapeshellarg($target).' && tar xf -) 2>/dev/null');

        return $target;
    }

    /**
     * Return the list of branches to process.
     *
     * The repository is supposed to contain local branches like 'tool_wp--37'.
     *
     * @return array (string)branchname => ['plugin' => (string)component_name, 'version' => (int)version_code]
     */
    protected function get_branches_to_process() : array {

        // We use a mirror clone, so updating the remote origin updates the local branches.
        $this->git->exec('remote update --prune');

        $result = [];

        foreach ($this->git->list_local_branches() as $branchname) {
            if ($branchname === 'master') {
                continue;
            }

            if (strpos($branchname, '--') === false) {
                mtrace(' unexpected branch name: '.$branchname);
                continue;
            }

            [$plugin, $version] = explode('--', $branchname);

            if (empty($plugin) || empty($version)) {
                mtrace(' unable to parse branch name: '.$branchname);
                continue;
            }

            if (clean_param($plugin, PARAM_COMPONENT) !== $plugin) {
                mtrace(' invalid plugin name: '.$plugin);
                continue;
            }

            if ((string)clean_param($version, PARAM_INT) !== $version) {
                mtrace(' invalid version: '.$version);
                continue;
            }

            $result[$branchname] = ['plugin' => $plugin, 'version' => $version];
        }

        return $result;
    }
}
