<?php

$reply = new stdclass();

$reply->code = 200;
$reply->text = 'OK';
$reply->numofitems = 1;
$reply->items = array();

$s = new stdclass();
$s->branch = '1.9';
$s->component = 'moodle';
$s->stringid = 'edit';
$s->origin = 'Edit';
$s->language = 'cs';
$s->translation = 'Upravit';
$reply->items[] = $s;

echo json_encode($reply);
