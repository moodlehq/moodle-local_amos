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
 * Imports English strings for a given component from PHP file
 *
 * This can be used to manually import contributed plugins, for example. Note there is an external function for this
 * feature, too.
 *
 * @package     local_amos
 * @copyright   2011 David Mudrak <david@moodle.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/amos/cli/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognised) = cli_get_params([
    'lang' => 'en',
    'versioncode' => null,
    'timemodified' => null,
    'name' => null,
    'format' => 2,
    'message' => '',
    'userinfo' => 'David Mudrak <david@moodle.com>',
    'commithash' => null,
    'yes' => false,
    'help' => false
], [
    'h' => 'help',
    'y' => 'yes',
]);

$usage = "
Imports strings from a file into the AMOS repository.

Usage:
    php import-strings.php --message='Commit message' [--other-options] /path/to/file.php

Options:
    --message       Commit message
    --lang          Language code, defaults to 'en',
    --versioncode   Code of the branch to commit to, defaults to most recent one
    --timemodified  Timestamp of the commit, defaults to the file last modification time
    --name          Name of the component, defaults to the filename
    --format        Format of the file, defaults to 2 (Moodle 2.0+)
    --userinfo      Committer information, defaults to 'David Mudrak <david@moodle.com>'
    --commithash    Allows to specify the git commit hash
    --yes | -y      It won't ask to continue
    --help | -h     Show this usage

The file is directly included into the PHP processor. Make sure to review the file
for a malicious contents before you import it via this script.
";

if ($options['help'] || empty($options['message']) || empty($unrecognised)) {
    echo $usage . PHP_EOL;
    exit(1);
}

$filepath = $unrecognised[0];
if (!is_readable($filepath)) {
    echo 'File "'.$filepath.'" not readable' . PHP_EOL;
    echo $usage . PHP_EOL;
    exit(2);
}

if ($options['versioncode']) {
    $version = mlang_version::by_code($options['versioncode']);
} else {
    $version = mlang_version::latest_version();
}

$component = mlang_component::from_phpfile($filepath, $options['lang'], $version,
    $options['timemodified'], $options['name'], (int)$options['format']);

fputs(STDOUT, "{$component->name} {$component->version->label} {$component->lang}" . PHP_EOL);

$stage = new mlang_stage();
$stage->add($component);
$stage->rebase(null, true, $options['timemodified']);

if (!$stage->has_component()) {
    echo 'No strings found (after rebase)' . PHP_EOL;
    exit(4);
}

foreach ($stage as $component) {
    foreach ($component as $string) {
        if ($string->deleted) {
            $sign = '-';
        } else {
            $sign = '+';
        }
        echo $sign . ' ' . $string->id . PHP_EOL;
    }
}

echo PHP_EOL;
if (!$options['yes']) {
    $continue = cli_input('Continue? [y/n]', 'n', array('y', 'n'));
    if ($continue !== 'y') {
        echo 'Import aborted' . PHP_EOL;
        exit(5);
    }
}

$meta = array('source' => 'import', 'userinfo' => $options['userinfo']);
if ($options['commithash']) {
    $meta['commithash'] = $commithash;
}

$stage->commit($options['message'], $meta, true);
