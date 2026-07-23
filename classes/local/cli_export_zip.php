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

namespace local_amos\local;

/**
 * Helper class for export-zip job
 *
 * @package   local_amos
 * @copyright 2012 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cli_export_zip {
    /** @var cli_logger */
    protected $logger;

    /** @var string full path to the root of output folders (those to be rsynced) */
    protected $outputdirroot;

    /** @var string full path to the root of temp folders */
    protected $tempdirroot;

    /** @var int the timestamp of the last execution of this job */
    protected $last;

    /** @var \local_amos_stats_manager instance used for updating translation stats while exporting the ZIPs */
    protected $statsman;

    /** @var int|null min version code to process */
    protected $minver;

    /** @var int|null max version code to process */
    protected $maxver;

    /** @var string[]|null explicit list of languages to process */
    protected $langcodes;

    /**
     * Instantiate the exporter job
     *
     * @param cli_logger $logger
     * @param \local_amos_stats_manager $statsman
     */
    public function __construct(cli_logger $logger, \local_amos_stats_manager $statsman = null) {
        global $CFG;

        $this->logger = $logger;
        $this->outputdirroot = $CFG->dataroot . '/amos/export-zip';
        $this->tempdirroot = $CFG->dataroot . '/amos/temp/export-zip';

        if (!$statsman) {
            $this->statsman = new \local_amos_stats_manager();
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
    public function log($message, $level = cli_logger::LEVEL_INFO) {
        $this->logger->log('export-zip', $message, $level);
    }

    /**
     * Initialize this exporter job instance
     *
     * @param int|null $minver
     * @param int|null $maxver
     * @param string[]|null $langcodes
     */
    public function init(?int $minver = null, ?int $maxver = null, ?array $langcodes = null) {

        $this->log('== Initializing the ZIP language packs exporter ==');
        $this->init_versions_range($minver, $maxver);

        if (!empty($langcodes)) {
            if (empty($this->minver)) {
                $this->job_failure('Missing minver specified for explicitly selected language packs');
            }
            $this->langcodes = $langcodes;
        }

        $this->init_temp_folders();
    }

    /**
     * Regenerates ZIP packages in output folders if needed
     */
    public function rebuild_zip_packages() {

        if (empty($this->langcodes)) {
            $this->log('== Rebuilding ZIPs of outdated language packs ==');
            $this->init_timestamp_last();
            $langpacks = $this->detect_recently_modified_languages();
        } else {
            $this->log('== Rebuilding ZIPs of specified language packs ==');
            $langpacks = array_combine($this->langcodes, array_fill(0, count($this->langcodes), $this->minver));
        }

        foreach ($this->get_versions() as $version) {
            foreach ($langpacks as $langcode => $since) {
                if ($version->code < $since) {
                    continue;
                }
                $this->rebuild_zip_package($version, $langcode);
                $this->push_zip_package($version, $langcode);
            }
        }

        $this->statsman->reset_caches();
    }

    /**
     * Regenerates languages.md5 and lang-table.html files in output folders
     */
    public function rebuild_output_folders() {
        $this->log('== Rebuilding languages.md5 and lang-table.html files ==');

        foreach (new \DirectoryIterator($this->outputdirroot) as $outputdir) {
            if (!$outputdir->isDir()) {
                continue;
            }

            if ($outputdir->isDot()) {
                continue;
            }

            $version = amos_version::by_dir($outputdir->getFilename());

            $this->rebuild_languages_md5($version);
            $this->rebuild_download_page($version);
        }
    }

    /**
     * Returns the list of mlang_versions that we should process
     *
     * @return array of {@see amos_version} objects indexed by the version code
     */
    protected function get_versions() {

        $minver = max(20, $this->minver ?? 20);
        $maxver = $this->maxver;

        $versions = amos_version::list_range($minver, $maxver);
        krsort($versions);

        return $versions;
    }

    /**
     * Set optional limits for processed versions.
     *
     * @param int|null $minver
     * @param int|null $maxver
     */
    protected function init_versions_range(?int $minver, ?int $maxver) {

        if ($this->minver = $minver) {
            $this->log(
                'Processing only versions starting from ' . amos_version::by_code($minver)->label,
                cli_logger::LEVEL_DEBUG
            );
        }

        if ($this->maxver = $maxver) {
            $this->log(
                'Processing only versions up to ' . amos_version::by_code($maxver)->label,
                cli_logger::LEVEL_DEBUG
            );
        }
    }

    /**
     * Loads the last execution timestamp
     */
    protected function init_timestamp_last() {
        $lastexportzip = get_config('local_amos', 'lastexportzip');
        if (empty($lastexportzip)) {
            $lastexportzip = 0;
            $this->log('Previous execution timestamp (lastexportzip) not configured.', cli_logger::LEVEL_DEBUG);
        } else {
            $this->log(
                sprintf('Previous execution timestamp found: %d (%s).', $lastexportzip, date('Y-m-d H:i:s', $lastexportzip)),
                cli_logger::LEVEL_DEBUG
            );
        }
        $this->last = $lastexportzip;
    }

    /**
     * Prepares the temporary folders where new ZIPs will be generated
     */
    protected function init_temp_folders() {
        $this->log('Preparing temporary folders', cli_logger::LEVEL_DEBUG);
        fulldelete($this->tempdirroot);
        foreach ($this->get_versions() as $version) {
            make_writable_directory($this->tempdirroot . '/' . $version->dir);
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

        $this->log('Looking for recent commits since ' . date('Y-m-d H:i:s', $this->last), cli_logger::LEVEL_DEBUG);

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
                 WHERE since IS NOT NULL
              ORDER BY lang";

        $result = $DB->get_records_sql_menu($sql, $params);

        foreach ($this->consume_pending_zip_rebuilds() as $lang => $since) {
            $this->log(
                sprintf('Pending forced rebuild detected for language %s affecting branches since %d', $lang, $since),
                cli_logger::LEVEL_DEBUG
            );

            if (!isset($result[$lang]) || $since < $result[$lang]) {
                $result[$lang] = $since;
            }
        }

        ksort($result);

        foreach ($result as $lang => $since) {
            $this->log(
                sprintf('Recent change detected in language %s affecting branches since %d', $lang, $since),
                cli_logger::LEVEL_DEBUG
            );
        }

        if (empty($result)) {
            $this->log('No recent changes found, nothing to rebuild', cli_logger::LEVEL_DEBUG);
        }

        return $result;
    }

    /**
     * Reads and clears the list of languages that need a forced ZIP rebuild
     *
     * This covers cases such as a translation record being permanently deleted directly from the
     * database, bypassing the normal commit mechanism that {@see detect_recently_modified_languages()}
     * relies on.
     *
     * @return array of (string) lang => (int) since version code
     */
    protected function consume_pending_zip_rebuilds() {

        $raw = get_config('local_amos', 'pendingzipsrebuild');

        if (empty($raw)) {
            return [];
        }

        unset_config('pendingzipsrebuild', 'local_amos');

        $pending = json_decode($raw, true);

        if (!is_array($pending)) {
            return [];
        }

        return $pending;
    }

    /**
     * Dumps all component strings into PHP files and makes ZIP archive of them
     *
     * @param amos_version $version
     * @param string $langcode
     */
    protected function rebuild_zip_package(amos_version $version, $langcode) {
        $this->log('Rebuilding temp/' . $version->dir . '/' . $langcode . '.zip', cli_logger::LEVEL_DEBUG);

        foreach (amos_tools::list_components() as $componentname => $ignored) {
            $component = amos_component::from_snapshot($componentname, $langcode, $version);
            if ($langcode !== 'en') {
                $english = amos_component::from_snapshot($componentname, 'en', $version);
                $component->intersect($english);
                $numberofenglishstrings = $english->get_number_of_strings(true);
                $english->clear();
            }
            $this->dump_component_into_temp($component);

            if ($component->has_string()) {
                if ($langcode !== 'en' && $component->name === 'langconfig') {
                    $numberofstrings = $numberofenglishstrings;
                } else {
                    $numberofstrings = $component->get_number_of_strings(true);
                }

                $this->statsman->add_to_buffer($component->version->code, $component->lang, $component->name, $numberofstrings);
            }

            $component->clear();
            unset($component);
        }

        $this->statsman->write_buffer();
        $this->make_zip_package($version, $langcode);
    }

    /**
     * Moves the generated ZIP package to the output directory for resync
     *
     * @param amos_version $version
     * @param string $langcode
     */
    protected function push_zip_package(amos_version $version, $langcode) {

        $source = $this->tempdirroot . '/' . $version->dir . '/' . $langcode . '.zip';
        $target = $this->outputdirroot . '/' . $version->dir . '/' . $langcode . '.zip';

        if (!file_exists($source)) {
            // Nothing to do, there was nothing generated for this branch.
            return;
        }

        if (!file_exists(dirname($target))) {
            make_writable_directory(dirname($target));
        }

        rename($source, $target);
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
        $this->log($message, cli_logger::LEVEL_ERROR);
        exit($status);
    }

    /**
     * Dumps the component into PHP file(s) in the temporary area
     *
     * @param amos_component $component
     */
    protected function dump_component_into_temp(amos_component $component) {

        if (!$component->has_string()) {
            return;
        }

        $file = $this->tempdirroot . '/' . $component->version->dir . '/' . $component->lang . '/' . $component->name . '.php';

        if (!file_exists(dirname($file))) {
            make_writable_directory(dirname($file));
        }

        $component->export_phpfile($file);
    }

    /**
     * Makes a ZIP file of all string files in the temp area
     *
     * @param amos_version $version
     * @param string $langcode
     */
    protected function make_zip_package(amos_version $version, $langcode) {

        $files = $this->list_string_files($version, $langcode);

        if (!empty($files)) {
            $packer = get_file_packer('application/zip');
            $zipfile = $this->tempdirroot . '/' . $version->dir . '/' . $langcode . '.zip';
            $packer->archive_to_pathname($files, $zipfile);
        }
    }

    /**
     * Returns the list of full paths to generated string files
     *
     * @param amos_version $version
     * @param string $langcode
     * @return array of (string)in-zip path => (string)full path
     */
    protected function list_string_files(amos_version $version, $langcode) {

        $dirpath = $this->tempdirroot . '/' . $version->dir . '/' . $langcode;

        if (!is_dir($dirpath)) {
            return [];
        }

        $files = [];

        foreach (scandir($dirpath) as $filename) {
            if (substr($filename, 0, 1) === '.') {
                continue;
            }
            if (substr($filename, -4) !== '.php') {
                continue;
            }
            $files[$langcode . '/' . $filename] = $dirpath . '/' . $filename;
        }

        return $files;
    }

    /**
     * Rebuilds languages.md5 file for the given version
     *
     * @param amos_version $version
     */
    protected function rebuild_languages_md5(amos_version $version) {

        $langnames = amos_tools::list_languages(true, true, false, true);
        $languagesmd5lines = [];

        foreach (new \DirectoryIterator($this->outputdirroot . '/' . $version->dir) as $zipfile) {
            if (!$zipfile->isFile()) {
                continue;
            }

            if (substr($zipfile->getFilename(), -4) !== '.zip') {
                continue;
            }

            $langcode = substr($zipfile->getFilename(), 0, -4);
            $md5 = md5_file($this->outputdirroot . '/' . $version->dir . '/' . $langcode . '.zip');
            $languagesmd5lines[] = [
                'code' => $langcode,
                'md5' => $md5,
                'name' => $langnames[$langcode],
            ];
        }

        \core_collator::asort_array_of_arrays_by_key($languagesmd5lines, 'name');

        $languagesmd5 = '';
        foreach ($languagesmd5lines as $line) {
            $languagesmd5 .= $line['code'] . ',' . $line['md5'] . ',' . $line['name'] . "\n";
        }
        file_put_contents($this->outputdirroot . '/' . $version->dir . '/languages.md5', $languagesmd5);
        $this->log($version->dir . '/languages.md5', cli_logger::LEVEL_DEBUG);
    }

    /**
     * Rebuilds lang-table.html file for the given version
     *
     * @param amos_version $version
     */
    protected function rebuild_download_page(amos_version $version) {
        global $OUTPUT;

        $langpacks = $this->statsman->get_language_pack_download_page_data($version->code);

        foreach ($langpacks as &$langpack) {
            $zipfile = $this->outputdirroot . '/' . $version->dir . '/' . $langpack['langcode'] . '.zip';
            if (file_exists($zipfile)) {
                $langpack['haszip'] = true;
                $langpack['downloadurl'] = '/download.php/langpack/' . $version->dir . '/' . $langpack['langcode'] . '.zip';
                $langpack['filesize'] = display_size(filesize($zipfile));
            }
        }

        $now = time();
        $tz = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $generatedlabel = date('Y-m-d H:i e', $now);
        $generateddatetime = date('c', $now);
        date_default_timezone_set($tz);

        $html = $OUTPUT->render_from_template('local_amos/downloadpage', [
            'langpacks' => $langpacks,
            'generatedlabel' => $generatedlabel,
            'generateddatetime' => $generateddatetime,
        ]);

        file_put_contents($this->outputdirroot . '/' . $version->dir . '/' . 'lang-table.html', $html);
        $this->log($version->dir . '/lang-table.html', cli_logger::LEVEL_DEBUG);
    }
}
