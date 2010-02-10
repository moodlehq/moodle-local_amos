<?php

header("Content-Type: text/plain");

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/mlanglib.php');

$wtree = '/home/mudrd8mz/devel/amos/moodle-lang';
$tmp = make_upload_directory('temp/amos', false);
$var = make_upload_directory('var/amos', false);

// the following commits contains a syntax typo and they can't be included for processing. They are skipped
$MLANG_BROKEN_CHECKOUTS = array(
    '6ec9481c57031d35ebb5ed19807791264f522d9c:langconfig',
    '64f9179caba6584f6fcee8e7ed957473301c26e7:auth',
    '7d35c4bd7ed781fccf1334a0662a6cc2a9c3c627:moodle',
    '3ea6d49f6d2e9785deff089193c878c4cf46a0dc:enrol_authorize',
    '1852dc59bd96d6a5dfa2f836d3b24a53e317e2ab:admin',
    'd76860354ecf3854e0d36cf82aaf23e54064cddc:admin',
    '1a27c7774de7dc6d2ce5d650eae972344159bdc2:admin',
    '7fd8390819eae456caa954d1fdb00ba84b3c428b:dialogues',
    '519dc243ca72b549fdd4a9a4bf27c4f3cea88ec2:quiz_analysis',
    '8bec8cb0b6edc835707c9eb4af61c0b37a31fed8:quiz_analysis',
    '4e573ae1b379d0a757104fb121515ce05427b9bf:enrol_mnet',
    '4e573ae1b379d0a757104fb121515ce05427b9bf:group',
    'a669da1017199cd19074b673675e3bba20919595:mnet',
    '2c4cdacf4a7976cdc1958da949bf28b097e42e88:mnet',
    '4eb0ae6e276ce2449d3ef3107b4843e7e351ad94:admin',
    '09f1f1f81dab4e3847876ad5e4803c74b69a493b:dialogues',
    'ad2c7d53d9423499edade1cbef08598461d3dbf3:langconfig',
    '3ccc36a2ce7d4359d88a66392cb15ba4843a137c:editor',
    'c0a238b29dd9014b6caef14ee79a0d510d364c44:tag',
    '7cf367a33e97e8046cf579282e89956ff18da72f:error',
    '815d89e7670b47bbaba17bafe2025895e310f81b:block_search',
    'd705fde9f580216833552e372a432ad36ff77413:debug',
    '1f18133335b17348abeb4472904c2a7765ae1009:gradeimport_xml',
    '8af971ad2265b3efc901985a6f204aa4ba4408a9:error',
    '77ef858496eda8c000327a7d87802d13643b0aa2:appointment',
    'd090367ead604f2a65e2b22abbec6f19c1915607:question',
    'abe4d2a450411820e7eda4d868ff01923597d5bd:error',
    '079dabb0918bb0e5d35b15886a4b84ed75ee1c7d:error',
    'a43d1b0eee00cd8d9f1d485916b24dfb2e85d3cc:auth',
    'a43d1b0eee00cd8d9f1d485916b24dfb2e85d3cc:enrol_ldap',
    'a43d1b0eee00cd8d9f1d485916b24dfb2e85d3cc:forum',
    'a43d1b0eee00cd8d9f1d485916b24dfb2e85d3cc:group',
    'a43d1b0eee00cd8d9f1d485916b24dfb2e85d3cc:hotpot',
    'a43d1b0eee00cd8d9f1d485916b24dfb2e85d3cc:question',
    '598095a80d24d982efa2eb5446ba2046ff5a4f80:moodlelib',
    'dcb292801654b0fd0bfb76ff0bf597f49f133cc9:moodle',
);

chdir($wtree);

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
$gitcmd = "git whatchanged --reverse --format=format:COMMIT:%H origin/cvshead {$startat}";
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
    echo "{$commithash} {$changetype} {$file}\n";

    if ($file == 'sr_lt_utf8/~$admin.php') {
        // wrong commit 3c270b5d9d6ae860e61b678a36f3490a7568f6ab
        continue;
    }

    $parts = explode('/', $file);
    $langcode = $parts[0];  // eg. 'en_us_utf8'
    $langcode = substr($langcode, 0, -5);   // without _utf8 suffix

    if ($changetype == 'D') {
        // todo process file removal
        continue;
    }

    $componentname = mlang_component_gitcheckout::name_from_filename($file);

    $pack = new mlang_pack('', $langcode);
    $componentfile = new mlang_component_gitcheckout($pack, $componentname);
    $componentfile->source = 'git';

    $componentdb = new mlang_component_db($pack, $componentname);
    $componentdb->load();

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
    $commitmsg      = $commitinfo[3];

    // dump the given revision of the file to a temporary area
    $checkout = "{$commithash}:{$componentfile->name}";
    if (in_array($checkout, $MLANG_BROKEN_CHECKOUTS)) {
        echo "BROKEN $checkout\n";
        continue;
    }
    $checkout = $tmp . '/' . $checkout;
    exec("git show {$commithash}:{$file} > {$checkout}");
    $componentfile->load($checkout, $timemodified, $commithash, $committer, $committeremail, $commitmsg);

    foreach ($componentfile->get_iterator() as $stringinfile) {
        if (! $componentdb->has_string($stringinfile)) {
            echo "NEW {$stringinfile->id}\n";
            try {
                $componentdb->update_string($stringinfile, $componentfile);
            } catch (moodle_exception $e) {
                echo "ERROR {$e->getMessage()}\n";
            }
            continue;
        }
        if ($componentdb->update_needed($stringinfile)) {
            echo "UPDATE {$stringinfile->id}\n";
            try {
                $componentdb->update_string($stringinfile, $componentfile);
            } catch (moodle_exception $e) {
                echo "ERROR {$e->getMessage()}\n";
            }
            continue;
        }
    }

    unlink($checkout);
}
echo "DONE\n";
