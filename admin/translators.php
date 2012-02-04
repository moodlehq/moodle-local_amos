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

$add = optional_param('add', null, PARAM_INT);  // userid to grant privileges to edit a language
$del = optional_param('del', null, PARAM_INT);  // userid to revoke privileges from editing a language

require_login(SITEID, false);
require_capability('local/amos:manage', get_system_context());

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/amos/admin/translators.php');
$PAGE->set_title('AMOS ' . get_string('maintainers', 'local_amos'));
$PAGE->set_heading('AMOS ' . get_string('maintainers', 'local_amos'));

// available translators
$available = get_users_by_capability(get_system_context(), 'local/amos:commit',
        $fields='u.id,u.firstname,u.lastname,u.email', $sort='u.lastname,u.firstname');

if (!empty($add) and array_key_exists($add, $available)) {
    require_sesskey();
    $lang = required_param('langcode', PARAM_SAFEDIR);
    if (empty($lang)) {
        print_error('err_invalidlangcode', 'local_amos');
    }
    $DB->insert_record('amos_translators', (object)array('userid' => $add, 'lang' => $lang));
    redirect($PAGE->url);
}

if (!empty($del)) {
    require_sesskey();
    $lang = required_param('langcode', PARAM_SAFEDIR);
    if (empty($lang)) {
        print_error('err_invalidlangcode', 'local_amos');
    }
    $DB->delete_records('amos_translators', array('userid' => $del, 'lang' => $lang));
    redirect($PAGE->url);
}

$options = array();
foreach ($available as $userid => $user) {
    $options[$userid] = sprintf('%s, %s &lt;%s&gt;', $user->lastname, $user->firstname, $user->email);
}

/// Output starts here
echo $OUTPUT->header();
$languages = array_merge(array('X' => '(All languages)'), mlang_tools::list_languages(true, true, false));
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
    $url = new moodle_url($PAGE->url, array('langcode' => $translator->lang, 'del' => $translator->id, 'sesskey' => sesskey()));
    $delicon = $OUTPUT->action_icon($url, new pix_icon('t/delete', 'Revoke'));
    $list[$translator->lang]->translators[$translator->id] = $name . $delicon;
}
$rows = array();
foreach ($list as $langcode => $item) {
    $url = new moodle_url($PAGE->url, array('langcode' => $langcode, 'sesskey' => sesskey()));
    $form = $OUTPUT->render(new single_select($url, 'add', array_diff_key($options, $item->translators)));
    $rows[] = new html_table_row(array($langcode, $item->langname, $form . implode("<br />\n", $item->translators)));
}
$t = new html_table();
$t->head = array('Code', get_string('language'), get_string('maintainers', 'local_amos'));
$t->data = $rows;
echo html_writer::table($t);
echo $OUTPUT->footer();
