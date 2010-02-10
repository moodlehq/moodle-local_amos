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
     * TODO: short description.
     *
     * @param amos_filter $filter 
     * @return TODO
     */
    protected function render_local_amos_filter(local_amos_filter $filter) {
        $output = '';

        // filter version checkboxes
        foreach ($filter->versions as $version) {
            if ($version->translatable) {
                $output .= html_writer::checkbox('fver[]', $version->code, $version->checked, $version->label);
            }
        }

        // hidden fields
        //$output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));

        // submit
        $output .= html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('reload', 'local_amos')));

        // block wrapper for xhtml strictness
        $output = html_writer::tag('div', array(), $output);

        // form
        $attributes = array('method' => 'get',
                            'action' => $filter->handler->out(),
                            'id'     => html_writer::random_id());
        $output = html_writer::tag('form', $attributes, $output);

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

