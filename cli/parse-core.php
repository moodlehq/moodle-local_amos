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
 * AMOS script to parse English strings in the core
 *
 * @package   local_amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/amos/cli/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');
require_once($CFG->dirroot . '/local/amos/locallib.php');
require_once($CFG->dirroot . '/local/amos/renderer.php');

raise_memory_limit(MEMORY_HUGE);
set_time_limit(0);
$starttime = microtime();

/**
 * This is a hacky way how to populate a forum at lang.moodle.org with commits into the core
 *
 * @param mlang_stage $stage
 * @param string $commitmsg
 * @param string $committer
 * @param string $committeremail
 * @param string $commithash
 * @param string $fullcommitmsg
 * @return void
 */
function amos_core_commit_notify(mlang_stage $stage, $commitmsg, $committer, $committeremail, $commithash, $fullcommitmsg) {
    global $CFG; $DB;
    require_once($CFG->dirroot.'/mod/forum/lib.php');

    if ($CFG->wwwroot !== 'https://lang.moodle.org' && $CFG->wwwroot !== 'https://lang.next.moodle.org') {
        return;
    }

    if (!$stage->has_component()) {
        return;
    }

    // Course 'Translating Moodle', forum 'Notification of string changes', user 'AMOS bot'.
    $courseid = 2;
    $cmid = 7;
    $userid = 2;

    $cm = get_coursemodule_from_id('forum', $cmid);

    $discussion = (object) [];
    $discussion->course = $courseid;
    $discussion->forum = $cm->instance;
    $discussion->name = substr(s('[AMOS commit] ' . $commitmsg), 0, 255);
    $discussion->message = 'Author: ' . $committer . "\n";
    $discussion->message .= $fullcommitmsg . "\n\n";
    $discussion->message .= 'http://git.moodle.org/gw?p=moodle.git;a=commit;h='.$commithash . "\n";
    $discussion->message .= 'http://github.com/moodle/moodle/commit/'.$commithash . "\n\n";

    $standardplugins = \local_amos\local\util::standard_components_tree();

    foreach ($stage as $component) {
        foreach ($component as $string) {
            if ($string->deleted) {
                $sign = '-  ';
            } else {
                $sign = '+  ';
            }

            if (isset($standardplugins[$component->version->code][$component->name])) {
                $name = $standardplugins[$component->version->code][$component->name];
            } else {
                $name = $component->name;
            }

            $discussion->message .= $sign . $component->version->dir . ' en [' . $string->id . ',' . $name . "]\n";
        }
    }

    $discussion->message = s($discussion->message);
    $discussion->messageformat = FORMAT_MOODLE;
    $discussion->messagetrust = 0;
    $discussion->attachments = null;
    $discussion->mailnow = 1;

    $message = null;
    forum_add_discussion($discussion, null, $message, $userid);
}

/**
 * This is a helper function that just contains a block of code that needs to be
 * executed from two different places in this script. Consider it more as C macro
 * than a real function.
 */
function amos_parse_core_commit() {
    global $DB;
    global $stage, $realmodified, $timemodified, $commitmsg, $committer, $committeremail, $commithash,
           $version, $fullcommitmsg, $startatlock, $affected;

    // If there is nothing to do, just store the last hash processed and return.
    // This is typical for git commits that do not touch any lang file.
    if (!$stage->has_component()) {
        file_put_contents($startatlock, $commithash);
        return;
    }

    if (empty($commithash)) {
        throw new coding_exception('When storing strings from a git commit, the hash must be provided');
    }

    // This is a hacky check to make sure that the git commit has not been processed already.
    // It helps to prevent situations when a commit is reverted and AMOS is adding and removing strings in sort of loop.
    if ($DB->record_exists('amos_commits', ['source' => 'git', 'commithash' => $commithash])) {
        $stage->clear();
        fputs(STDOUT, "SKIP $commithash has already been processed before\n");
        file_put_contents($startatlock, $commithash);
        return;
    }

    // Rebase the stage so that it contains just real modifications of strings.
    $stage->rebase($timemodified, true, $timemodified);

    // Make sure that the strings to be removed are really affected by the commit.
    foreach ($stage as $component) {
        foreach ($component as $string) {
            if (!isset($affected[$component->name][$string->id])) {
                $component->unlink_string($string->id);
            }
        }
    }

    // Rebase again to get rid of eventually empty components that were left after removing unaffected strings.
    $stage->rebase($timemodified, false);

    // If there is nothing to do now, just store the last has processed and return.
    // It is typical for git commits that touch some lang file but they do not actually
    // modify any string. Note that we do not execute AMOScript in this situation.
    if (!$stage->has_component()) {
        fputs(STDOUT, "NOOP $commithash does not introduce any string change\n");
        file_put_contents($startatlock, $commithash);
        return;
    }

    // OK so it seems we finally have something to do here! Let's spam the world first.
    amos_core_commit_notify($stage, $commitmsg, $committer, $committeremail, $commithash, $fullcommitmsg);
    // Actually commit the changes.
    $stage->commit($commitmsg, [
        'source' => 'git',
        'userinfo' => $committer . ' <' . $committeremail . '>',
        'commithash' => $commithash
    ], true, $timemodified);

    // Execute AMOS script if the commit message contains some.
    if ($version->code >= 20) {
        $instructions = mlang_tools::extract_script_from_text($fullcommitmsg);
        if (!empty($instructions)) {
            foreach ($instructions as $instruction) {
                fputs(STDOUT, "EXEC $instruction\n");
                $changes = mlang_tools::execute($instruction, $version, $timemodified);
                if ($changes instanceof mlang_stage) {
                    $changes->rebase($timemodified);
                    $changes->commit($commitmsg, [
                        'source' => 'commitscript',
                        'userinfo' => $committer . ' <' . $committeremail . '>',
                        'commithash' => $commithash
                    ], true, $timemodified);
                } else if ($changes < 0) {
                    fputs(STDERR, "EXEC STATUS $changes\n");
                }
                unset($changes);
            }
        }
    }

    // Remember the processed commithash.
    file_put_contents($startatlock, $commithash);
}

$tmp = make_upload_directory('amos/temp');
$var = make_upload_directory('amos/var');
$mem = memory_get_usage();

// Following commits contains a syntax typo and they can't be included for processing.
$brokencheckouts = array(
    '52425959755ff22c733bc39b7580166f848e2e2a_lang_en_utf8_enrol_authorize.php',
    '46702071623f161c4e06ee9bbed7fbbd48356267_lang_en_utf8_enrol_authorize.php',
    '1ec0ef254c869f6bd020edafdb78a80d4126ba79_lang_en_utf8_role.php',
    '8871caf0ac9735b67200a6bdcae3477701077e63_lang_en_utf8_role.php',
    '50d30259479d27c982dabb5953b778b71d50d848_lang_en_utf8_countries.php',
    'e783513693c95d6ec659cb487acda8243d118b84_lang_en_utf8_countries.php',
    '5e924af4cac96414ee5cd6fc22b5daaedc86a476_lang_en_utf8_countries.php',
    'c2acd8318b4e95576015ccc649db0f2f1fe980f7_lang_en_utf8_grades.php',
    '5a7e8cf985d706b935a61366a0c66fd5c6fb20f9_lang_en_utf8_grades.php',
    '8e9d88f2f6b5660687c9bd5decbac890126c13e5_lang_en_utf8_debug.php',
    '1343697c8235003a91bf09ad11ab296f106269c7_lang_en_utf8_error.php',
    'c5d0eaa9afecd924d720fbc0b206d144eb68db68_lang_en_utf8_question.php',
    '06e84d52bd52a4901e2512ea92d87b6192edeffa_lang_en_utf8_error.php',
    '4416da02db714807a71d8a28c19af3a834d2a266_lang_en_utf8_enrol_mnet.php',
    'fd1d5455fde49baa64a37126f25f3d3fd6b6f3f2_mod_assignment_lang_en_assignment.php',
    '759b81f3dc4c2ce2b0579f8764aabf9e3fa9d0cc_theme_nonzero_lang_en_theme_nonzero.php',
    '5de15b83cc41c1f03415db00088b0c0d294556a9_mod_lti_lang_en_lti.php',
    '0d112864825a316a9bc0a17909bd0ea5342e4be4_question_type_ordering_lang_en_qtype_ordering.php',
);

$ignoredcommits = array(
    // Following are MDL-21694 commits that just move the lang files. Such a move is registered
    // as a deletion and re-addition of every string which is usually useless.
    '9d68aee7860398345b3921b552ccaefe094d438a',
    '5f251510549671a3864427e4ea161b8bd62d0df9',
    '60b00b6d99f10c084375d09c244f0011baabdec9',
    'f312fe1a9e00abe1f79348d1092697a485369bfb',
    '05162f405802faf006cac816443432d29e742458',
    '57223fbe95df69ebb9831ff681b89ec67de850ff',
    '7ae8954a02ebaf82f74e2842e4ad17c05f6af6a8',
    '1df58edc0f25db3892950816f6b9edb2de693a2c',
    'd8753184ec66575cffc834aaeb8ac25477da289b',
    '200fe7f26b1ba13d9ac63f073b6676ce4abd2976',
    '2476f5f22c2bfaf0626a7e1e8af0ffee316b01b4',
    'd8a81830333d99770a6072ddc0530c267ebddcde',
    'afbbc6c0f6667a5af2a55aab1319f3be00a667f1',
    '3158eb9350ed79c3fe81919ea8af67418de18277',
    'ffbb41f48f9c317347be4288771db84e36bfdf22',
    '81144b026f80665a7d7ccdadbde4e8f99d91e806',
    '675aa51db3038b629c7350a53e43f20c5d414045',
    'dee576ebbaece98483acfa401d459f62f0f0387d',
    'eea1d899bca628f9c5e0234068beb713e81a64fd',
    'ce0250ec19cf29479b36e17541c314030a2f9ab5',
    'bda5f9b1159bff09006dac0bcfaec1ec788f134c',
    '89422374d1944d4f5fff08e2187f2c0db75aaefc',
    'b4340cb296ce7665b6d8f64885aab259309271a6',
    '001fa4b3135b27c2364845a221d11ea725d446a0',
    'c811222ff9b1469633f7e8dbf6b06ddccafb8dbd',
    '7a4ddc172ae46014ee2ebb5b9f4ee2ada2cd7e1e',
    'bc3755be21025c5815de19670eb04b0875f5fa31',
    '96b838efa990d6a6a2db0050d9deeceeda234494',
    'cb9dc45c36ffbbdee1a0f22a50b4f31db47a5eb6',
    '33aadb2d70c4e8381281b635a9012f3f0673d397',
    '34970b7fc6c4932b15426ea80ad94867a1e1bb5b',
    '7a563f0f3586a4bc5b5263492282734410e01ee0',
    'b13af519fc48ee9d8b1e801c6056519454bf8400',
    'd1f62223b59d6acb1475d3979cdafda726cc1290',
    '2064cbaa0f6ea36fc5803fcebb5954ef8c642ac4',
    // Following commit renames en_utf8 back to en we are ignoring that.
    '3a915b066765efc3cc166ae8186405f67c04ec2c',
    // Following commits just move a string file.
    '34d6a78987fa61f81bf37f5c4c2ee3e7a01d4d1c',
    '8118207b6fd8607eeca1aa7bef327e8280e3e5f8',
    // Removal of mod_hotpot from core.
    '91b9560bd63e5582781e910573ee0887b558ca12',
    // Moving most of the codebase to the public subfolder.
    'f747c15cbf1c1c963a9592a73b167b969ec33d75',
);

$git = new \local_amos\local\git(AMOS_REPO_MOODLE);
$git->exec('remote update --prune');

$versions = mlang_version::list_supported();
$standardplugins = \local_amos\local\util::standard_components_tree();
$stage = new mlang_stage();
$prevstartat = '';

fputs(STDOUT, "*****************************************\n");
fputs(STDOUT, date('Y-m-d H:i', time()));
fputs(STDOUT, " PARSE CORE JOB STARTED\n");

foreach ($versions as $version) {
    fputs(STDOUT, "=========================================\n");

    if ($git->has_remote_branch($version->branch)) {
        $gitbranch = 'origin/' . $version->branch;

    } else if ($version->code == mlang_version::latest_version()->code) {
        $gitbranch = 'origin/main';

    } else {
        fputs(STDERR, "GIT BRANCH NOT FOUND FOR MOODLE VERSION {$version->label}\n");
        exit(3);
    }

    fputs(STDOUT, "PROCESSING VERSION {$version->code} ({$gitbranch})\n");

    $branch = $version->branch;
    $startatlock = "{$var}/{$branch}.startat";
    $startat = '';

    if (file_exists($startatlock)) {
        $startat = trim(file_get_contents($startatlock));
        if (!empty($startat)) {
            $startat = '^' . $startat . '^';
            $prevstartat = '^' . $gitbranch . '^';
        }

    } else if (!empty($prevstartat)) {
        // This is the first time we process the new version. Start where the previous version was.
        $startat = $prevstartat;

    } else {
        fputs(STDERR, "Missing {$branch} branch startat point\n");
        exit(4);
    }

    $gitcmd = "log --topo-order --reverse --raw --no-merges --format='format:COMMIT:%H TIMESTAMP:%at' {$gitbranch} {$startat}";
    fputs(STDOUT, "RUNNING git {$gitcmd}\n");
    $gitout = $git->exec($gitcmd);

    $commithash = '';
    $committime = '';
    $affected = [];

    foreach ($gitout as $line) {
        $line = trim($line);

        if (empty($line)) {
            continue;
        }

        if (substr($line, 0, 7) == 'COMMIT:') {
            if (!empty($commithash)) {
                // New commit is here - if we have something to push into AMOS repository, do it now.
                amos_parse_core_commit();
            }

            $commithash = substr($line, 7, 40);
            // The original git commit's timestamp.
            $committime = substr($line, 58);
            // When the commit was processed by AMOS.
            $timemodified = time();
            // Explicit list of strings affected by the commit.
            $affected = [];
            continue;
        }

        if (in_array($commithash, $ignoredcommits)) {
            fputs(STDOUT, "IGNORED {$commithash}\n");
            continue;
        }

        // Refer to "RAW OUTPUT FORMAT" section of git-diff man page for the description of the format.
        $parts = explode("\t", $line);
        $parts0 = explode(' ', $parts[0]);

        // Status, followed by optional "score" number.
        $status = substr(end($parts0), 0, 1);

        if ($status === 'D') {
            // The file is being removed - do not do anything.
            continue;

        } else if ($status === 'C' || $status === 'R') {
            // Copy of a file into a new one or renaming of a file - read the name of the destination file.
            $file = $parts[2];

        } else if ($status === 'A' || $status === 'M') {
            // Addition of a new file or modification of the contents or mode of a file.
            $file = $parts[1];

        } else {
            // Some unsupported change, ignore it.
            continue;
        }

        $componentname = mlang_component::name_from_filename($file);

        // Series of checks that the file is proper language pack.

        if (($version->code >= 20) and ($committime >= 1270884296)) {
            // Since Petr's commit 3a915b06676 on 10th April 2010, strings are in 'en' folder again.
            $enfolder = 'en';
        } else {
            $enfolder = 'en_utf8';
        }

        if (strpos($file, "lang/$enfolder/") === false) {
            // This is not a language file.
            continue;
        }

        if (strpos($file, "lang/$enfolder/docs/") !== false or strpos($file, "lang/$enfolder/help/") !== false) {
            // Ignore.
            continue;
        }

        if (strpos($file, 'portfolio_format_leap2a.php') !== false) {
            // See MDL-22212.
            continue;
        }

        if (substr($file, -4) !== '.php') {
            // This is not a valid string file.
            continue;
        }

        if (substr($file, 0, 13) == 'install/lang/') {
            // Ignore auto generated installer files.
            fputs(STDOUT, "SKIP installer bootstrap strings\n");
            continue;
        }

        if (strpos($file, '/tests/fixtures/') !== false) {
            // This is a string file that is part of unit tests, ignore it.
            fputs(STDOUT, "SKIP unit test fixture file\n");
            continue;
        }

        if (isset($standardplugins[$version->code])) {
            // Parse only standard component.
            if (!isset($standardplugins[$version->code][$componentname])) {
                fputs(STDERR, "WARNING non-standard component on this branch ($componentname)\n");
            }
        }

        $memprev = $mem;
        $mem = memory_get_usage();
        $memdiff = $memprev < $mem ? '+' : '-';
        $memdiff = $memdiff . abs($mem - $memprev);
        fputs(STDOUT, "FOUND {$commithash} {$status} {$file} [{$mem} {$memdiff}]\n");

        // Get some additional information from the commit - name, email, timestamp, subject, body.
        $format = implode('%n', array('%an', '%ae', '%at', '%s', '%b'));
        $commitinfo = $git->exec("log --format={$format} {$commithash} ^{$commithash}~");
        $committer = $commitinfo[0];
        $committeremail = $commitinfo[1];
        // The real timestamp of the commit - should be the same as $committime.
        $realmodified = $commitinfo[2];
        $commitmsg = iconv('UTF-8', 'UTF-8//IGNORE', $commitinfo[3]);
        $commitmsg .= "\n\nCommitted into Git: " . local_amos_renderer::commit_datetime($realmodified);
        // Full commit message to look up for AMOS script there.
        $fullcommitmsg = implode("\n", array_slice($commitinfo, 3));

        // Get the list of strings affected by the commit.
        $diff = $git->exec("log -1 -p --format=format: " . $commithash . ' -- ' . escapeshellarg($file));

        foreach (mlang_tools::get_affected_strings($diff) as $stringid) {
            $affected[$componentname][$stringid] = true;
        }

        // Dump the given revision of the file to a temporary area.
        $checkout = $commithash . '_' . str_replace('/', '_', $file);

        if (in_array($checkout, $brokencheckouts)) {
            fputs(STDOUT, "BROKEN $checkout\n");
            continue;
        }

        $checkout = $tmp . '/' . $checkout;

        $git->exec(" show {$commithash}:{$file} > {$checkout}");

        // Convert the PHP file into strings in the staging area.
        if ($version->code >= 20) {
            if ($committime >= 1270908105) {
                // Since David's commit 30c8dd34f7 on 10th April 2010, strings are in the new format.
                $checkoutformat = 2;
            } else {
                $checkoutformat = 1;
            }
        } else {
            $checkoutformat = 1;
        }

        $component = mlang_component::from_phpfile($checkout, 'en', $version, $timemodified, $componentname, $checkoutformat);
        $stage->add($component);
        $component->clear();
        unset($component);
        unlink($checkout);
    }
    // We just parsed the last git commit at this branch - let us commit what we have.
    amos_parse_core_commit();
}

fputs(STDOUT, date('Y-m-d H:i', time()));
fputs(STDOUT, " PARSE CORE JOB DONE\n");
