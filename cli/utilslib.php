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
 * Library of helper classes used for AMOS cli jobs
 *
 * @package     local_amos
 * @subpackage  cli
 * @copyright   2012 David Mudrak <david@moodle.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir  . '/filelib.php');
require_once($CFG->libdir  . '/clilib.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->dirroot . '/local/amos/locallib.php');
require_once($CFG->dirroot . '/local/amos/renderer.php');

if (is_readable($CFG->dirroot . '/local/amos/cli/config.php')) {
    require_once($CFG->dirroot . '/local/amos/cli/config.php');
} else {
    require_once($CFG->dirroot . '/local/amos/cli/config-dist.php');
}

/**
 * Provides logging facilities for AMOS cli jobs
 *
 * Simply prints log messages to the STDOUT (info and warnings) and STDERR (errors).
 *
 * @copyright 2012 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class amos_cli_logger {

    const LEVEL_ERROR           = 100;  // serious error
    const LEVEL_WARNING         = 200;  // may need attention
    const LEVEL_INFO            = 300;  // normal progress message
    const LEVEL_DEBUG           = 400;  // more detailed debugging message

    /**
     * Logs a message
     *
     * @param string $job the AMOS CLI job providing the message
     * @param string $message the message to log
     * @param string $level error, warning or info level
     */
    public function log($job, $message, $level = self::LEVEL_INFO) {

        $formatted = $this->format_message($job, $message, $level);
        $output = $this->get_output($job, $level);
        fwrite($output, $formatted);
    }

    // End of external API /////////////////////////////////////////////////////

    /**
     * Prepares the log message to print
     *
     * @param string $job the AMOS CLI job providing the message
     * @param string $message the message to log
     * @param string $level error, warning or info level
     * @return string
     */
    protected function format_message($job, $message, $level) {

        $job = trim($job);
        $message = trim($message);
        switch ($level) {
        case self::LEVEL_ERROR:
            $prefix = '[ERR] ';
            break;
        case self::LEVEL_WARNING:
            $prefix = '[WRN] ';
            break;
        case self::LEVEL_DEBUG:
            $prefix = '[DBG] ';
            break;
        default:
            $prefix = '[INF] ';
        }

        return $prefix . $job . ': ' . $message . PHP_EOL;
    }

    /**
     * Choose appropriate output stream handlers for the message
     *
     * @param string $job the AMOS CLI job providing the message
     * @param string $level error, warning or info level
     * @return file
     */
    protected function get_output($job, $level) {
        if ($level < self::LEVEL_WARNING) {
            return STDERR;
        } else {
            return STDOUT;
        }
    }
}


/**
 * Helper class for export-zip job
 *
 * @copyright 2012 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class amos_export_zip {

    /** @var amos_cli_logger */
    protected $logger;

    /** @var string full path to the root of output folders (those to be rsynced) */
    protected $outputdirroot;

    /** @var string full path to the root of temp folders */
    protected $tempdirroot;

    /** @var string full path to the root of folders with packinfo.ser files */
    protected $vardirroot;

    /** @var int the timestamp of the current execution */
    protected $now;

    /** @var int the timestamp of the last execution of this job */
    protected $last;

    /** @var array in-memory stash for caching various data for other steps of the execution */
    protected $stash = array();

    /** @var local_amos_stats_manager instance used for updating translation stats while exporting the ZIPs */
    protected $statsman;

    /**
     * Instantinate the exporter job
     *
     */
    public function __construct(amos_cli_logger $logger, local_amos_stats_manager $statsman = null) {
        global $CFG;

        $this->logger = $logger;
        $this->outputdirroot = $CFG->dataroot.'/amos/export-zip';
        $this->tempdirroot = $CFG->dataroot.'/amos/temp/export-zip';
        $this->vardirroot = $CFG->dataroot.'/amos/var/export-zip';

        if (!$statsman) {
            $this->statsman = new local_amos_stats_manager();
        } else {
            $this->statsman = $statsman;
        }

        raise_memory_limit(MEMORY_HUGE);
    }

    /**
     * Logs a message
     *
     * @param string $message message to log
     * @param string $level message level
     */
    public function log($message, $level = amos_cli_logger::LEVEL_INFO) {
        $this->logger->log('export-zip', $message, $level);
    }

    /**
     * Initialize this exporter job instance
     */
    public function init() {
        $this->log('== Initializing the ZIP language packs exporter ==');
        $this->init_timestamp_now();
        $this->init_timestamp_last();
        $this->init_temp_folders();
    }

    /**
     * Regenerates ZIP packages in output folders if needed
     */
    public function rebuild_zip_packages() {
        $this->log('== Rebuilding outdated ZIP language packs ==');

        $langpacks = $this->detect_recently_modified_languages();

        foreach ($this->get_versions() as $version) {
            foreach ($langpacks as $langcode => $since) {
                if ($version->code < $since) {
                    continue;
                }
                $this->rebuild_zip_package($version, $langcode);
                $this->push_zip_package($version, $langcode);
                $this->update_packinfo($version, $langcode);
                $this->update_stats($version, $langcode);
            }
        }
    }

    /**
     * Regenerates languages.md5 and lang-table.html files in output folders
     */
    public function rebuild_output_folders() {
        $this->log('== Rebuilding languages.md5 and lang-table.html files ==');

        foreach ($this->get_versions() as $version) {
            $packinfofile = $this->vardirroot.'/'.$version->dir.'/packinfo.ser';
            if (!file_exists($packinfofile)) {
                $this->log('File packinfo.ser not found for '.$version->dir, amos_cli_logger::LEVEL_WARNING);
                continue;
            }
            $packinfo = unserialize(file_get_contents($packinfofile));
            ksort($packinfo);

            $this->rebuild_languages_md5($version, $packinfo);
            $this->rebuild_index_tablehtml($version, $packinfo);
        }
    }

    /**
     * Cleanup and dismiss this exporter job instance
     */
    public function finalize() {
        $this->log('== Finalizing the exporter job ==');
        $this->set_timestamp_last();
        $this->job_success('Done!');
    }

    // End of external API /////////////////////////////////////////////////////

    /**
     * Returns the list of mlang_versions that we should process
     *
     * @return array {@link mlang_version} objects indexed by the version code
     */
    protected function get_versions() {

        if (!isset($this->stash['versions'])) {
            $this->stash['versions'] = mlang_version::list_range(20);
            krsort($this->stash['versions']);
        }

        return $this->stash['versions'];
    }

    /**
     * Loads the current execution timestamps
     */
    protected function init_timestamp_now() {
        $this->now = time();
        $this->log('Setting the current execution timestamp to '.date('Y-m-d H:i:s', $this->now), amos_cli_logger::LEVEL_DEBUG);
    }

    /**
     * Loads the last execution timestamp
     */
    protected function init_timestamp_last() {
        $lastexportzip = get_config('local_amos', 'lastexportzip');
        if (empty($lastexportzip)) {
            $lastexportzip = 0;
            $this->log('Previous execution timestamp not found, setting it to the beginning of the epoch '.date('Y-m-d H:i:s', $lastexportzip), amos_cli_logger::LEVEL_DEBUG);
        } else {
            $this->log('Previous execution timestamp was '.date('Y-m-d H:i:s', $lastexportzip), amos_cli_logger::LEVEL_DEBUG);
        }
        $this->last = $lastexportzip;
    }

    /**
     * Stores the last execution timestamp
     */
    protected function set_timestamp_last() {
        $this->log('Store the current execution timestamp '.date('Y-m-d H:i:s', $this->now), amos_cli_logger::LEVEL_DEBUG);
        set_config('lastexportzip', $this->now, 'local_amos');
    }

    /**
     * Prepares the temporary folders where new ZIPs will be generated
     */
    protected function init_temp_folders() {
        $this->log('Preparing temporary folders', amos_cli_logger::LEVEL_DEBUG);
        fulldelete($this->tempdirroot);
        foreach($this->get_versions() as $version) {
            make_writable_directory($this->tempdirroot.'/'.$version->dir);
        }
    }

    /**
     * Returns the list of languages that were modified since the last time we executed this job
     *
     * @return array of (string) lang => (int) since version code
     */
    protected function detect_recently_modified_languages() {
        global $DB;

        $params = [
            'lastexportzip1' => $this->last,
            'lastexportzip2' => $this->last,
        ];

        $this->log('Looking for recent commits since ' . date('Y-m-d H:i:s', $this->last), amos_cli_logger::LEVEL_DEBUG);

        $sql = "SELECT *
                  FROM (
                        SELECT t.lang, MIN(t.since) AS since
                          FROM {amos_translations} t
                          JOIN {amos_commits} c ON c.id = t.commitid
                         WHERE c.timecommitted >= :lastexportzip1
                           AND t.lang <> 'en_fix'
                      GROUP BY t.lang

                         UNION

                        SELECT 'en' AS lang, MIN(s.since) AS since
                          FROM {amos_strings} s
                          JOIN {amos_commits} c ON c.id = s.commitid
                         WHERE c.timecommitted >= :lastexportzip2
                       ) r
              ORDER BY lang";

        $result = $DB->get_records_sql_menu($sql, $params);

        foreach ($result as $lang => $since) {
            $this->log(sprintf('Recent commit detected in language %s affecting branches since %d', $lang, $since),
                amos_cli_logger::LEVEL_DEBUG);
        }

        if (empty($result)) {
            $this->log('No recent changes found, nothing to rebuild', amos_cli_logger::LEVEL_DEBUG);
        }

        return $result;
    }

    /**
     * Dumps all component strings into PHP files and makes ZIP archive of them
     *
     * @param mlang_version $version
     * @param string $langcode
     */
    protected function rebuild_zip_package(mlang_version $version, $langcode) {
        $this->log('Rebuilding temp/'.$version->dir.'/'.$langcode.'.zip', amos_cli_logger::LEVEL_DEBUG);

        foreach (mlang_tools::list_components() as $componentname => $ignored) {
            $component = mlang_component::from_snapshot($componentname, $langcode, $version);
            if ($langcode !== 'en') {
                $english = mlang_component::from_snapshot($componentname, 'en', $version);
                $component->intersect($english);
                $english->clear();
            }
            $this->dump_component_into_temp($component);
            $component->clear();
            unset($component);
        }

        $this->make_zip_package($version, $langcode);
    }

    /**
     * Moves the generated ZIP package to the output directory for resync
     *
     * @param mlang_version $version
     * @param string $langcode
     */
    protected function push_zip_package(mlang_version $version, $langcode) {

        $source = $this->tempdirroot.'/'.$version->dir.'/'.$langcode.'.zip';
        $target = $this->outputdirroot.'/'.$version->dir.'/'.$langcode.'.zip';

        if (!file_exists($source)) {
            // nothing to do, there was nothing generated for this branch
            return;
        }

        if (!file_exists(dirname($target))) {
            make_writable_directory(dirname($target));
        }

        rename($source, $target);
    }

    /**
     * Updates the information about the pushed ZIP package in packinfo.ser file
     *
     * @param mlang_version $version
     * @param string $langcode
     */
    protected function update_packinfo(mlang_version $version, $langcode) {

        $packinfofile = $this->vardirroot.'/'.$version->dir.'/packinfo.ser';

        if (file_exists($packinfofile)) {
            $packinfo = unserialize(file_get_contents($packinfofile));
        } else {
            $packinfo = array();
            if (!file_exists(dirname($packinfofile))) {
                make_writable_directory(dirname($packinfofile));
            }
        }
        if ($info = $this->generate_packinfo($version, $langcode)) {
            $packinfo[$langcode] = $info;
            file_put_contents($packinfofile, serialize($packinfo));
        }
    }

    /**
     * Generates new packinfo structure about the pushed ZIP package
     *
     * @param mlang_version $version
     * @param string $langcode
     * @return array|null
     */
    protected function generate_packinfo(mlang_version $version, $langcode) {

        $info = array();

        // Name of the language
        if (isset($this->stash['langnames'][$langcode])) {
            $info['langname'] = $this->stash['langnames'][$langcode];
        } else {
            $info['langname'] = $langcode;
        }

        // Parent language
        if (isset($this->stash['langparents'][$langcode])) {
           $info['parent'] = $this->stash['langparents'][$langcode];
        }

        // Pack modification date
        if (!isset($this->stash['componentrecenttimemodified'][$version->code][$langcode])) {
            $this->log('Unable to generate packinfo/modified for '.$version->dir.'/'.$langcode, amos_cli_logger::LEVEL_WARNING);
            return null;
        }
        $packmodified = 0;
        foreach ($this->stash['componentrecenttimemodified'][$version->code][$langcode] as $componentname => $componentmodified) {
            if ($componentmodified > $packmodified) {
                $packmodified = $componentmodified;
            }
        }
        if ($packmodified == 0) {
            $this->job_failure('Unexpected zero value of packinfo/modified for '.$version->dir.'/'.$langcode);
        } else {
            $info['modified'] = $packmodified;
        }

        // Number of strings per component
        if (!isset($this->stash['componentnumofstrings'][$version->code][$langcode])) {
            $this->job_failure('Unable to generate packinfo/numofstrings for '.$version->dir.'/'.$langcode);
        }
        foreach ($this->stash['componentnumofstrings'][$version->code][$langcode] as $componentname => $numberofstrings) {
            $info['numofstrings'][$componentname] = $numberofstrings;
        }

        // Information about the ZIP file
        $zipfile = $this->outputdirroot.'/'.$version->dir.'/'.$langcode.'.zip';
        $info['md5'] = md5_file($zipfile);
        $info['filesize'] = filesize($zipfile);

        return $info;
    }

    /**
     * Update the statistics about the number of translated strings.
     *
     * @param mlang_version $version
     * @param string $langcode
     */
    protected function update_stats(mlang_version $version, string $langcode) {

        if (!isset($this->stash['componentnumofstrings'][$version->code][$langcode])) {
            $this->log('Number of strings not available for '.$version->dir.'/'.$langcode);
            return;
        }

        foreach ($this->stash['componentnumofstrings'][$version->code][$langcode] as $componentname => $numberofstrings) {
            $this->statsman->update_stats($version->code, $langcode, $componentname, $numberofstrings);
        }
    }

    /**
     * Logs the message and exit with success status
     *
     * @param string $message
     */
    protected function job_success($message) {
        $this->log($message);
        exit(0);
    }

    /**
     * Logs the error message and exit with error status
     *
     * @param string $message error message
     * @param int $status
     */
    protected function job_failure($message, $status = 128) {
        $this->log($message, amos_cli_logger::LEVEL_ERROR);
        exit($status);
    }

    /**
     * Dumps the component into PHP file(s) in the temporary area
     *
     * @param mlang_component $component
     */
    protected function dump_component_into_temp(mlang_component $component) {

        if (!$component->has_string()) {
            return;
        }

        // Write the strings and stash some meta-information about the component for later usage
        $file = $this->tempdirroot . '/' . $component->version->dir . '/' . $component->lang . '/' . $component->name . '.php';
        if (!file_exists(dirname($file))) {
            make_writable_directory(dirname($file));
        }
        $component->export_phpfile($file);

        $numberofstrings = $component->get_number_of_strings(true);
        $this->stash['componentnumofstrings'][$component->version->code][$component->lang][$component->name] = $numberofstrings;

        $recentmodified = $component->get_recent_timemodified();
        $this->stash['componentrecenttimemodified'][$component->version->code][$component->lang][$component->name] = $recentmodified;

        if ($component->name === 'langconfig') {
            if ($component->has_string('thislanguage')) {
                $langname = $component->get_string('thislanguage')->text;
                $this->stash['langnames'][$component->lang] = $langname;
            }
            if ($component->has_string('parentlanguage')) {
                $parentlanguage = $component->get_string('parentlanguage')->text;
                $this->stash['langparents'][$component->lang] = $parentlanguage;
            }
        }
    }

    /**
     * Makes a ZIP file of all string files in the temp area
     *
     * @param mlang_version $version
     * @param string $langcode
     */
    protected function make_zip_package(mlang_version $version, $langcode) {

        $files = $this->list_string_files($version, $langcode);

        if (!empty($files)) {
            $packer = get_file_packer('application/zip');
            $zipfile = $this->tempdirroot.'/'.$version->dir.'/'.$langcode.'.zip';
            $packer->archive_to_pathname($files, $zipfile);
        }
    }

    /**
     * Returns the list of full paths to generated string files
     *
     * @param mlang_version $version
     * @param string $langcode
     * @return array of (string)in-zip path => (string)full path
     */
    protected function list_string_files(mlang_version $version, $langcode) {

        $dirpath = $this->tempdirroot.'/'.$version->dir.'/'.$langcode;

        if (!is_dir($dirpath)) {
            return array();
        }

        $files = array();

        foreach (scandir($dirpath) as $filename) {
            if (substr($filename, 0, 1) === '.') {
                continue;
            }
            if (substr($filename, -4) !== '.php') {
                continue;
            }
            $files[$langcode.'/'.$filename] = $dirpath.'/'.$filename;
        }

        return $files;
    }

    /**
     * Rebuilds languages.md5 file for the given version
     *
     * @param mlang_version $version
     * @param array $packinfo
     */
    protected function rebuild_languages_md5(mlang_version $version, array $packinfo) {

        $languagesmd5 = '';
        foreach ($packinfo as $langcode => $info) {
            $zipfile = $this->outputdirroot.'/'.$version->dir.'/'.$langcode.'.zip';
            if (!file_exists($zipfile)) {
                $this->log('File '.$version->dir.'/'.$langcode.'.zip not found in the output directory', amos_cli_logger::LEVEL_WARNING);
                continue;
            }
            $languagesmd5 .= $langcode.','.$info['md5'].','.$info['langname']."\n";
        }
        if (empty($languagesmd5)) {
            $this->log('No lines would be written to '.$version->dir.'/languages.md5', amos_cli_logger::LEVEL_WARNING);
        } else {
            $this->log($version->dir.'/languages.md5', amos_cli_logger::LEVEL_DEBUG);
            file_put_contents($this->outputdirroot.'/'.$version->dir.'/languages.md5', $languagesmd5);
        }
    }

    /**
     * Rebuilds lang-table.html file for the given version
     *
     * @param mlang_version $version
     * @param array $packinfo
     */
    protected function rebuild_index_tablehtml(mlang_version $version, array $packinfo) {
        global $PAGE;

        $indextable = new local_amos_index_tablehtml($version, $packinfo);
        $output = $PAGE->get_renderer('local_amos', null, RENDERER_TARGET_GENERAL);
        $tablehtml = $output->render($indextable);
        $this->log($version->dir.'/lang-table.html', amos_cli_logger::LEVEL_DEBUG);
        file_put_contents($this->outputdirroot.'/'.$version->dir.'/'.'lang-table.html', $tablehtml);
    }
}


/**
 * Helper class to merge one string file into another
 *
 * @copyright 2013 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class amos_merge_string_files {

    /** @var amos_cli_logger */
    protected $logger;

    /**
     * Instantiate the merge helper
     *
     * @param amos_cli_logger $logger
     */
    public function __construct(amos_cli_logger $logger) {
        $this->logger = $logger;
    }

    /**
     * Logs a message
     *
     * @param string $message message to log
     * @param string $level message level
     */
    public function log($message, $level = amos_cli_logger::LEVEL_INFO) {
        $this->logger->log('merge-files', $message, $level);
    }

    /**
     * Returns all strings defined in the given file
     *
     * This is supposed to be used on trusted sources only, so plain require() is ok here.
     *
     * @param string $filename
     * @return array
     */
    public function load_strings_from_file($filename) {
        $string = array();
        require($filename);
        return $string;
    }

    /**
     * Replaces strings declaration in the file with changed ones
     *
     * If false is returned, the $filecontents may or may not be modified. The
     * caller should not consider its value valid.
     *
     * @param string $filecontents the file contents to be modified
     * @param array $fromstrings list of strings and their values to be replaced
     * @param array $tostring list of strings and their new values
     * @return int|false number of replaced strings, or false on error
     */
    public function replace_strings_in_file(&$filecontents, array $fromstrings, array $tostrings) {

        $changes = 0;
        $realchanges = 0;

        foreach ($tostrings as $changeid => $changetext) {
            if (!isset($fromstrings[$changeid])) {
                $this->log('Attempting to merge an orphaned change: "'.$changeid.'"', amos_cli_logger::LEVEL_WARNING);
                continue;
            }
            if ($fromstrings[$changeid] !== $changetext) {
                $changes++;
                $pattern = '/(^\s*\$string\s*\[\s*\''.preg_quote($changeid, '/').'\'\s*\]\s*=\s*)(\'|")'.preg_quote(str_replace("'", "\\'", $fromstrings[$changeid]), '/').'(\\2\s*)(;[^;]*\s*$)/m';
                if (!preg_match($pattern, $filecontents)) {
                    $this->log('String "'.$changeid.'" not found', amos_cli_logger::LEVEL_DEBUG);
                    continue;
                }
                $replacement = '$1'.var_export($changetext, true).'$4';
                $count = 0;
                $filecontents = preg_replace($pattern, $replacement, $filecontents, -1, $count);
                if (!$count) {
                    $this->log('No change done by preg_replace()', amos_cli_logger::LEVEL_DEBUG);
                }
                if (is_null($filecontents)) {
                    $this->log('Error in preg_replace()', amos_cli_logger::LEVEL_DEBUG);
                    return false;
                }
                $realchanges += $count;
            } else {
                $this->log('String "'.$changeid.'" identical, no update needed', amos_cli_logger::LEVEL_DEBUG);
            }
        }

        if ($changes <> $realchanges) {
            $this->log('Expected changes: '.$changes.', real changes: '.$realchanges, amos_cli_logger::LEVEL_WARNING);
        }

        return $realchanges;
    }

    /**
     * Executes the merge process
     *
     * @return int|bool number of modified strings or false on error
     */
    public function merge_changes() {

        if (!$this->is_ready()) {
            $this->log('Helper not ready to merge changes', amos_cli_logger::LEVEL_ERROR);
            return false;
        }

        $this->load_main_file();
        $this->load_changes_file();

        $merged = $this->replace_main_file_strings();

        if ($merged) {
            $this->rewrite_main_file();
        } else if ($merged === 0) {
            $this->log('No strings changed', amos_cli_logger::LEVEL_DEBUG);
        } else {
            $this->log('Error while merging strings', amos_cli_logger::LEVEL_ERROR);
        }

        return $merged;
    }
}
