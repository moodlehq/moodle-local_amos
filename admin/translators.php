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
 * Manage list of translators
 *
 * @package   local-amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once(dirname(dirname(__FILE__)).'/mlanglib.php');
//require_once(dirname(dirname(__FILE__)).'/locallib.php');

require_login(SITEID, false);
require_capability('local/amos:manage', get_system_context());

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/amos/admin/translators.php');
$PAGE->set_title('AMOS ADMIN Translators');
$PAGE->set_heading('Translators');

/// Output starts here
echo $OUTPUT->header();
$languages = array_merge(array('*' => '(All languages)'), mlang_tools::list_languages());
foreach ($languages as $langcode => $langname) {
    $list[$langcode] = (object)array('langname' => $langname, 'translators' => array());
}
$sql = "SELECT at.id AS tid, at.lang, u.id, u.lastname, u.firstname, u.email, u.imagealt, u.picture
          FROM {amos_translators} at
          JOIN {user} u ON at.userid=u.id
      ORDER BY at.lang, u.lastname, u.firstname";
$translators = $DB->get_records_sql($sql);
foreach ($translators as $translator) {
    if (empty($list[$translator->lang])) {
        debugging('Unknown language ' . $translator->lang);
        continue;
    }
    $name = $OUTPUT->user_picture($translator, array('size' => 20)).fullname($translator).' &lt;'.$translator->email.'&gt;';
    $list[$translator->lang]->translators[$translator->id] = $name;
}
$rows = array();
foreach ($list as $langcode => $item) {
    $rows[] = new html_table_row(array($langcode, $item->langname, implode("<br />\n", $item->translators)));
}
$t = new html_table();
$t->head = array('Code', 'Language', 'Allowed translators');
$t->data = $rows;
echo html_writer::table($t);
echo $OUTPUT->footer();
