<?php

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/mlanglib.php');

$branch = 'MOODLE_16_STABLE';
$wtree = '/home/mudrd8mz/tmp/moodle-16';
$file = 'lang/en_utf8/moodle.php';
$pack = new mlang_pack($branch, 'en');
$componentfile = new mlang_component($pack, mlang_component::name_from_filename($file));
$componentdb = new mlang_component($pack, mlang_component::name_from_filename($file));
$componentdb->load_from_db();

print_object($componentdb); die(); // DONOTCOMMIT
$startafter = '';   // '^commithash' - where is this going to be stored?
$tmp = make_upload_directory('temp/mlang', false);

chdir($wtree);
$gitout = array();
$gitstatus = 0;
// the following command gets the list of all commits that influence the content of the file
$gitcmd = "git log {$branch} {$startafter} --format=tformat:%H --reverse {$file}";
exec($gitcmd, $gitout, $gitstatus);

if ($gitstatus <> 0) {
    // error occured
}

foreach ($gitout as $commithash) {
    // get some additional information of the commit
    $format = implode('%n', array('%an', '%ae', '%at', '%s')); // name, email, timestamp, subject
    $commitinfo = array();
    $gitcmd = "git log --format={$format} {$commithash} ^{$commithash}~";
    exec($gitcmd, $commitinfo);

    // dump the given revision of the file to a temporary area
    $checkout = "$tmp/mlang-{$commithash}-{$componentfile->name}";
    exec("git show {$commithash}:{$file} > {$checkout}");
    $componentfile->load_from_php($checkout);

    print_object($componentfile); die(); // DONOTCOMMIT
    /*
    foreach ($componentfile->get_iterator() as $stringinfile) {
        if ($componentdb->has_string($stringinfile->id)) {
        }
    }
     */

    unlink($checkout);
}
