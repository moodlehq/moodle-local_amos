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

        // translate into languages
        $output .= html_writer::start_tag('div', array('class' => 'item select yui-gd'));
        $output .= html_writer::start_tag('div', array('class' => 'label yui-u first'));
        $output .= html_writer::tag('label', 'Language', array('for' => 'amosfilter_ftgt'));
        $output .= html_writer::tag('div', 'Translate into this language', array('class' => 'description'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::start_tag('div', array('class' => 'element yui-u'));
        $options = array('cs' => 'Čeština (cs)', 'fr' => 'Français (fr)', 'fr_ca' => 'Français - Canada (fr_ca)');
        $output .= html_writer::select($options, 'ftgt', $filter->get_data()->target, '', array('id' => 'amosfilter_ftgt'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');

        // version checkboxes
        $output .= html_writer::start_tag('div', array('class' => 'item checkboxgroup yui-gd'));
        $output .= html_writer::start_tag('div', array('class' => 'label yui-u first'));
        $output .= html_writer::tag('label', 'Version', array('for' => 'amosfilter_fver'));
        $output .= html_writer::tag('div', 'Show strings from these Moodle versions', array('class' => 'description'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::start_tag('div', array('class' => 'element yui-u'));
        $fver = '';
        foreach (mlang_version::list_translatable() as $version) {
            $checkbox = html_writer::checkbox('fver[]', $version->code, in_array($version->code, $filter->get_data()->version),
                    $version->label);
            $fver .= html_writer::tag('div', $checkbox, array('class' => 'labelled_checkbox'));
        }
        $output .= html_writer::tag('div', $fver, array('id' => 'amosfilter_fver', 'class' => 'checkboxgroup'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');

        // other languages selector
        $output .= html_writer::start_tag('div', array('class' => 'item select yui-gd'));
        $output .= html_writer::start_tag('div', array('class' => 'label yui-u first'));
        $output .= html_writer::tag('label', 'Compare with', array('for' => 'amosfilter_flng'));
        $output .= html_writer::tag('div', 'Beside English, display also translations into these languages', array('class' => 'description'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::start_tag('div', array('class' => 'element yui-u'));
        $options = array('en' => 'English (en)', 'cs' => 'Čeština (cs)', 'fr' => 'Français (fr)', 'fr_ca' => 'Français - Canada (fr_ca)');
        $output .= html_writer::select($options, 'flng[]', $filter->get_data()->language, '',
                    array('id' => 'amosfilter_flng', 'multiple' => true, 'size' => 3));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');

        // component selector
        $output .= html_writer::start_tag('div', array('class' => 'item select yui-gd'));
        $output .= html_writer::start_tag('div', array('class' => 'label yui-u first'));
        $output .= html_writer::tag('label', 'Component', array('for' => 'amosfilter_fcmp'));
        $output .= html_writer::tag('div', 'Show strings of these components', array('class' => 'description'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::start_tag('div', array('class' => 'element yui-u'));
        $components = array('moodle' => 'moodle', 'auth_ldap' => 'auth_ldap', 'workshop' => 'workshop');
        $output .= html_writer::select($components, 'fcmp[]', $filter->get_data()->component, '',
                    array('id' => 'amosfilter_fcmp', 'multiple' => true, 'size' => 3));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');

        // other filter settings
        $output .= html_writer::start_tag('div', array('class' => 'item checkboxgroup yui-gd'));
        $output .= html_writer::start_tag('div', array('class' => 'label yui-u first'));
        $output .= html_writer::tag('label', 'Miscellaneous', array('for' => 'amosfilter_fmis'));
        $output .= html_writer::tag('div', 'Additional conditions on strings to display', array('class' => 'description'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::start_tag('div', array('class' => 'element yui-u'));

        $fmis    = html_writer::checkbox('fmis', 1, $filter->get_data()->missing, 'missing and outdated strings only');
        $fmis    = html_writer::tag('div', $fmis, array('class' => 'labelled_checkbox'));

        $fhlp    = html_writer::checkbox('fhlp', 1, $filter->get_data()->helps, 'help tooltips only');
        $fhlp    = html_writer::tag('div', $fhlp, array('class' => 'labelled_checkbox'));

        $output .= html_writer::tag('div', $fmis . $fhlp, array('id' => 'amosfilter_fmis', 'class' => 'checkboxgroup vertical'));

        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');

        // must contain string
        $output .= html_writer::start_tag('div', array('class' => 'item text yui-gd'));
        $output .= html_writer::start_tag('div', array('class' => 'label yui-u first'));
        $output .= html_writer::tag('label', 'Substring', array('for' => 'amosfilter_ftxt'));
        $output .= html_writer::tag('div', 'String must contain given text (comma separated list of values)', array('class' => 'description'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::start_tag('div', array('class' => 'element yui-u'));

        $output .= html_writer::empty_tag('input', array('name' => 'ftxt', 'type' => 'text', 'value' => $filter->get_data()->substring));

        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');

        // hidden fields
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => '__lazyform_' . $filter->lazyformname, 'value' => 1));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));

        // submit
        $output .= html_writer::start_tag('div', array('class' => 'item submit yui-gd'));
        $output .= html_writer::start_tag('div', array('class' => 'label yui-u first'));
        $output .= html_writer::tag('label', '&nbsp;', array('for' => 'amosfilter_fsbm'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::start_tag('div', array('class' => 'element yui-u'));
        $output .= html_writer::empty_tag('input', array('type' => 'submit', 'value' => 'Save filter settings'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');

        // block wrapper for xhtml strictness
        $output = html_writer::tag('div', $output, array());

        // form
        $attributes = array('method' => 'get',
                            'action' => $filter->handler->out(),
                            'id'     => html_writer::random_id(),
                            'class'  => 'lazyform ' . $filter->lazyformname . ' yui-t7',
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
        $table->head = array('Component', 'Identifier', 'Ver', 'Lang', 'Original', 'Translation');
        $table->colclasses = array('component', 'stringinfo', 'version', 'lang', 'original', 'translation');
        $table->attributes['class'] = 'translator';

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
            // the language in which the original is displayed
            $cells[3] = new html_table_cell($string->language);
            // original of the string
            $cells[4] = new html_table_cell(html_writer::tag('div', s($string->original), array('class' => 'preformatted')));
            // Translation
            $t = s($string->translation);
            $sid = local_amos_translator::encode_identifier($string->originalid, $string->translationid);
            //$t = html_writer::tag('textarea', $t, array('name' => $sid, 'class' => 'translation'));
            $t = html_writer::tag('div', $t, array('name' => $sid, 'class' => 'translation'));
            $i = html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'fields[]', 'value' => $sid));
            $cells[5] = new html_table_cell($t . $i);
            $row = new html_table_row($cells);
            $table->data[] = $row;
        }

        $output  = html_writer::table($table);
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'language', 'value' => $translator->target));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
        $submit  = html_writer::empty_tag('input', array('type' => 'submit', 'value' => 'Save translations'));
        $output .= html_writer::tag('div', $submit, array('class' => 'buttons'));

        // wrapping form
        $attributes = array('method' => 'post',
                            'action' => $translator->handler->out(),
                            'id'     => html_writer::random_id(),
                            'class'  => 'translatorform',
                        );
        $output = html_writer::tag('form', $output, $attributes);
        $output = html_writer::tag('div', $output, array('class' => 'translatorwrapper'));

        return $output;
    }
}

