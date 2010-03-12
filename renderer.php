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

        // language selector
        $output .= html_writer::start_tag('div', array('class' => 'item select yui-gd'));
        $output .= html_writer::start_tag('div', array('class' => 'label yui-u first'));
        $output .= html_writer::tag('label', 'Language', array('for' => 'amosfilter_flng'));
        $output .= html_writer::tag('div', 'Display translations into these languages', array('class' => 'description'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::start_tag('div', array('class' => 'element yui-u'));
        $options = array('cs' => 'Čeština (cs)', 'fr_ca' => 'French - Canadian (fr_cs)');
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

        // missing and outdated strings only
        $output .= html_writer::start_tag('div', array('class' => 'item checkboxgroup yui-gd'));
        $output .= html_writer::start_tag('div', array('class' => 'label yui-u first'));
        $output .= html_writer::tag('label', 'Status', array('for' => 'amosfilter_fmis'));
        $output .= html_writer::tag('div', 'Additional conditions on strings to display', array('class' => 'description'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::start_tag('div', array('class' => 'element yui-u'));

        $fmis    = html_writer::checkbox('fmis', 1, $filter->get_data()->missing, 'only missing and outdated strings');
        $fmis    = html_writer::tag('div', $fmis, array('class' => 'labelled_checkbox'));
        $output .= html_writer::tag('div', $fmis, array('id' => 'amosfilter_fver', 'class' => 'checkboxgroup singlecheckbox'));

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
        //$output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));

        // submit
        $output .= html_writer::start_tag('div', array('class' => 'item submit yui-gd'));
        $output .= html_writer::start_tag('div', array('class' => 'label yui-u first'));
        $output .= html_writer::tag('label', '&nbsp;', array('for' => 'amosfilter_fsbm'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::start_tag('div', array('class' => 'element yui-u'));
        $output .= html_writer::empty_tag('input', array('type' => 'submit', 'value' => 'Show strings'));
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div');

        // block wrapper for xhtml strictness
        $output = html_writer::tag('div', $output, array());

        // form
        $attributes = array('method' => 'get',
                            'action' => $filter->handler->out(),
                            'id'     => html_writer::random_id(),
                            'class'  => 'lazyform yui-t7',
                        );
        $output = html_writer::tag('form', $output, $attributes);

        return $output;
    }

    /**
     * TODO: short description.
     *
     * @param local_amos_translator $translator 
     * @return TODO
     */
    protected function render_local_amos_translator(local_amos_translator $translator) {

    }
}

