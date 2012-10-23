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
 * Import strings from uploaded file and stage them
 *
 * @package    local
 * @subpackage amos
 * @copyright  2010 David Mudrak <david.mudrak@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/mlanglib.php');
require_once(dirname(__FILE__).'/mlangparser.php');
require_once(dirname(__FILE__).'/importfile_form.php');

require_login(SITEID, false);
require_capability('local/amos:importfile', get_system_context());

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/amos/importfile.php');
navigation_node::override_active_url(new moodle_url('/local/amos/stage.php'));
$PAGE->set_title('AMOS ' . get_string('importfile', 'local_amos'));
$PAGE->set_heading('AMOS ' . get_string('importfile', 'local_amos'));

$importform = new local_amos_importfile_form(null, local_amos_importfile_options());

if (($data = $importform->get_data()) and has_capability('local/amos:stage', get_system_context())) {
    $tmpdir = $CFG->dataroot . '/amos/temp/import-uploads/' . $USER->id;
    check_dir_exists($tmpdir);
    $filenameorig = basename($importform->get_new_filename('importfile'));
    $filename = $filenameorig . '-' . md5(time() . '-' . $USER->id . '-'. random_string(20));
    $pathname = $tmpdir . '/' . $filename;

    if ($importform->save_file('importfile', $pathname)) {

        // Prepare the list of files to import.
        $stringfiles = array();

        if (strtolower(substr($filenameorig, -4)) === '.zip') {
            $tmpzipdir = $pathname . '-content';
            check_dir_exists($tmpzipdir);
            $fp = get_file_packer('application/zip');
            $zipcontents = $fp->extract_to_pathname($pathname, $tmpzipdir);
            if (!$zipcontents) {
                notice(get_string('novalidzip', 'local_amos'), new moodle_url('/local/amos/stage.php'));
                @remove_dir($tmpzipdir);
            } else {
                foreach ($zipcontents as $zipfilename => $zipfilestatus) {
                    // We want PHP files in the root of the ZIP only.
                    if ($zipfilestatus === true and basename($zipfilename) === $zipfilename and strtolower(substr($zipfilename, -4)) === '.php') {
                        $stringfiles[$zipfilename] = $tmpzipdir . '/' . $zipfilename;
                    }
                }
            }

        } else if (strtolower(substr($filenameorig, -4)) === '.php') {
            $stringfiles = array($filenameorig => $pathname);
        }

        if (empty($stringfiles)) {
            notice(get_string('nostringtoimport', 'local_amos'), new moodle_url('/local/amos/stage.php'));

        } else {
            $stage = mlang_persistent_stage::instance_for_user($USER->id, sesskey());
            $version = mlang_version::by_code($data->version);
            $parser = mlang_parser_factory::get_parser('php');

            foreach ($stringfiles as $filenameorig => $pathname) {
                $name = mlang_component::name_from_filename($filenameorig);
                $component = new mlang_component($name, $data->language, $version);

                try {
                    $parser->parse(file_get_contents($pathname), $component);

                } catch (mlang_parser_exception $e) {
                    notice($e->getMessage(), new moodle_url('/local/amos/stage.php'));
                }

                $encomponent = mlang_component::from_snapshot($component->name, 'en', $version);
                $component->intersect($encomponent);

                if ($component->has_string()) {
                    $stage->add($component, true);
                    $component->clear();
                    $stage->store();
                }
            }
            mlang_stash::autosave($stage);
        }

        if (!empty($tmpzipdir)) {
            @remove_dir($tmpzipdir);
        }

    } else {
        notice(get_string('nofiletoimport', 'local_amos'), new moodle_url('/local/amos/stage.php'));
    }
}

if (!isset($stage) or !$stage->has_component()) {
    notice(get_string('nostringtoimport', 'local_amos'), new moodle_url('/local/amos/stage.php'));
}

redirect(new moodle_url('/local/amos/stage.php'));
