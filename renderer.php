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
 * @package   local_amos
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
     * @param local_amos_filter $filter
     * @return string
     */
    protected function render_local_amos_filter(local_amos_filter $filter) {
        $output = '';
        $alerts = array();

        $filterdata = $filter->get_data();

        // language selector
        $current = $filterdata->language;
        $someselected = false;
        $options = mlang_tools::list_languages(false);
        foreach ($options as $langcode => $langname) {
            if (!$someselected and in_array($langcode, $current)) {
                $someselected = true;
            }
            $options[$langcode] = $langname;
        }
        if (!$someselected) {
            $extraclass = ' error';
            $alerts[] = get_string('filterlngnothingselected', 'local_amos');
        } else {
            $extraclass = '';
        }

        $output .= html_writer::start_tag('div', array('class' => 'control-group'.$extraclass));

        $output .= html_writer::tag('label',
            get_string('filterlng', 'local_amos') . html_writer::tag('div', get_string('filterlng_desc', 'local_amos'), array('class' => 'help-block')),
            array('class' => 'control-label', 'for' => 'amosfilter_flng')
        );

        $output .= html_writer::start_tag('div', array('class' => 'controls'));
        $output .= html_writer::select($options, 'flng[]', $current, '',
                    array('id' => 'amosfilter_flng', 'multiple' => 'multiple', 'size' => 3));
        $output .= html_writer::tag('span', '', array('id' => 'amosfilter_flng_actions', 'class' => 'jsactions'));
        $output .= html_writer::end_tag('div'); // .controls
        $output .= html_writer::end_tag('div'); // .control-group

        // component selector
        $optionscore = array();
        $optionsstandard = array();
        $optionscontrib = array();
        $standard = array();
        foreach (local_amos_standard_plugins() as $plugins) {
            $standard = array_merge($standard, $plugins);
        }
        $allversions = mlang_version::list_all();
        foreach ($allversions as $key => $version) {
            if ($version->code < 2000) {
                unset($allversions[$key]);
            }
        }
        $colspan = count($allversions) + 1;
        $listversions = array();
        foreach (mlang_tools::list_components() as $componentname => $branches) {
            // Categorize the component into Core, Standard or Add-ons.
            if (isset($standard[$componentname])) {
                if ($standard[$componentname] === 'core' or substr($standard[$componentname], 0, 5) === 'core_') {
                    $optionscore[$componentname] = $standard[$componentname];
                } else {
                    $optionsstandard[$componentname] = $standard[$componentname];
                }
            } else {
                $optionscontrib[$componentname] = $componentname;
            }
            // Prepare the list of versions the component strings are available at.
            $componentversions = array();
            foreach ($allversions as $version) {
                if (in_array($version->code, $branches)) {
                    $componentversions[$version->code] = html_writer::tag('td', html_writer::tag('span', $version->label), array('class' => 'version'));
                } else {
                    $componentversions[$version->code] = html_writer::tag('td', '', array('class' => 'version'));
                }
            }
            $listversions[$componentname] = implode('', $componentversions);
        }

        asort($optionscore);
        asort($optionsstandard);
        asort($optionscontrib);

        $current = $filterdata->component;
        $someselected = false;

        $table = html_writer::tag('tr', html_writer::tag('th', get_string('typecore', 'local_amos'), array('colspan' => $colspan)));
        foreach ($optionscore as $key => $label) {
            $selected = in_array($key, $current);
            $cssclasses = 'labelled_checkbox';
            if ($selected) {
                $someselected = true;
                $cssclasses .= ' preset';
            }
            $checkbox = html_writer::checkbox('fcmp[]', $key, $selected, $label);
            $table .= html_writer::tag('tr', html_writer::tag('td', $checkbox, array('class' => $cssclasses)) . $listversions[$key],
                array('class' => 'standard core'));
        }

        $table .= html_writer::tag('tr', html_writer::tag('th', get_string('typestandard', 'local_amos'), array('colspan' => $colspan)));
        foreach ($optionsstandard as $key => $label) {
            $selected = in_array($key, $current);
            $cssclasses = 'labelled_checkbox';
            if ($selected) {
                $someselected = true;
                $cssclasses .= ' preset';
            }
            $checkbox = html_writer::checkbox('fcmp[]', $key, $selected, $label);
            $table .= html_writer::tag('tr', html_writer::tag('td', $checkbox, array('class' => $cssclasses)) . $listversions[$key],
                array('class' => 'standard plugin'));
        }

        $table .= html_writer::tag('tr', html_writer::tag('th', get_string('typecontrib', 'local_amos'), array('colspan' => $colspan)));
        foreach ($optionscontrib as $key => $label) {
            $selected = in_array($key, $current);
            $cssclasses = 'labelled_checkbox';
            if ($selected) {
                $someselected = true;
                $cssclasses .= ' preset';
            }
            $checkbox = html_writer::checkbox('fcmp[]', $key, $selected, $label);
            $table .= html_writer::tag('tr', html_writer::tag('td', $checkbox, array('class' => $cssclasses)) . $listversions[$key],
                array('class' => 'contrib plugin'));
        }

        if (!$someselected) {
            $extraclass = ' error';
            $alerts[] = get_string('filtercmpnothingselected', 'local_amos');
        } else {
            $extraclass = '';
        }

        $output .= html_writer::start_tag('div', array('class' => 'control-group'.$extraclass));
        $output .= html_writer::tag('label',
            get_string('filtercmp', 'local_amos') . html_writer::tag('div', get_string('filtercmp_desc', 'local_amos'), array('class' => 'help-block')),
            array('class' => 'control-label', 'for' => 'amosfilter_fcmp')
        );

        $output .= html_writer::start_tag('div', array('class' => 'controls'));
        $output .= html_writer::div(
            html_writer::tag('table', $table, array('border' => '0')),
            '', array('id' => 'amosfilter_fcmp')
        );
        $output .= html_writer::tag('span', '', array('id' => 'amosfilter_fcmp_actions', 'class' => 'jsactions'));
        $output .= html_writer::end_tag('div'); // .controls
        $output .= html_writer::end_tag('div'); // .control-group

        // version checkboxes
        $current = $filterdata->version;
        $someselected = false;
        $fver = '';
        foreach (mlang_version::list_all() as $version) {
            if ($version->code < 2000) {
                continue;
            }
            if (in_array($version->code, $current)) {
                $someselected = true;
                $thisselected = true;
            } else {
                $thisselected = false;
            }
            $fver .= html_writer::tag('label',
                html_writer::checkbox('fver[]', $version->code, $thisselected) . ' ' . $version->label,
                array('class' => 'checkbox inline')
            );
        }

        if (!$someselected) {
            $extraclass = ' error';
            $alerts[] = get_string('filtervernothingselected', 'local_amos');
        } else {
            $extraclass = '';
        }

        $output .= html_writer::start_tag('div', array('class' => 'control-group'.$extraclass));
        $output .= html_writer::tag('label',
            get_string('filterver', 'local_amos') . html_writer::tag('span', get_string('filterver_desc', 'local_amos'), array('class' => 'help-block')),
            array('class' => 'control-label', 'for' => 'amosfilter_fver')
        );

        $output .= html_writer::start_tag('div', array('id' => 'amosfilter_fver', 'class' => 'controls'));
        $output .= $fver;
        $output .= html_writer::end_tag('div'); // .controls
        $output .= html_writer::end_tag('div'); // .control-group

        // other filter settings
        $output .= html_writer::start_tag('div', array('class' => 'control-group'));
        $collapsible = ' collapsible collapsed';
        foreach (array('missing', 'helps', 'stagedonly', 'greylistedonly', 'withoutgreylisted') as $ff) {
            if (!empty($filterdata->$ff)) {
                $collapsible = '';
            }
        }
        $output .= html_writer::tag('label',
            get_string('filtermis', 'local_amos') . html_writer::tag('div', get_string('filtermis_desc', 'local_amos'), array('class' => 'help-block')),
            array('class' => 'control-label'.$collapsible, 'for' => 'amosfilter_fmis')
        );

        $output .= html_writer::start_tag('div', array('id' => 'amosfilter_fmis', 'class' => 'controls'));

        $collapsible = empty($filterdata->missing) ? ' collapsible collapsed' : '';
        $output .= html_writer::tag('label',
            html_writer::checkbox('fmis', 1, $filterdata->missing) . get_string('filtermisfmis', 'local_amos'),
            array('class' => 'checkbox'.$collapsible)
        );

        $collapsible = empty($filterdata->helps) ? ' collapsible collapsed' : '';
        $output .= html_writer::tag('label',
            html_writer::checkbox('fhlp', 1, $filterdata->helps) . get_string('filtermisfhlp', 'local_amos'),
            array('class' => 'checkbox'.$collapsible)
        );

        $collapsible = empty($filterdata->stagedonly) ? ' collapsible collapsed' : '';
        $output .= html_writer::tag('label',
            html_writer::checkbox('fstg', 1, $filterdata->stagedonly) . get_string('filtermisfstg', 'local_amos'),
            array('class' => 'checkbox'.$collapsible)
        );

        $collapsible = empty($filterdata->greylistedonly) ? ' collapsible collapsed' : '';
        $output .= html_writer::tag('label',
            html_writer::checkbox('fglo', 1, $filterdata->greylistedonly, '', array('id' => 'amosfilter_fglo')) . get_string('filtermisfglo', 'local_amos'),
            array('class' => 'checkbox'.$collapsible)
        );

        $collapsible = empty($filterdata->withoutgreylisted) ? ' collapsible collapsed' : '';
        $output .= html_writer::tag('label',
            html_writer::checkbox('fwog', 1, $filterdata->withoutgreylisted, '', array('id' => 'amosfilter_fwog')) . get_string('filtermisfwog', 'local_amos'),
            array('class' => 'checkbox'.$collapsible)
        );

        $output .= html_writer::end_tag('div'); // .controls
        $output .= html_writer::end_tag('div'); // .control-group

        // must contain string
        $collapsible = empty($filterdata->substring) ? ' collapsible collapsed' : '';
        $output .= html_writer::start_tag('div', array('class' => 'control-group'.$collapsible));
        $output .= html_writer::tag('label',
            get_string('filtertxt', 'local_amos') . html_writer::tag('div', get_string('filtertxt_desc', 'local_amos'), array('class' => 'help-block')),
            array('class' => 'control-label', 'for' => 'amosfilter_ftxt')
        );

        $output .= html_writer::start_tag('div', array('id' => 'amosfilter_ftxt', 'class' => 'controls'));

        $output .= html_writer::empty_tag('input', array('name' => 'ftxt', 'type' => 'text', 'value' => $filterdata->substring));

        $output .= html_writer::tag('label',
            html_writer::checkbox('ftxr', 1, $filterdata->substringregex) . get_string('filtertxtregex', 'local_amos'),
            array('class' => 'checkbox')
        );

        $output .= html_writer::tag('label',
            html_writer::checkbox('ftxs', 1, $filterdata->substringcs) . get_string('filtertxtcasesensitive', 'local_amos'),
            array('class' => 'checkbox')
        );

        $output .= html_writer::end_tag('div'); // .controls
        $output .= html_writer::end_tag('div'); // .control-group

        // string identifier
        $collapsible = empty($filterdata->stringid) ? ' collapsible collapsed' : '';
        $output .= html_writer::start_tag('div', array('class' => 'control-group'.$collapsible));
        $output .= html_writer::tag('label',
            get_string('filtersid', 'local_amos') . html_writer::tag('div', get_string('filtersid_desc', 'local_amos'), array('class' => 'help-block')),
            array('class' => 'control-label', 'for' => 'amosfilter_fsid')
        );

        $output .= html_writer::start_tag('div', array('id' => 'amosfilter_fsid', 'class' => 'controls'));

        $output .= html_writer::empty_tag('input', array('name' => 'fsid', 'type' => 'text', 'value' => $filterdata->stringid));

        $output .= html_writer::tag('label',
            html_writer::checkbox('fsix', 1, $filterdata->stringidpartial) . get_string('filtersidpartial', 'local_amos'),
            array('class' => 'checkbox')
        );

        $output .= html_writer::end_tag('div'); // .controls
        $output .= html_writer::end_tag('div'); // .control-group

        // hidden fields
        $output .= html_writer::start_tag('div');
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => '__lazyform_' . $filter->lazyformname, 'value' => 1));
        $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
        $output .= html_writer::end_tag('div');

        // submit and permalink
        $output .= html_writer::start_tag('div', array('class' => 'form-actions'));
        $output .= html_writer::tag('button', get_string('savefilter', 'local_amos'), array('class' => 'btn btn-primary', 'type' => 'submit'));
        $output .= html_writer::tag('span', '', array('id' => 'amosfilter_submitted_icon'));
        $permalink = $filter->get_permalink();
        if (!is_null($permalink)) {
            $output .= html_writer::link($permalink, get_string('permalink', 'local_amos'), array('class' => 'permalink btn btn-link'));
        }
        $output .= html_writer::span('', 'collapsible-control');
        $output .= html_writer::end_tag('div');

        // alerts
        if (!empty($alerts)) {
            $alertsout = '';
            foreach ($alerts as $alert) {
                $alertsout .= html_writer::div($alert, 'alert alert-error');
            }
            $output = $alertsout . $output;
        }

        // block wrapper
        $output = html_writer::tag('fieldset', $output, array('id' => 'amosfilter'));

        // form
        $attributes = array('method' => 'post',
                            'action' => $filter->handler->out(),
                            'id'     => 'amosfilter_form',
                            'class'  => 'lazyform form-horizontal ' . $filter->lazyformname,
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
        global $PAGE;

        $listlanguages = mlang_tools::list_languages();

        if (empty($translator->strings)) {
            if ($translator->currentpage > 1) {
                $output  = $this->heading(get_string('nostringsfoundonpage', 'local_amos', $translator->currentpage));
                $output .= html_writer::tag('div',
                        html_writer::link(new moodle_url($PAGE->url, array('fpg' => 1)), get_string('gotofirst', 'local_amos')) . ' | '.
                        html_writer::link(new moodle_url($PAGE->url, array('fpg' => $translator->currentpage - 1)), get_string('gotoprevious', 'local_amos')),
                        array('style' => 'text-align:center'));
                $output = html_writer::tag('div', $output, array('class' => 'translatorwrapper no-overflow'));
            } else {
                $output = $this->heading(get_string('nostringsfound', 'local_amos'));
                $output = html_writer::tag('div', $output, array('class' => 'translatorwrapper no-overflow'));
            }
            return $output;
        }

        $trout = '';
        $missing = 0;
        $standard = array();

        foreach (local_amos_standard_plugins() as $plugins) {
            $standard = array_merge($standard, $plugins);
        }

        foreach ($translator->strings as $string) {

            // string information
            $infoversion = html_writer::span($string->branchlabel, 'info info-version');

            $infostringid = html_writer::span($string->stringid, 'info info-stringid');

            if (isset($standard[$string->component])) {
                $infocomponent = $standard[$string->component];
            } else {
                $infocomponent = $string->component;
            }
            $infocomponent = html_writer::span($infocomponent, 'info info-component');

            $infoplaceholder = '';
            if (preg_match('/\{\$a(->.+)?\}/', $string->original)) {
                $infoplaceholder = html_writer::span($this->help_icon('placeholder', 'local_amos', get_string('placeholderwarning', 'local_amos')),
                    'info info-placeholder');
            }

            $infogreylisted = '';
            if ($string->greylisted) {
                $infogreylisted = html_writer::span($this->help_icon('greylisted', 'local_amos', get_string('greylistedwarning', 'local_amos')),
                   'info info-greylisted');
            }

            $infolanguage = html_writer::span($listlanguages[$string->language], 'info info-language');

            $infotimeline = html_writer::span(html_writer::tag('a', get_string('timeline', 'local_amos'), array(
                'target' => '_blank',
                'title' => get_string('stringhistory', 'local_amos'),
                'href' => new moodle_url('/local/amos/timeline.php', array(
                    'component' => $string->component,
                    'language'  => $string->language,
                    'branch'    => $string->branchcode,
                    'stringid'  => $string->stringid
                ))
            )), 'info info-timeline');

            if (has_capability('local/amos:usegoogle', context_system::instance())) {
                $infogoogle = html_writer::span('', 'info info-google');
            } else {
                $infogoogle = '';
            }

            // original of the string
            $original = self::add_breaks(s($string->original));
            // work around https://bugzilla.mozilla.org/show_bug.cgi?id=116083
            $original = nl2br($original);
            $original = str_replace(array("\n", "\r"), '', $original);
            $original = html_writer::div($original, 'preformatted english');

            if ($infogoogle) {
                $original .= html_writer::div('', 'google-translation');
            }

            // translation
            if (is_null($string->translation)) {
                $missing++;
            }
            $translation = self::add_breaks(s($string->translation));
            $translation = html_writer::tag('div', $translation, array('class' => 'preformatted translation-view'));

            $trclasses = ' ' . $string->class;
            if ($string->translatable) {
                $trclasses .= ' translatable';
            } else {
                $trclasses .= ' nontranslatable';
            }
            if ($string->committable) {
                $trclasses .= ' committable';
            }
            if ($string->outdated) {
                $trclasses .= ' outdated';
            }

            // mark as up-to-date
            if ($string->outdated and $string->committable and $string->translation) {
                if ($string->translationid) {
                    $uptodate = html_writer::tag('button',
                        get_string('markuptodatelabel', 'local_amos'),
                        array('id' => 'uptodate_'.$string->translationid, 'class' => 'btn btn-small btn-warning markuptodate'));
                    $uptodate .= $this->help_icon('markuptodate', 'local_amos');
                } else {
                    $uptodate = $this->help_icon('outdatednotcommitted', 'local_amos', get_string('outdatednotcommittedwarning', 'local_amos'));
                }
                $uptodate = html_writer::div($uptodate, 'uptodatewrapper');
            } else {
                $uptodate = '';
            }

            // info lines
            $infoline1 = $infoversion.' | '.$infostringid.' | '.$infocomponent;

            if ($infogreylisted) {
                $infoline1 .= ' | '.$infogreylisted;
            }

            if ($infoplaceholder) {
                $infoline1 .= ' | '.$infoplaceholder;
            }

            $infoline2 = $this->help_icon('translatortranslation', 'local_amos') . ' ' . $infolanguage.' | '.$infotimeline . ' ' . $infogoogle;

            $data = array(
                'data-amos-lang' => $string->language,
                'data-amos-originalid' => $string->originalid,
            );

            if (!empty($string->translationid)) {
                $data['data-amos-translationid'] = $string->translationid;
            }

            $trout .= html_writer::start_div('string-control-group', $data);
            $trout .= html_writer::start_div('string-control-label');
            $trout .= html_writer::start_div('row-fluid');
            $trout .= html_writer::div($infoline1, 'span6');
            $trout .= html_writer::div($infoline2, 'span6');
            $trout .= html_writer::end_div(); // .row-fluid
            $trout .= html_writer::end_div(); // .string-control-label

            $trout .= html_writer::start_div('string-controls');
            $trout .= html_writer::start_div('row-fluid');
            $trout .= html_writer::div(html_writer::div($original, 'string-text original'), 'span6');
            $trout .= html_writer::div(html_writer::div($translation.$uptodate, 'string-text translation' . $trclasses), 'span6');
            $trout .= html_writer::end_div(); // .row-fluid
            $trout .= html_writer::end_div(); // .string-controls
            $trout .= html_writer::end_div(); // .string-control-group
        }

        $a = array(
            'found' => $translator->numofrows,
            'missing' => $translator->numofmissing,
            'missingonpage' => $missing
        );
        $output = $this->heading_with_help(get_string('found', 'local_amos', $a), 'foundinfo', 'local_amos');
        $pages = ceil($translator->numofrows / local_amos_translator::PERPAGE);
        $output .= html_writer::tag('div', self::page_links($pages, $translator->currentpage), array('class' => 'pagination'));
        $output .= html_writer::div($trout, '', array('id' => 'amostranslator'));
        $output .= html_writer::tag('div', self::page_links($pages, $translator->currentpage), array('class' => 'pagination'));
        $output = html_writer::tag('div', $output, array('class' => 'translatorwrapper no-overflow'));

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

        /*
                get_string('stagestring', 'local_amos'),
                get_string('stageoriginal', 'local_amos'),
                get_string('stagelang', 'local_amos'),
                get_string('stagetranslation', 'local_amos') . $this->help_icon('stagetranslation', 'local_amos'));
         */

        $listlanguages = mlang_tools::list_languages();
        $committable = 0;
        $standard = array();
        $trout = '';

        foreach (local_amos_standard_plugins() as $plugins) {
            $standard = array_merge($standard, $plugins);
        }

        foreach ($stage->strings as $string) {
            $cells = array();

            // string identification
            $infoversion = html_writer::span($string->version, 'info info-version');

            $infostringid = html_writer::span($string->stringid, 'info info-stringid');

            if (isset($standard[$string->component])) {
                $infocomponent = $standard[$string->component];
            } else {
                $infocomponent = $string->component;
            }
            $infocomponent = html_writer::span($infocomponent, 'info info-component');

            $infolanguage = html_writer::span($listlanguages[$string->language], 'info info-language');

            // original of the string
            $original = self::add_breaks(s($string->original));
            // work around https://bugzilla.mozilla.org/show_bug.cgi?id=116083
            $original = nl2br($original);
            $original = str_replace(array("\n", "\r"), '', $original);
            $original = html_writer::div($original, 'preformatted english');

            // unnstage button
            $unstageurl = new moodle_url('/local/amos/stage.php', array(
                'unstage'   => $string->stringid,
                'component' => $string->component,
                'branch'    => $string->branch,
                'lang'      => $string->language,
                'sesskey'   => sesskey(),
            ));
            $unstagebutton = html_writer::link($unstageurl, get_string('unstage', 'local_amos'), array(
                'class' => 'btn btn-warning btn-small protected unstagebutton',
                'data-unstage' => $string->stringid,
                'data-component' => $string->component,
                'data-branch' => $string->branch,
                'data-lang' => $string->language,
                'data-sesskey' => sesskey(),
            ));

            // edit string button
            $editurl = new moodle_url('/local/amos/view.php', array(
                't' => time(),
                'v' => $string->branch,
                'l' => $string->language,
                'c' => $string->component,
                'd' => $string->stringid,
            ));
            $editbutton = html_writer::link($editurl, get_string('edit'),
                array('class' => 'btn btn-small editbutton'));

            $trclasses = ' ';

            if ($string->deleted) {
                // removal of the string
                if ($string->committable) {
                    $committable++;
                    $trclasses .= ' committable removal';
                } else {
                    $trclasses .= ' uncommittable removal';
                }
                $t = html_writer::div(self::add_breaks(s($string->current)), 'preformatted');
                $t .= html_writer::div($unstagebutton.' '.$editbutton, 'translationactions');
            } else if (is_null($string->current)) {
                // new translation
                if ($string->committable) {
                    $trclasses .= ' committable new';
                    $committable++;
                }
                if (!$string->committable) {
                    $trclasses .= ' uncommittable new';
                }
                $t = html_writer::div(self::add_breaks(s($string->new)), 'preformatted');
                $t .= html_writer::div($unstagebutton.' '.$editbutton, 'translationactions');
            } else if (trim($string->current) === trim($string->new)) {
                // no difference
                $trclasses .= ' uncommittable nodiff';
                $t = html_writer::div(self::add_breaks(s($string->current)), 'preformatted');
                $t .= html_writer::div($unstagebutton.' '.$editbutton, 'translationactions');
            } else {
                // there is a difference
                if ($string->committable) {
                    $trclasses .= ' committable diff';
                    $committable++;
                }
                if (!$string->committable) {
                    $trclasses .= ' uncommittable diff';
                }

                $c = s($string->current);
                $n = s($string->new);
                $x1 = explode(' ', $c);
                $x2 = explode(' ', $n);

                $t = '';
                $diff = local_amos_simplediff($x1, $x2);
                $numd = 0;
                $numi = 0;
                foreach ($diff as $k) { // $diff is a sequence of chunks (words) $k
                    if (is_array($k)) {
                        if (!empty($k['d'])) {
                            $kd = implode(' ', $k['d']);
                            if (!empty($kd)) {
                                $t .= '<del>'.$kd.'</del> ';
                                $numd += count($k['d']);
                            }
                        }
                        if (!empty($k['i'])) {
                            $ki = implode(' ', $k['i']);
                            if (!empty($ki)) {
                                $t .= '<ins>'.$ki.'</ins> ';
                                $numi += count($k['i']);
                            }
                        }
                    } else {
                        $t .= $k . ' ';
                    }
                }

                if ($numi == 0 or $numd == 0 or ($numd == 1 and $numi == 1)) {
                    $cstyle = 'display:none;';
                    $nstyle = 'display:none;';
                    $tstyle = 'display:block;';
                } else {
                    $cstyle = 'display:block;';
                    $nstyle = 'display:block;';
                    $tstyle = 'display:none;';
                }
                $n = html_writer::tag('ins', $n);
                $n = html_writer::tag('div', self::add_breaks($n), array('class' => 'preformatted stringtext new', 'style' => $nstyle));
                $c = html_writer::tag('del', $c);
                $c = html_writer::tag('div', self::add_breaks($c), array('class' => 'preformatted stringtext current', 'style' => $cstyle));
                $t = html_writer::tag('div', self::add_breaks($t), array('class' => 'preformatted stringtext diff', 'style' => $tstyle));
                $t = html_writer::div($n . $c . $t);
                $t .= html_writer::div($unstagebutton.' '.$editbutton, 'translationactions');
            }

            // info lines
            $infoline1 = $infoversion.' | '.$infostringid.' | '.$infocomponent;

            $infoline2 = $this->help_icon('stagetranslation', 'local_amos') . ' ' . $infolanguage;

            $data = array(
                'data-amos-lang' => $string->language,
            );

            if (!empty($string->translationid)) {
                $data['data-amos-translationid'] = $string->translationid;
            }

            $trout .= html_writer::start_div('string-control-group', $data);
            $trout .= html_writer::start_div('string-control-label');
            $trout .= html_writer::start_div('row-fluid');
            $trout .= html_writer::div($infoline1, 'span6');
            $trout .= html_writer::div($infoline2, 'span6');
            $trout .= html_writer::end_div(); // .row-fluid
            $trout .= html_writer::end_div(); // .string-control-label

            $trout .= html_writer::start_div('string-controls');
            $trout .= html_writer::start_div('row-fluid');
            $trout .= html_writer::div(html_writer::div($original, 'string-text original'), 'span6');
            $trout .= html_writer::div(html_writer::div($t, 'string-text translation' . $trclasses), 'span6');
            $trout .= html_writer::end_div(); // .row-fluid
            $trout .= html_writer::end_div(); // .string-controls
            $trout .= html_writer::end_div(); // .string-control-group
        }

        if ($stage->showpropagateform) {
            $propagateform  = html_writer::empty_tag('input', array('name' => 'sesskey', 'value' => sesskey(), 'type' => 'hidden'));
            $propagateform .= html_writer::empty_tag('input', array('name' => 'propagate', 'value' => 1, 'type' => 'hidden'));
            foreach (mlang_version::list_all() as $version) {
                if ($version->code < 2000) {
                    continue;
                }
                $checkbox = html_writer::checkbox('ver[]', $version->code, true, $version->label);
                $propagateform .= html_writer::tag('div', $checkbox, array('class' => 'labelled_checkbox'));
            }
            $propagateform .= html_writer::empty_tag('input', array('value' => get_string('propagaterun', 'local_amos'), 'type' => 'submit'));
            $propagateform  = html_writer::tag('div', $propagateform);
            $propagateform  = html_writer::tag('form', $propagateform, array('method' => 'post', 'action' => $CFG->wwwroot . '/local/amos/stage.php'));
            $propagateform .= html_writer::tag('legend', get_string('propagate', 'local_amos') . $this->help_icon('propagate', 'local_amos'));
            $propagateform = html_writer::tag('fieldset', $propagateform, array('class' => 'propagateformwrapper protected'));
        } else {
            $propagateform = '';
        }

        $commitform  = html_writer::label(get_string('commitmessage', 'local_amos'), 'commitmessage', false);
        $commitform .= html_writer::empty_tag('img', array('src' => $this->pix_url('req'), 'title' => 'Required', 'alt' => 'Required', 'class' => 'req'));
        $commitform .= html_writer::empty_tag('br');
        $commitform .= html_writer::tag('textarea', s($stage->presetmessage), array('id' => 'commitmessage', 'name' => 'message'));
        $commitform .= html_writer::empty_tag('input', array('name' => 'sesskey', 'value' => sesskey(), 'type' => 'hidden'));
        $button1 = html_writer::empty_tag('input', array('name' => 'commit1', 'value' => get_string('commitbutton', 'local_amos'), 'type' => 'submit'));
        $button2 = html_writer::empty_tag('input', array('name' => 'commit2', 'value' => get_string('commitbutton2', 'local_amos'), 'type' => 'submit'));
        $button = html_writer::tag('div', $button1.' '.$button2);
        $commitform = html_writer::tag('div', $commitform . $button);
        $commitform = html_writer::tag('form', $commitform, array('method' => 'post', 'action' => $CFG->wwwroot . '/local/amos/stage.php'));
        $commitform .= html_writer::tag('legend', get_string('commitstage', 'local_amos') . $this->help_icon('commitstage', 'local_amos'));
        $commitform = html_writer::tag('fieldset', $commitform, array('class' => 'commitformwrapper protected'));

        $a = new stdClass();
        $a->time = userdate(time(), get_string('strftimedaydatetime', 'langconfig'));
        $stashtitle = get_string('stashtitledefault', 'local_amos', $a);

        $stashform  = html_writer::label(get_string('stashtitle', 'local_amos'), 'stashtitle', true);
        $stashform .= html_writer::empty_tag('input', array('name' => 'sesskey', 'value' => sesskey(), 'type' => 'hidden'));
        $stashform .= html_writer::empty_tag('input', array('name' => 'new', 'value' => 1, 'type' => 'hidden'));
        $stashform .= html_writer::empty_tag('input', array('name' => 'name',
                                                            'value' => $stashtitle,
                                                            'type' => 'text',
                                                            'size' => 50,
                                                            'id' => 'stashtitle',
                                                            'maxlength' => 255));
        $stashform .= html_writer::empty_tag('input', array('value' => get_string('stashpush', 'local_amos'), 'type' => 'submit'));
        $stashform  = html_writer::tag('div', $stashform);
        $stashform  = html_writer::tag('form', $stashform, array('method' => 'post', 'action' => $CFG->wwwroot . '/local/amos/stash.php'));
        $stashform  = html_writer::tag('div', $stashform, array('class' => 'stashformwrapper'));

        $submiturl = new moodle_url('/local/amos/stage.php', array('submit' => 1));
        $submitbutton = $this->single_button($submiturl, get_string('stagesubmit', 'local_amos'), 'post', array('class'=>'singlebutton submit'));

        $pruneurl = new moodle_url('/local/amos/stage.php', array('prune' => 1));
        $prunebutton = $this->single_button($pruneurl, get_string('stageprune', 'local_amos'), 'post', array('class'=>'singlebutton protected prune'));

        $rebaseurl = new moodle_url('/local/amos/stage.php', array('rebase' => 1));
        $rebasebutton = $this->single_button($rebaseurl, get_string('stagerebase', 'local_amos'), 'post', array('class'=>'singlebutton protected rebase'));

        $unstageallurl = new moodle_url('/local/amos/stage.php', array('unstageall' => 1));
        $unstageallbutton = $this->single_button($unstageallurl, get_string('stageunstageall', 'local_amos'), 'post', array('class'=>'singlebutton protected unstageall'));

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
        $editbutton = $this->single_button($editurl, get_string('stageedit', 'local_amos'), 'post', array('class'=>'singlebutton edit'));

        if (empty($stage->strings)) {
            $output = $this->heading(get_string('stagestringsnone', 'local_amos'), 2, 'main', 'numberofstagedstrings');

            if ($stage->importform) {
                $legend = html_writer::tag('legend', get_string('importfile', 'local_amos') . $this->help_icon('importfile', 'local_amos'));
                ob_start();
                $stage->importform->display();
                $importform = ob_get_contents();
                ob_end_clean();
                $output .= html_writer::tag('fieldset', $legend.$importform, array('class' => 'wrappedmform importform'));
            }

            if ($stage->mergeform) {
                $legend = html_writer::tag('legend', get_string('mergestrings', 'local_amos') . $this->help_icon('mergestrings', 'local_amos'));
                ob_start();
                $stage->mergeform->display();
                $mergeform = ob_get_contents();
                ob_end_clean();
                $output .= html_writer::tag('fieldset', $legend.$mergeform, array('class' => 'wrappedmform mergeform'));
            }

            if ($stage->diffform) {
                $legend = html_writer::tag('legend', get_string('diffstrings', 'local_amos') . $this->help_icon('diffstrings', 'local_amos'));
                ob_start();
                $stage->diffform->display();
                $diffform = ob_get_contents();
                ob_end_clean();
                $output .= html_writer::tag('fieldset', $legend.$diffform, array('class' => 'wrappedmform diffform'));
            }

            if ($stage->executeform) {
                $legend = html_writer::tag('legend', get_string('script', 'local_amos') . $this->help_icon('script', 'local_amos'));
                ob_start();
                $stage->executeform->display();
                $executeform = ob_get_contents();
                ob_end_clean();
                $output .= html_writer::tag('fieldset', $legend.$executeform, array('class' => 'wrappedmform executeform'));
            }

        } else {
            $output = '';
            if (!empty($stage->stagedcontribution)) {
                $output .= $this->heading_with_help(get_string('contribstaged', 'local_amos', $stage->stagedcontribution),
                    'contribstagedinfo', 'local_amos');
            }
            $a = (object)array('staged' => count($stage->strings), 'committable' => $committable);
            if ($committable) {
                $output .= $this->heading(get_string('stagestringssome', 'local_amos', $a), 2, 'main', 'numberofstagedstrings');
            } else {
                $output .= $this->heading(get_string('stagestringsnocommit', 'local_amos', $a), 2, 'main', 'numberofstagedstrings');
            }
            unset($a);

            $justpropagated = optional_param('justpropagated', null, PARAM_INT); // usability hack to hide the propagator just after it was used
            if (is_null($justpropagated)) {
                $output .= $propagateform;
            } else if ($justpropagated == 0) {
                $output .= $this->heading(get_string('propagatednone', 'local_amos'));
            } else {
                $output .= $this->heading(get_string('propagatedsome', 'local_amos', $justpropagated));
            }

            if ($committable) {
                $output .= $commitform;
            }

            $legend = html_writer::tag('legend', get_string('stageactions', 'local_amos') . $this->help_icon('stageactions', 'local_amos'));
            $actionbuttons = $legend.$submitbutton.$editbutton;
            if ($committable) {
                $actionbuttons .= $prunebutton.$rebasebutton;
            }
            $actionbuttons .= $unstageallbutton;
            $output .= html_writer::tag('fieldset', $actionbuttons, array('class' => 'actionbuttons'));

            $legend = html_writer::tag('legend', get_string('stashactions', 'local_amos') . $this->help_icon('stashactions', 'local_amos'));
            $output .= html_writer::tag('fieldset', $legend.$stashform, array('class' => 'actionbuttons'));

            $output .= html_writer::div($trout, '', array('id' => 'amosstage'));
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

        if ($log->numofcommits == 0) {
            return $this->heading(get_string('nologsfound', 'local_amos'));
        }

        $a = (object)array('found' => $log->numofcommits, 'limit' => local_amos_log::LIMITCOMMITS);
        if ($log->numofcommits > local_amos_log::LIMITCOMMITS) {
            $output = $this->heading(get_string('numofcommitsabovelimit', 'local_amos', $a));
        } else {
            $output = $this->heading(get_string('numofcommitsunderlimit', 'local_amos', $a));
        }

        $a = (object)array('strings' => $log->numofstrings, 'commits' => count($log->commits));
        $output .= $this->heading(get_string('numofmatchingstrings', 'local_amos', $a));

        $standard = array();
        foreach (local_amos_standard_plugins() as $plugins) {
            $standard = array_merge($standard, $plugins);
        }

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
                if (isset($standard[$string->component])) {
                    $component = $standard[$string->component];
                } else {
                    $component = $string->component;
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
require("/var/www/vhosts/download.moodle.org/html/config.php");
require("/var/www/vhosts/download.moodle.org/html/menu.php");

print_header("Moodle: Download: Language Packs", "Moodle Downloads",
        "<a href=\"$CFG->wwwroot/\">Download</a> -> Language Packs",
        "", "", true, " ", $navmenu);
$current = "lang";
require("/var/www/vhosts/download.moodle.org/html/tabs.php");

print_simple_box_start("center", "100%", "#FFFFFF", 20);
?>';
        $output .= $this->heading('Moodle '.$data->version->label.' language packs');
        $output .= html_writer::tag('p', 'These zip files are generated automatically from the work of translators in the
            <a href=" http://lang.moodle.org/">Moodle languages portal</a>. Contact details for language pack maintainers are
            listed in the <a href="http://docs.moodle.org/en/Translation_credits">Translation credits</a>.');
        $output .= html_writer::tag('p', 'Note: All language packs are work-in-progress, as developers continue to add new language strings to Moodle. The most commonly used strings are generally translated first.');
        $output .= html_writer::tag('h3', 'Language pack installation');
        $output .= html_writer::tag('p', 'To install additional language packs on your Moodle site, access Site Administration > Language > Language packs then select the languages you require and click on the "Install selected language pack" button.');
        $output .= html_writer::tag('p', 'For further information, including details of how to install language packs manually, please refer to the <a href="http://docs.moodle.org/en/Language_packs">Language packs documentation</a>.');
        $output .= html_writer::tag('p', 'Moodle is available in English by default. Translation work has started on '.$data->numoflangpacks.' language packs for Moodle '.$data->version->label.'. There are currently:');
        $output .= html_writer::start_tag('table', array('border' => 0, 'width' => '80%', 'style' => 'margin:0.5em auto;'));
        $output .= html_writer::start_tag('tr');
        $output .= html_writer::tag('td', $data->percents['80'].' languages with more than 80% translated', array('style'=>'background-color:#e7f1c3;'));
        $output .= html_writer::tag('td', $data->percents['60'].' languages with more than 60% translated', array('style'=>'background-color:#d2ebff;'));
        $output .= html_writer::tag('td', $data->percents['40'].' languages with more than 40% translated', array('style'=>'background-color:#f3f2aa;'));
        $output .= html_writer::tag('td', $data->percents['0'].' languages with less than 40% translated', array('style'=>'background-color:#ffd3d9;'));
        $output .= html_writer::end_tag('tr');
        $output .= html_writer::end_tag('table');
        $table = new html_table();
        $table->head = array('Language', 'Download', 'Size', 'Last updated', 'Percentage of language strings translated');
        $table->width = '100%';
        foreach ($data->langpacks as $langcode => $langpack) {
            $row = array();
            if ($langpack->parent == 'en' and (substr($langcode, 0, 3) != 'en_')) {
                // standard pack without parent
                $row[0] = $langpack->langname;
            } else {
                $row[0] = html_writer::tag('em', $langpack->langname, array('style'=>'margin-left:1em;'));
            }
            $row[1] = '<a href="http://download.moodle.org/download.php/langpack/'.$data->version->dir.'/'.$langpack->filename.'">'.$langpack->filename.'</a>';
            $row[2] = display_size($langpack->filesize);
            if (time() - $langpack->modified < WEEKSECS) {
                $row[3] = html_writer::tag('strong', self::commit_datetime($langpack->modified), array('class'=>'recentlymodified'));
            } else {
                $row[3] = self::commit_datetime($langpack->modified);
            }
            if (($langpack->parent == 'en') and (substr($langcode, 0, 3) != 'en_')) {
                // standard package
                if (!is_null($langpack->ratio)) {
                    $barmax = 500; // pixels
                    $barwidth = floor($barmax * $langpack->ratio);
                    $barvalue = sprintf('%d %%', $langpack->ratio * 100).' <span style="color:#666">('.$langpack->totaltranslated.'/'.$data->totalenglish.')</span>';
                    if ($langpack->ratio >= 0.8) {
                        $bg = '#e7f1c3'; // green
                    } elseif ($langpack->ratio >= 0.6) {
                        $bg = '#d2ebff'; // blue
                    } elseif ($langpack->ratio >= 0.4) {
                        $bg = '#f3f2aa'; // yellow
                    } else {
                        $bg = '#ffd3d9'; // red
                    }
                    $row[4] = '<div style="width:100%">
                                <div style="width:'.$barwidth.'px;background-color:'.$bg.';float:left;margin-right: 5px;">&nbsp;</div><span>'.$barvalue.'</span></div>';
                } else {
                    $row[4] = '';
                }
            } else {
                // variant of parent language
                $row[4] = $langpack->totaltranslated.' changes from '.$langpack->parent;
            }
            $table->data[] = $row;
        }
        $output .= html_writer::table($table);
        $output .= '<div style="margin-top: 1em; text-align:center;">';
        $output .= html_writer::tag('em', 'Generated: '.self::commit_datetime($data->timemodified), array('class'=>'timemodified'));
        $output .= "</div>\n";
        $output .= '<?php
print_simple_box_end();
print_footer();
';

        return $output;
    }

    /**
     * Render stash information
     *
     * @param local_amos_stash $stash
     * @return string to be echo'ed
     */
    protected function render_local_amos_stash(local_amos_stash $stash) {

        $output  = html_writer::start_tag('div', array('class' => 'stash'));
        if ($stash->isautosave) {
            $output .= html_writer::tag('h3', get_string('stashautosave', 'local_amos'));
            $extraclasses = ' autosave';
        } else {
            $output .= html_writer::tag('h3', s($stash->name));
            $extraclasses = '';
        }
        $output .= html_writer::tag('div', fullname($stash->owner), array('class' => 'owner'));
        $output .= $this->output->user_picture($stash->owner);
        $output .= html_writer::tag('div', userdate($stash->timecreated, get_string('strftimedaydatetime', 'langconfig')),
                                    array('class' => 'timecreated'));
        $output .= html_writer::tag('div', get_string('stashstrings', 'local_amos', $stash->strings),
                                    array('class' => 'strings'));
        $output .= html_writer::tag('div', get_string('stashlanguages', 'local_amos', s(implode(', ', $stash->languages))),
                                    array('class' => 'languages'));
        $output .= html_writer::tag('div', get_string('stashcomponents', 'local_amos', s(implode(', ', $stash->components))),
                                    array('class' => 'components'));

        $output .= html_writer::end_tag('div');

        $actions = '';
        foreach ($stash->get_actions() as $action) {
            $actions .= $this->output->single_button($action->url, $action->label, 'post', array('class'=>'singlebutton '.$action->id));
        }
        if ($actions) {
            $actions .= $this->output->help_icon('ownstashactions', 'local_amos');
            $actions = html_writer::tag('div', $actions, array('class' => 'actions'));
        }
        $output = $this->output->box($output . $actions, 'generalbox stashwrapper'.$extraclasses);

        return $output;
    }

    /**
     * Render single contribution record
     *
     * @param local_amos_contribution $contribution
     * @return string
     */
    protected function render_local_amos_contribution(local_amos_contribution $contrib) {
        global $USER;

        $output = '';
        $output .= $this->output->heading('#'.$contrib->info->id.' '.s($contrib->info->subject), 3, 'subject');
        $output .= $this->output->container($this->output->user_picture($contrib->author) . fullname($contrib->author), 'author');
        $output .= $this->output->container(userdate($contrib->info->timecreated, get_string('strftimedaydatetime', 'langconfig')), 'timecreated');
        $output .= $this->output->container(format_text($contrib->info->message), 'message');
        $output = $this->box($output, 'generalbox source');

        $table = new html_table();
        $table->attributes['class'] = 'generaltable details';

        $row = new html_table_row(array(
            get_string('contribstatus', 'local_amos'),
            get_string('contribstatus'.$contrib->info->status, 'local_amos') . $this->output->help_icon('contribstatus', 'local_amos')));
        $row->attributes['class'] = 'status'.$contrib->info->status;
        $table->data[] = $row;

        if ($contrib->assignee) {
            $assignee = $this->output->user_picture($contrib->assignee, array('size' => 16)) . fullname($contrib->assignee);
        } else {
            $assignee = get_string('contribassigneenone', 'local_amos');
        }
        $row = new html_table_row(array(get_string('contribassignee', 'local_amos'), $assignee));
        if ($contrib->assignee) {
            if ($contrib->assignee->id == $USER->id) {
                $row->attributes['class'] = 'assignment self';
            } else {
                $row->attributes['class'] = 'assignment';
            }
        } else {
            $row->attributes['class'] = 'assignment none';
        }
        $table->data[] = $row;

        $row = new html_table_row(array(get_string('contriblanguage', 'local_amos'), $contrib->language));
        $table->data[] = $row;

        $row = new html_table_row(array(get_string('contribcomponents', 'local_amos'), $contrib->components));
        $table->data[] = $row;

        $a = array('orig'=>$contrib->strings, 'new'=>$contrib->stringsreb, 'same'=>($contrib->strings - $contrib->stringsreb));
        if ($contrib->stringsreb == 0) {
            $s = get_string('contribstringsnone', 'local_amos', $a);
        } else if ($contrib->strings == $contrib->stringsreb) {
            $s = get_string('contribstringseq', 'local_amos', $a);
        } else {
            $s = get_string('contribstringssome', 'local_amos', $a);
        }
        $row = new html_table_row(array(get_string('contribstrings', 'local_amos'), $s));
        $table->data[] = $row;

        $output .= html_writer::table($table);
        $output = $this->output->container($output, 'contributionwrapper');

        return $output;
    }

    /**
     * Renders the AMOS credits page
     *
     * @param array $people as populated in credits.php
     * @param string $currentlanguage the user's current language
     * @return string
     */
    public function page_credits(array $people, $currentlanguage) {

        $out = $this->output->heading(get_string('creditstitlelong', 'local_amos'));
        $out .= $this->output->container(get_string('creditsthanks', 'local_amos'), 'thanks');

        $out .= $this->output->container_start('quicklinks');
        $links = array();
        foreach ($people as $langcode => $langdata) {
            if ($langcode === $currentlanguage) {
                $attributes = array('class' => 'current');
            } else {
                $attributes = null;
            }

            $links[] = html_writer::link(new moodle_url('#credits-language-'.$langcode),
                str_replace(' ', '&nbsp;', $langdata->langname), $attributes);
        }
        $out .= implode(' | ', $links);
        $out .= $this->output->container_end();

        foreach ($people as $langcode => $langdata) {
            $out .= $this->output->container_start('language', 'credits-language-'.$langcode);
            $out .= $this->output->heading($langdata->langname, 3, 'langname');

            $out .= $this->output->container_start('maintainers');
            if (empty($langdata->maintainers)) {
                $out .= $this->output->container(get_string('creditsnomaintainer', 'local_amos', array('url' => 'http://docs.moodle.org/en/Translation')));
            } else {
                $out .= $this->output->container(get_string('creditsmaintainedby', 'local_amos'), 'maintainers-title');
                foreach ($langdata->maintainers as $maintainer) {
                    $out .= $this->output->container_start('maintainer');
                    $out .= $this->output->user_picture($maintainer, array('size' => 50));
                    $out .= $this->output->container(fullname($maintainer), 'fullname');
                    $out .= $this->output->action_icon(
                        new moodle_url('/message/index.php', array('id' => $maintainer->id)),
                        new pix_icon('t/message', get_string('creditscontact', 'local_amos')),
                        null,
                        array('class' => 'contact')
                    );
                    $out .= $this->output->container_end();
                }
            }
            $out .= $this->output->container_end();

            $out .= $this->output->container_start('contributors');
            if (!empty($langdata->contributors)) {
                $out .= $this->output->container(get_string('creditscontributors', 'local_amos'), 'contributors-title');
                foreach ($langdata->contributors as $contributor) {
                    $out .= $this->output->container_start('contributor');
                    $out .= $this->output->user_picture($contributor, array('size' => 16));
                    $out .= $this->output->container(fullname($contributor), 'fullname');
                    $out .= $this->output->container_end();
                }
            }
            $out .= $this->output->container_end();

            $out .= $this->output->container_end();
        }

        return $out;
    }

    /**
     * Makes sure there is a zero-width space after non-word characters in the given string
     *
     * This is used to wrap long strings like 'A,B,C,D,...,x,y,z' in the translator
     *
     * @link http://www.w3.org/TR/html4/struct/text.html#h-9.1
     * @link http://www.fileformat.info/info/unicode/char/200b/index.htm
     *
     * @param string $text plain text
     * @return string
     */
    public static function add_breaks($text) {
        return preg_replace('/([,])(\S)/', '$1'."\xe2\x80\x8b".'$2', $text);
    }
}
