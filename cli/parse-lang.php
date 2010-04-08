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
 * AMOS script to parse the history of all standard translation packages
 *
 * This is supposed to run manually to import the history in a one-shot
 *
 * @package   local_amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

set_time_limit(0);
$starttime = microtime();

// this cron script might be considered to be a CLI script even when accessed over HTTP,
// we do not want HTML in output and there is no real session ;-)
define('CLI_SCRIPT', true);

// Do not set moodle cookie because we do not need it here, it is better to emulate session
define('NO_MOODLE_COOKIES', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/local/amos/cli/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');

// send mime type and encoding
if (check_browser_version('MSIE')) {
    //ugly IE hack to work around downloading instead of viewing
    @header('Content-Type: text/html; charset=utf-8');
    echo "<xmp>"; //<pre> is not good enough for us here
} else {
    //send proper plaintext header
    @header('Content-Type: text/plain; charset=utf-8');
}

// no more headers and buffers
while(@ob_end_flush());

// increase memory limit (PHP 5.2 does different calculation, we need more memory now)
@raise_memory_limit('128M');

$tmp = make_upload_directory('amos/temp', false);
$var = make_upload_directory('amos/var', false);
$mem = memory_get_usage();
$eng = array();

// the following commits contains a syntax typo and they can't be included for processing. They are skipped
$MLANG_BROKEN_CHECKOUTS = array(
    '6ec9481c57031d35ebb5ed19807791264f522d9c_cs_utf8_langconfig.php',
    '64f9179caba6584f6fcee8e7ed957473301c26e7_cs_utf8_auth.php',
    '7d35c4bd7ed781fccf1334a0662a6cc2a9c3c627_it_utf8_moodle.php',
    '3ea6d49f6d2e9785deff089193c878c4cf46a0dc_fr_utf8_enrol_authorize.php',
    '1852dc59bd96d6a5dfa2f836d3b24a53e317e2ab_fr_utf8_admin.php',
    'd76860354ecf3854e0d36cf82aaf23e54064cddc_fr_utf8_admin.php',
    '1a27c7774de7dc6d2ce5d650eae972344159bdc2_ja_utf8_admin.php',
    '7fd8390819eae456caa954d1fdb00ba84b3c428b_zh_tw_utf8_dialogues.php',
    '519dc243ca72b549fdd4a9a4bf27c4f3cea88ec2_mn_utf8_quiz_analysis.php',
    '8bec8cb0b6edc835707c9eb4af61c0b37a31fed8_mn_utf8_quiz_analysis.php',
    '4e573ae1b379d0a757104fb121515ce05427b9bf_hu_utf8_enrol_mnet.php',
    '4e573ae1b379d0a757104fb121515ce05427b9bf_hu_utf8_group.php',
    'a669da1017199cd19074b673675e3bba20919595_fr_utf8_mnet.php',
    '2c4cdacf4a7976cdc1958da949bf28b097e42e88_fr_utf8_mnet.php',
    '4eb0ae6e276ce2449d3ef3107b4843e7e351ad94_fr_utf8_admin.php',
    '09f1f1f81dab4e3847876ad5e4803c74b69a493b_zh_tw_utf8_dialogues.php',
    'ad2c7d53d9423499edade1cbef08598461d3dbf3_si_utf8_langconfig.php',
    '3ccc36a2ce7d4359d88a66392cb15ba4843a137c_pt_utf8_editor.php',
    'c0a238b29dd9014b6caef14ee79a0d510d364c44_he_utf8_tag.php',
    '7cf367a33e97e8046cf579282e89956ff18da72f_fr_utf8_error.php',
    '815d89e7670b47bbaba17bafe2025895e310f81b_fr_utf8_block_search.php',
    'd705fde9f580216833552e372a432ad36ff77413_fr_utf8_debug.php',
    '1f18133335b17348abeb4472904c2a7765ae1009_fr_utf8_gradeimport_xml.php',
    '8af971ad2265b3efc901985a6f204aa4ba4408a9_fr_utf8_error.php',
    '77ef858496eda8c000327a7d87802d13643b0aa2_so_utf8_appointment.php',
    'd090367ead604f2a65e2b22abbec6f19c1915607_es_utf8_question.php',
    'abe4d2a450411820e7eda4d868ff01923597d5bd_eu_utf8_error.php',
    '079dabb0918bb0e5d35b15886a4b84ed75ee1c7d_tr_utf8_error.php',
    'a43d1b0eee00cd8d9f1d485916b24dfb2e85d3cc_km_utf8_auth.php',
    'a43d1b0eee00cd8d9f1d485916b24dfb2e85d3cc_km_utf8_enrol_ldap.php',
    'a43d1b0eee00cd8d9f1d485916b24dfb2e85d3cc_km_utf8_forum.php',
    'a43d1b0eee00cd8d9f1d485916b24dfb2e85d3cc_km_utf8_group.php',
    'a43d1b0eee00cd8d9f1d485916b24dfb2e85d3cc_km_utf8_hotpot.php',
    'a43d1b0eee00cd8d9f1d485916b24dfb2e85d3cc_km_utf8_question.php',
    '598095a80d24d982efa2eb5446ba2046ff5a4f80_dv_utf8_moodlelib.php',
    'dcb292801654b0fd0bfb76ff0bf597f49f133cc9_da_utf8_moodle.php',
    'cfec4b2b359a67abd50eca74fd37f12a54d8ea42_nl_utf8_grades.php',
);

chdir(AMOS_REPO_LANGS);

// find the root commit
$gitout = array();
$gitstatus = 0;
$gitcmd = "git rev-list HEAD | tail -1";
echo "RUN {$gitcmd}\n";
exec($gitcmd, $gitout, $gitstatus);
if ($gitstatus <> 0) {
    die('ERROR');
}
$rootcommit = $gitout[0];

// find all commits that touched the repository since the last save point
$startatlock = "{$var}/LANG.startat";
$startat = '';
if (file_exists($startatlock)) {
    echo "STARTAT {$startatlock}\n";
    $startat = trim(file_get_contents($startatlock));
    if (!empty($startat) and ($startat != $rootcommit)) {
        $startat = '^' . $startat . '^';
    }
}
$gitout = array();
$gitstatus = 0;
$gitcmd = "git whatchanged --reverse --format=format:COMMIT:%H origin/cvshead {$startat} " . AMOS_REPO_LANGS . '/cs_utf8'; // XXX
echo "RUN {$gitcmd}\n";
exec($gitcmd, $gitout, $gitstatus);

if ($gitstatus <> 0) {
    // error occured
    die('ERROR');
}

$commithash = '';
foreach ($gitout as $line) {
    $line = trim($line);
    if (empty($line)) {
        continue;
    }
    if (substr($line, 0, 7) == 'COMMIT:') {
        // remember the processed commithash
        if (!empty($commithash)) {
            file_put_contents($startatlock, $commithash);
        }
        $commithash = substr($line, 7);
        continue;
    }

    $parts = explode("\t", $line);
    $changetype = substr($parts[0], -1);    // A (added new file), M (modified), D (deleted)
    $file = $parts[1];
    if (substr($file, -4) !== '.php') {
        continue;
    }
    $memprev = $mem;
    $mem = memory_get_usage();
    $memdiff = $memprev < $mem ? '+' : '-';
    $memdiff = $memdiff . abs($mem - $memprev);
    echo "{$commithash} {$changetype} {$file} [{$mem} {$memdiff}]\n";

    if ($commithash == '4d0f400a58cbbe2bfd30c5f62aa59bc9d043a699') {
        // wrong UTF-8 encoding in all commits, fixed immediately
        echo "SKIP\n";
        continue;
    }
    if ($file == 'sr_lt_utf8/~$admin.php') {
        // wrong commit 3c270b5d9d6ae860e61b678a36f3490a7568f6ab
        echo "SKIP\n";
        continue;
    }

    $parts = explode('/', $file);
    $langcode = $parts[0];  // eg. 'en_us_utf8'
    $langcode = substr($langcode, 0, -5);   // without _utf8 suffix

    if ($langcode == 'en') {
        // for historical reasons, English strings are in this repo history as well
        // we can not process them here as they would break the data
        continue;
    }

    // get some additional information of the commit
    $format = implode('%n', array('%an', '%ae', '%at', '%s')); // name, email, timestamp, subject
    $commitinfo = array();
    if ($commithash == $rootcommit) {
        $gitcmd = "git log --format={$format} {$commithash}";
    } else {
        $gitcmd = "git log --format={$format} {$commithash} ^{$commithash}^";
    }
    exec($gitcmd, $commitinfo);
    $committer      = $commitinfo[0];
    $committeremail = $commitinfo[1];
    $timemodified   = $commitinfo[2];
    $commitmsg      = iconv('UTF-8', 'UTF-8//IGNORE', $commitinfo[3]);

    if ($changetype == 'D') {
        // whole file removal - xxx what can be done? :-/
        continue;
    }

    // dump the given revision of the file to a temporary area
    $checkout = $commithash . '_' . str_replace('/', '_', $file);
    if (in_array($checkout, $MLANG_BROKEN_CHECKOUTS)) {
        echo "BROKEN $checkout\n";
        continue;
    }
    $checkout = $tmp . '/' . $checkout;
    exec("git show {$commithash}:{$file} > {$checkout}");

    // push the string on all branches where the English original is currently (or has ever been) defined
    // note that all English strings history must already be registered in AMOS repository
    foreach (array('MOODLE_20_STABLE', 'MOODLE_19_STABLE', 'MOODLE_18_STABLE', 'MOODLE_17_STABLE', 'MOODLE_16_STABLE') as $branch) {
        $version = mlang_version::by_branch($branch);
        // get the translated strings from PHP file
        $component = mlang_component::from_phpfile($checkout, $langcode, $version, $timemodified, mlang_component::name_from_filename($file));
        // get the most recent snapshot of English strings including those deleted
        // beware - we are caching the English snapshots so do not modify English strings while migrating langs
        // TODO those deleted from English are are not marked as deleted in the translation yet
        if (!isset($eng[$branch])) {
            $eng[$branch] = array();
        }
        if (!isset($eng[$branch][$component->name])) {
            $eng[$branch][$component->name] = mlang_component::from_snapshot($component->name, 'en', $version, null, true);
        }
        // keep just those defined in English on that branch - this is where we are replaying branching of lang packs
        $component->intersect($eng[$branch][$component->name]);
        // and let us commit added/modified strings
        $stage = new mlang_stage();
        $stage->add($component);
        $stage->rebase($timemodified, true, $timemodified);
        try {
            $stage->commit($commitmsg, array(
                    'source' => 'git',
                    'userinfo' => $committer . ' <' . $committeremail . '>',
                    'commithash' => $commithash
                ), true);
        } catch (dml_write_exception $e) {
            echo "FAILED COMMIT $checkout\n";
        }
        $component->clear();
        unset($component);
    }
    unlink($checkout);
}
echo "DONE\n";
