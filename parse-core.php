<?php

header("Content-Type: text/plain");

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/mlanglib.php');

// prepare the list of valid locations of string files
$wtree = '/home/mudrd8mz/devel/amos/moodle';
$locations = array('lang/en_utf8/*.php');
$plugins = string_manager::instance()->get_registered_plugin_types();
foreach ($plugins as $prefixtype => $pluginlocations) {
    if (empty($prefixtype)) {
        $plugintype = '';
    } else {
        $plugintype = substr($prefixtype, 0, strlen($prefixtype)-1);
    }
    foreach ($pluginlocations as $pluginlocation) {
        $pluginlist = get_plugin_list($plugintype);
        foreach ($pluginlist as $plugintype => $pluginpath) {
            $langfile = substr($pluginpath . '/lang/en_utf8/' . $prefixtype . $plugintype  . '.php', strlen($CFG->dirroot) + 1);
            if (file_exists($wtree . '/' . $langfile)) {
                $locations[] = $langfile;
            }
        }
    }
}
$locations = implode(' ', $locations);
echo "SEARCH $locations\n";

$tmp = make_upload_directory('temp/amos', false);
$var = make_upload_directory('var/amos', false);
$mem = memory_get_usage();

// the following commits contains a syntax typo and they can't be included for processing. They are skipped
$MLANG_BROKEN_CHECKOUTS = array(
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
);

$MLANG_PARSE_BRANCHES = array(
    'MOODLE_20_STABLE',
    'MOODLE_19_STABLE',
    'MOODLE_18_STABLE',
    'MOODLE_17_STABLE',
    'MOODLE_16_STABLE',
);

foreach ($MLANG_PARSE_BRANCHES as $branch) {
    echo "*****************************************\n";
    echo "BRANCH {$branch}\n";
    if ($branch == 'MOODLE_20_STABLE') {
        $gitbranch = 'origin/cvshead';
    } else {
        $gitbranch = 'origin/' . $branch;
    }
    $version = mlang_version::by_branch($branch);

    $startatlock = "{$var}/{$branch}.startat";
    $startat = '';
    if (file_exists($startatlock)) {
        $startat = trim(file_get_contents($startatlock));
        if (!empty($startat)) {
            $startat = '^' . $startat . '^';
        }
    }

    chdir($wtree);
    $gitout = array();
    $gitstatus = 0;
    $gitcmd = "git whatchanged --reverse --format=format:COMMIT:%H {$gitbranch} {$startat} {$locations}";
    echo "RUN {$gitcmd}\n";
    exec($gitcmd, $gitout, $gitstatus);

    if ($gitstatus <> 0) {
        // error occured
        die('ERROR git-log');
    }

    $commithash = '';
    foreach ($gitout as $line) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }
        if (substr($line, 0, 7) == 'COMMIT:') {
            file_put_contents($startatlock, $commithash);
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

        // get some additional information of the commit
        $format = implode('%n', array('%an', '%ae', '%at', '%s')); // name, email, timestamp, subject
        $commitinfo = array();
        $gitcmd = "git log --format={$format} {$commithash} ^{$commithash}~";
        exec($gitcmd, $commitinfo);
        $committer      = $commitinfo[0];
        $committeremail = $commitinfo[1];
        $timemodified   = $commitinfo[2];
        $commitmsg      = iconv('UTF-8', 'UTF-8//IGNORE', $commitinfo[3]);

        if ($changetype == 'D') {
            // whole file removal
            $component = mlang_component::from_snapshot(mlang_component::name_from_filename($file), 'en', $version, $timemodified);
            foreach ($component->get_iterator() as $string) {
                $string->deleted = true;
                $string->timemodified = $timemodified;
            }
            $stage = new mlang_stage();
            $stage->add($component);
            $stage->commit($commitmsg, array(
                'source' => 'git',
                'userinfo' => $committer . ' <' . $committeremail . '>',
                'commithash' => $commithash
            ), true);
            $component->clear();
            unset($component);
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

        // convert the php file into strings in the staging area
        $component = mlang_component::from_phpfile($checkout, 'en', $version, $timemodified, mlang_component::name_from_filename($file));
        $stage = new mlang_stage();
        $stage->add($component);
        $stage->rebase(true, $timemodified);
        $stage->commit($commitmsg, array(
                'source' => 'git',
                'userinfo' => $committer . ' <' . $committeremail . '>',
                'commithash' => $commithash
            ), true);
        $component->clear();
        unset($component);
        unlink($checkout);
    }
}
echo "DONE\n";
