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
 * AMOS renderer class is defined here
 *
 * @package   local-amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * AMOS renderer class
 */
class local_amos_renderer extends plugin_renderer_base {

    /**
     * Renders the filter form
     *
     * @todo this code was used as sort of prototype of the HTML produced by the future forms framework, to be replaced by proper forms library
     * @param local_amos_filter $filter
     * @return string
     */
    protected function render_local_amos_filter(local_amos_filter $filter) {
        $output = '';

        // version checkboxes
        $output .= html_writer::start_tag('div', array('class' => 'item checkboxgroup yui3-gd'));
        $output .= html_writer::start_tag('div', array('class' => 'label yui3-u first'));
        $output .= html_writer::tag('label', 'Version', array('for' => 'amosfilter_fver'));
        $output .= html_writer::tag('div', 'Show strings from these Moodle versions', array('class' => 'description'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::start_tag('div', array('class' => 'element yui3-u'));
        $fver = '';
        foreach (mlang_version::list_all() as $version) {
            $checkbox = html_writer::checkbox('fver[]', $version->code, in_array($version->code, $filter->get_data()->version),
                    $version->label);
            $fver .= html_writer::tag('div', $checkbox, array('class' => 'labelled_checkbox'));
        }
        $output .= html_writer::tag('div', $fver, array('id' => 'amosfilter_fver', 'class' => 'checkboxgroup'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');

        // language selector
        $output .= html_writer::start_tag('div', array('class' => 'item select yui3-gd'));
        $output .= html_writer::start_tag('div', array('class' => 'label yui3-u first'));
        $output .= html_writer::tag('label', 'Languages', array('for' => 'amosfilter_flng'));
        $output .= html_writer::tag('div', 'Display translations in these languages', array('class' => 'description'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::start_tag('div', array('class' => 'element yui3-u'));
        $options = mlang_tools::list_languages();
        foreach ($options as $langcode => $langname) {
            $options[$langcode] = $langname . ' (' . $langcode . ')';
        }
        unset($options['en']); // English is not translatable via AMOS
        $output .= html_writer::select($options, 'flng[]', $filter->get_data()->language, '',
                    array('id' => 'amosfilter_flng', 'multiple' => 'multiple', 'size' => 3));
        $output .= html_writer::tag('span', '', array('id' => 'amosfilter_flng_actions', 'class' => 'actions'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');

        // component selector
        $output .= html_writer::start_tag('div', array('class' => 'item select yui3-gd'));
        $output .= html_writer::start_tag('div', array('class' => 'label yui3-u first'));
        $output .= html_writer::tag('label', 'Component', array('for' => 'amosfilter_fcmp'));
        $output .= html_writer::tag('div', 'Show strings of these components', array('class' => 'description'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::start_tag('div', array('class' => 'element yui3-u'));
        $options = array();
        foreach (mlang_tools::list_components() as $componentname => $undefined) {
            $options[$componentname] = $componentname;
        }
        $output .= html_writer::select($options, 'fcmp[]', $filter->get_data()->component, '',
                    array('id' => 'amosfilter_fcmp', 'multiple' => 'multiple', 'size' => 5));
        $output .= html_writer::tag('span', '', array('id' => 'amosfilter_fcmp_actions', 'class' => 'actions'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');

        // other filter settings
        $output .= html_writer::start_tag('div', array('class' => 'item checkboxgroup yui3-gd'));
        $output .= html_writer::start_tag('div', array('class' => 'label yui3-u first'));
        $output .= html_writer::tag('label', 'Miscellaneous', array('for' => 'amosfilter_fmis'));
        $output .= html_writer::tag('div', 'Additional conditions on strings to display', array('class' => 'description'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::start_tag('div', array('class' => 'element yui3-u'));

        $fmis    = html_writer::checkbox('fmis', 1, $filter->get_data()->missing, 'missing and outdated strings only');
        $fmis    = html_writer::tag('div', $fmis, array('class' => 'labelled_checkbox'));

        $fhlp    = html_writer::checkbox('fhlp', 1, $filter->get_data()->helps, 'help strings only');
        $fhlp    = html_writer::tag('div', $fhlp, array('class' => 'labelled_checkbox'));

        $fstg    = html_writer::checkbox('fstg', 1, $filter->get_data()->stagedonly, 'staged strings only');
        $fstg    = html_writer::tag('div', $fstg, array('class' => 'labelled_checkbox'));

        $output .= html_writer::tag('div', $fmis.$fhlp.$fstg, array('id' => 'amosfilter_fmis', 'class' => 'checkboxgroup vertical'));

        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');

        // must contain string
        $output .= html_writer::start_tag('div', array('class' => 'item text yui3-gd'));
        $output .= html_writer::start_tag('div', array('class' => 'label yui3-u first'));
        $output .= html_writer::tag('label', 'Substring', array('for' => 'amosfilter_ftxt'));
        $output .= html_writer::tag('div', 'String must contain given text', array('class' => 'description'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::start_tag('div', array('class' => 'element yui3-u'));

        $output .= html_writer::empty_tag('input', array('name' => 'ftxt', 'type' => 'text', 'value' => $filter->get_data()->substring));

        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');

        // string identifier
        $output .= html_writer::start_tag('div', array('class' => 'item text yui3-gd'));
        $output .= html_writer::start_tag('div', array('class' => 'label yui3-u first'));
        $output .= html_writer::tag('label', 'String identifier', array('for' => 'amosfilter_ftxt'));
        $output .= html_writer::tag('div', 'The key in the array of strings', array('class' => 'description'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::start_tag('div', array('class' => 'element yui3-u'));

        $output .= html_writer::empty_tag('input', array('name' => 'fsid', 'type' => 'text', 'value' => $filter->get_data()->stringid));

        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');

        // hidden fields
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => '__lazyform_' . $filter->lazyformname, 'value' => 1));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));

        // submit
        $output .= html_writer::start_tag('div', array('class' => 'item submit yui3-gd'));
        $output .= html_writer::start_tag('div', array('class' => 'label yui3-u first'));
        $output .= html_writer::tag('label', '&nbsp;', array('for' => 'amosfilter_fsbm'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::start_tag('div', array('class' => 'element yui3-u'));
        $output .= html_writer::empty_tag('input', array('type' => 'submit', 'value' => 'Save filter settings'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');

        // block wrapper for xhtml strictness
        $output = html_writer::tag('div', $output, array('id' => 'amosfilter'));

        // form
        $attributes = array('method' => 'post',
                            'action' => $filter->handler->out(),
                            'id'     => html_writer::random_id(),
                            'class'  => 'lazyform ' . $filter->lazyformname,
                        );
        $output = html_writer::tag('form', $output, $attributes);
        $output = html_writer::tag('div', $output, array('class' => 'filterwrapper'));

        return $output;
    }

    /**
     * Renders the translation tool
     *
     * @param local_amos_translator $translator
     * @return string
     */
    protected function render_local_amos_translator(local_amos_translator $translator) {

        $table = new html_table();
        $table->id = 'amostranslator';
        $table->head = array('Component', 'Identifier', 'Ver', 'Original', 'Lang',
                get_string('translatortranslation', 'local_amos') . $this->help_icon('translatortranslation', 'local_amos'));
        $table->colclasses = array('component', 'stringinfo', 'version', 'original', 'lang', 'translation');

        if (empty($translator->strings)) {
            return $this->heading('No strings found');
        }

        $missing = 0;
        foreach ($translator->strings as $string) {
            $cells = array();
            // component name
            $cells[0] = new html_table_cell($string->component);
            // string identification code and some meta information
            $t  = html_writer::tag('div', s($string->stringid), array('class' => 'stringid'));
            $t .= html_writer::tag('div', s($string->metainfo), array('class' => 'metainfo'));
            $cells[1] = new html_table_cell($t);
            // moodle version to put this translation onto
            $cells[2] = new html_table_cell($string->branch);
            // original of the string
            $cells[3] = new html_table_cell(html_writer::tag('div', s($string->original), array('class' => 'preformatted')));
            // the language in which the original is displayed
            $cells[4] = new html_table_cell($string->language);
            // Translation
            if (empty($string->translation)) {
                $missing++;
            }
            $t = s($string->translation);
            $sid = local_amos_translator::encode_identifier($string->language, $string->originalid, $string->translationid);
            $t = html_writer::tag('div', $t, array('class' => 'preformatted translation-view'));
            $i = html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'fields[]', 'value' => $sid));
            if ($string->outdated and $string->committable and $string->translation) {
                $c  = html_writer::empty_tag('input', array('type' => 'checkbox', 'id' => 'update_' . $string->translationid,
                        'name' => 'updates[]', 'value' => $string->translationid));
                $help = $this->help_icon('markuptodate', 'local_amos');
                $c .= html_writer::tag('label', 'mark as up-to-date' . $help, array('for' => 'update_' . $string->translationid));
                $c  = html_writer::tag('div', $c, array('class' => 'uptodatewrapper'));
            } else {
                $c = '';
            }
            $cells[5] = new html_table_cell($t . $c . $i);
            $cells[5]->id = $sid;
            $cells[5]->attributes['class'] = $string->class;
            $cells[5]->attributes['class'] .= ' translatable';
            if ($string->committable) {
                $cells[5]->attributes['class'] .= ' committable';
            }
            if ($string->outdated) {
                $cells[5]->attributes['class'] .= ' outdated';
            }
            $row = new html_table_row($cells);
            $table->data[] = $row;
        }

        $heading = 'Found: '.$translator->numofrows.' &nbsp;&nbsp;&nbsp; Missing: '.$translator->numofmissing.' ('.$missing.')';
        $output = $this->heading_with_help($heading, 'foundinfo', 'local_amos');
        $pages = ceil($translator->numofrows / local_amos_translator::PERPAGE);
        $output .= html_writer::tag('div', self::page_links($pages, $translator->currentpage), array('class' => 'pagination'));
        $output .= html_writer::table($table);
        $output .= html_writer::tag('div', self::page_links($pages, $translator->currentpage), array('class' => 'pagination'));
        $output = html_writer::tag('div', $output, array('class' => 'translatorwrapper'));

        return $output;
    }

    /**
     * Displays paginator
     *
     * @param int $numofpages
     * @param int $current current page number, numbering from 1 to n
     * @param moodle_url $handler
     * @return string
     */
    protected static function page_links($numofpages, $current) {
        global $PAGE;

        if ($numofpages < 2) {
            return '';
        }
        $output = '';
        if ($current > 1) {
            $output .= html_writer::tag('span', html_writer::link(new moodle_url($PAGE->url, array('fpg' => $current - 1)), '&lt;&lt; '));
        }
        for ($i = 1; $i <= $numofpages; $i++) {
            if ($i == $current) {
                $link = html_writer::tag('span', $i, array('class' => 'current'));
            } else {
                $url = new moodle_url($PAGE->url, array('fpg' => $i));
                $link = html_writer::link($url, $i);
                $link = html_writer::tag('span', $link);
            }
            $output .= ' ' . $link;
        }
        if ($current < $numofpages) {
            $output .= html_writer::tag('span', html_writer::link(new moodle_url($PAGE->url, array('fpg' => $current + 1)), '&gt;&gt; '));
        }
        return $output;
    }

    /**
     * Renders the stage
     *
     * @param local_amos_stage $stage
     * @return string
     */
    protected function render_local_amos_stage(local_amos_stage $stage) {
        global $CFG;

        $table = new html_table();
        $table->id = 'amosstage';
        $table->head = array('Component', 'Identifier', 'Ver', 'Original', 'Lang',
                get_string('stagetranslation', 'local_amos') . $this->help_icon('stagetranslation', 'local_amos'));
        $table->colclasses = array('component', 'stringinfo', 'version', 'original', 'lang', 'translation');

        $committable = 0;
        foreach ($stage->strings as $string) {
            $cells = array();
            // component name
            $cells[0] = new html_table_cell($string->component);
            // string identification code and some meta information
            $t  = html_writer::tag('div', s($string->stringid), array('class' => 'stringid'));
            $cells[1] = new html_table_cell($t);
            // moodle version to put this translation onto
            $cells[2] = new html_table_cell($string->version);
            // original of the string
            $cells[3] = new html_table_cell(html_writer::tag('div', s($string->original), array('class' => 'preformatted')));
            // the language in which the original is displayed
            $cells[4] = new html_table_cell($string->language);
            // the current and the new translation
            $t1 = s($string->current);
            $t1 = html_writer::tag('del', $t1, array());
            $t1 = html_writer::tag('div', $t1, array('class' => 'current preformatted'));
            $t2 = s($string->new);
            $t2 = html_writer::tag('div', $t2, array('class' => 'new preformatted'));
            $unstageurl = new moodle_url('/local/amos/stage.php', array(
                    'unstage'   => $string->stringid,
                    'component' => $string->component,
                    'branch'    => $string->branch,
                    'lang'      => $string->language));
            $unstagebutton = $this->single_button($unstageurl, 'Unstage', 'post', array('class' => 'singlebutton protected'));
            $cells[5] = new html_table_cell($t2 . $t1 . $unstagebutton);
            if ($string->committable and !(trim($string->current) === trim($string->new))) {
                $cells[5]->attributes['class'] .= ' committable';
                $committable++;
            }
            if (!$string->committable) {
                $cells[5]->attributes['class'] .= ' uncommittable';
            }
            $row = new html_table_row($cells);
            $table->data[] = $row;
        }
        $table = html_writer::table($table);

        $commitform = html_writer::tag('textarea', '', array('name' => 'message'));
        $commitform .= html_writer::empty_tag('input', array('name' => 'sesskey', 'value' => sesskey(), 'type' => 'hidden'));
        $button = html_writer::empty_tag('input', array('value' => 'Commit', 'type' => 'submit'));
        $button = html_writer::tag('div', $button);
        $commitform = html_writer::tag('div', $commitform . $button);
        $commitform = html_writer::tag('form', $commitform, array('method' => 'post', 'action' => $CFG->wwwroot . '/local/amos/stage.php'));
        $commitform = html_writer::tag('div', $commitform, array('class' => 'commitformwrapper protected'));

        $pruneurl = new moodle_url('/local/amos/stage.php', array('prune' => 1));
        $prunebutton = $this->single_button($pruneurl, 'Prune non-committable', 'post', array('class'=>'singlebutton protected'));

        $rebaseurl = new moodle_url('/local/amos/stage.php', array('rebase' => 1));
        $rebasebutton = $this->single_button($rebaseurl, 'Rebase', 'post', array('class'=>'singlebutton protected'));

        $i = 0;
        foreach ($stage->filterfields->fver as $fver) {
            $params['fver['.$i.']'] = $fver;
            $i++;
        }
        $i = 0;
        foreach ($stage->filterfields->flng as $flng) {
            $params['flng['.$i.']'] = $flng;
            $i++;
        }
        $i = 0;
        foreach ($stage->filterfields->fcmp as $fcmp) {
            $params['fcmp['.$i.']'] = $fcmp;
            $i++;
        }
        $params['fstg'] = 1;
        $params['__lazyform_amosfilter'] = 1;
        $editurl = new moodle_url('/local/amos/view.php', $params);
        $editbutton = $this->single_button($editurl, 'Edit staged strings');

        $output = $this->heading('There are ' . count($stage->strings) . ' staged strings, ' . $committable . ' of them can be committed.');
        if ($committable) {
            $output .= $commitform;
        }

        if (!empty($stage->strings)) {
            $legend = html_writer::tag('legend', get_string('stageactions', 'local_amos') . $this->help_icon('stageactions', 'local_amos'));
            $output .= html_writer::tag('fieldset', $legend.$editbutton.$prunebutton.$rebasebutton, array('class' => 'actionbuttons'));
            $output .= $table;
        }
        $output = html_writer::tag('div', $output, array('class' => 'stagewrapper'));

        return $output;

    }

    /**
     * Returns formatted commit date and time
     *
     * In our git repos, timestamps are stored in UTC always and that is what standard git log
     * displays.
     *
     * @param int $timestamp
     * @return string formatted date and time
     */
    public static function commit_datetime($timestamp) {
        $tz = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $t = date('Y-m-d H:i e', $timestamp);
        date_default_timezone_set($tz);
        return $t;
    }

    /**
     * Render repository records
     *
     * @param local_amos_log $records of stdclass full amos repository records
     * @return string HTML
     */
    protected function render_local_amos_log(local_amos_log $log) {
        $output = $this->heading('TODO: there will be filter here');
        foreach ($log->commits as $commit) {
            $o  = '';
            $o .= "Date:      " . self::commit_datetime($commit->timecommitted) . "\n";
            $o .= "Author:    " . s($commit->userinfo) . "\n";
            $o .= "Source:    " . $commit->source . "\n";
            if ($commit->source == 'git') {
                $o .= "Commit:    " . $commit->commithash . "\n";
            }
            $o .= "\n";
            $o .= s($commit->commitmsg);
            $o .= "\n";
            $o .= "\n";
            foreach ($commit->strings as $string) {
                if ($string->deleted) {
                    $o .= "- ";
                } else {
                    $o .= "+ ";
                }
                list($type, $plugin) = normalize_component($string->component);
                if ($type == 'core' and is_null($plugin)) {
                    $component = 'core';
                } else {
                    $component = $type . '_' . $plugin;
                }
                $o .= sprintf('%4s', $string->branch) . ' ';
                $o .= sprintf('%-8s', $string->lang) . ' ';
                $o .= ' [' . $string->stringid . ',' . $component . "]\n";
            }
            $output .= html_writer::tag('pre', $o, array('class' => 'preformatted logrecord'));
        }
        return $output;
    }

    /**
     * Render index page of http://download.moodle.org/langpack/x.x/
     *
     * Output of this renderer is expected to be saved into the file index.php and uploaded to the server
     *
     * @param local_amos_index_page $data
     * @return string HTML
     */
    protected function render_local_amos_index_page(local_amos_index_page $data) {

        $output = '<?php
require(dirname(dirname(dirname(__FILE__)))."/config.php");
require(dirname(dirname(dirname(__FILE__)))."/menu.php");

print_header("Moodle: Download: Language Packs", "Moodle Downloads",
        "<a href=\"$CFG->wwwroot/\">Download</a> -> Language Packs",
        "", "", true, " ", $navmenu);
$current = "lang";
require(dirname(dirname(dirname(__FILE__)))."/tabs.php");

print_simple_box_start("center", "100%", "#FFFFFF", 20);
?>
';
        $output .= $this->heading('Moodle 2.0 language packs');

        $table = new html_table();
        $table->head = array('Language', 'Download', 'Size', 'Modified', 'Translation ratio');
        $table->width = '100%';
        foreach ($data->langpacks as $langcode => $langpack) {
            $row = array();
            $row[0] = $langpack->langname;
            $row[1] = $langpack->filename;
            $row[2] = display_size($langpack->filesize);
            if (time() - $langpack->modified < WEEKSECS) {
                $row[3] = html_writer::tag('strong', self::commit_datetime($langpack->modified), array('class'=>'recentlymodified'));
            } else {
                $row[3] = self::commit_datetime($langpack->modified);
            }
            if ($langpack->parent == 'en') {
                // standard package
                if (!is_null($langpack->ratio)) {
                    $barmax = 500; // pixels
                    $barwidth = floor($barmax * $langpack->ratio);
                    $barvalue = sprintf('%d %%', $langpack->ratio * 100);
                    if ($langpack->ratio >= 0.8) {
                        $bg = '#e7f1c3'; // green
                    } elseif ($langpack->ratio >= 0.6) {
                        $bg = '#d2ebff'; // blue
                    } elseif ($langpack->ratio >= 0.4) {
                        $bg = '#f3f2aa'; // yellow
                    } else {
                        $bg = '#ffd3d9'; // red
                    }
                    $row[4] = '<div style="width:'.$barmax.'px;">
                                <div style="width:'.$barwidth.'px;background-color:'.$bg.';float:left;margin-right: 5px;">&nbsp;</div><span>'.$barvalue.'</span></div>';
                } else {
                    $row[4] = '';
                }
            } else {
                // variant
                $row[4] = $langpack->totaltranslated.' modifications';
            }
            $table->data[] = $row;
        }
        $output .= html_writer::table($table);
        $output .= '<?php
print_simple_box_end();
print_footer();
';

        return $output;
    }
}
