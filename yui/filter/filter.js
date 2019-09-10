/**
 * YUI module for AMOS translator filter
 *
 * @author  David Mudrak <david@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
YUI.add('moodle-local_amos-filter', function(Y) {

    /**
     * @class Filter
     * @constructor
     */
    function Filter() {
        Filter.superclass.constructor.apply(this, arguments);
    }

    Y.extend(Filter, Y.Base, {

        /**
         * The text currently entered into the search component box
         *
         * @property filtersearchneedle
         */
        filtersearchneedle: '',

        initializer : function(config) {
            this.init_filter();
            Y.log('Filter initialized', 'debug', 'moodle-local_amos-filter');
        },

        /**
         * Initialize JS support for the filter at the translator page
         */
        init_filter: function() {
            var filter      = Y.one('#amosfilter');

            // add select All / None links to the filter languages selector field
            var flng        = filter.one('#amosfilter_flng');
            var flngactions = filter.one('#amosfilter_flng_actions');
            flngactions.set('style', '');
            var flngselectall = filter.one('#amosfilter_flng_actions_all');
            flngselectall.on('click', function(e) {
                e.preventDefault();
                filter.all('.amosfilter_flng_actions_select').removeClass('active');
                flng.all('option').set('selected', true);
            });
            var flngselectnone = filter.one('#amosfilter_flng_actions_none');
            flngselectnone.on('click', function(e) {
                e.preventDefault();
                filter.all('.amosfilter_flng_actions_select').removeClass('active');
                flng.all('option').set('selected', false);
            });
            flng.all('option').on('click', function(e) {
                filter.all('.amosfilter_flng_actions_select').removeClass('active');
            });

            // add select All / None links to the filter components selector field
            var fcmp        = filter.one('#amosfilter_fcmp');
            var fcmpactions = filter.one('#amosfilter_fcmp_actions');
            fcmpactions.set('style', '');
            var fcmpenlarge = filter.one('#amosfilter_fcmp_actions_enlarge');
            fcmpenlarge.on('click', function(e) {
                // Enlarge the components selection box.
                e.preventDefault();
                fcmp.setStyle('height', parseInt(fcmp.getComputedStyle('height')) * 2 + 'px');
            });
            var fcmpselectallstandard = filter.one('#amosfilter_fcmp_actions_allstandard');
            fcmpselectallstandard.on('click', function(e) {
                // Check all displayed standard components.
                e.preventDefault();
                filter.all('.amosfilter_fcmp_actions_select').removeClass('active');
                fcmp.all('tr:not(.hidden) input').set('checked', false);
                fcmp.all('tr.standard:not(.hidden) input').set('checked', true);
            });
            var fcmpselectallapp = filter.one('#amosfilter_fcmp_actions_allapp');
            if (fcmpselectallapp) {
                fcmpselectallapp.on('click', function (e) {
                    // Check all displayed standard components.
                    e.preventDefault();
                    filter.all('.amosfilter_fcmp_actions_select').removeClass('active');
                    fcmp.all('tr:not(.hidden) input').set('checked', false);
                    fcmp.all('tr.app:not(.hidden) input').set('checked', true);
                    filter.one('#fapp input').set('checked', true);
                    filter.one('#amosfilter_fmis_collapse').removeClass('collapse');
                    filter.one('#fapp').ancestor().removeClass('collapse');
                    filter.all('#amosfilter_fver .fver').set('disabled', true);
                    filter.one('#amosfilter_fver_versions').addClass('hidden');
                    filter.one('#amosfilter_fver #flast').set('checked', true);
                });
            }
            var fcmpselectall = filter.one('#amosfilter_fcmp_actions_all');
            fcmpselectall.on('click', function(e) {
                // Check all displayed components.
                e.preventDefault();
                filter.all('.amosfilter_fcmp_actions_select').removeClass('active');
                fcmp.all('tr:not(.hidden) input').set('checked', true);
            });
            var fcmpselectnone = filter.one('#amosfilter_fcmp_actions_none');
            fcmpselectnone.on('click', function(e) {
                // Uncheck all components (even those not displayed).
                e.preventDefault();
                filter.all('.amosfilter_fcmp_actions_select').removeClass('active');
                fcmp.all('tr input').set('checked', false);
            });

            fcmp.all('tr input').on('click', function(e) {
                filter.all('.amosfilter_fcmp_actions_select').removeClass('active');
            });

            var flast = filter.one('#flast');
            if (flast) {
                flast.on('change', function (e) {
                    e.preventDefault();
                    filter.all('.fver').set('disabled', e.currentTarget.get('checked'));
                    if (e.currentTarget.get('checked')) {
                        filter.one('#amosfilter_fver_versions').addClass('hidden');
                        filter.all('#amosfilter_fcmp').addClass('hiddenversions');
                    } else {
                        filter.one('#amosfilter_fver_versions').removeClass('hidden');
                        filter.all('#amosfilter_fcmp').removeClass('hiddenversions');
                    }
                });
            }

            // search for components
            var fcmpsearch = filter.one('#amosfilter_fcmp_actions_search');
            fcmpsearch.on('keypress', function(e) {
                if (e.keyCode == 13) {
                    // Do not submit the form
                    e.halt();
                }
            });
            fcmpsearch.on('valuechange', this.filter_components, this, fcmpsearch, fcmp);

            // scroll the components selector to the first checked item
            var firstcheckcomponent = fcmp.one('.labelled_checkbox.preset');
            if (firstcheckcomponent) {
                // Align with bottom to prevent the main window from scrolling too.
                firstcheckcomponent.scrollIntoView(false);
            }

            // make greylist related checkboxed mutally exclusive
            var fglo = filter.one('#amosfilter_fglo');
            var fwog = filter.one('#amosfilter_fwog');
            if (fglo && fwog) {
                fglo.on('change', function (e) {
                    if (fglo.get('checked')) {
                        fwog.set('checked', false);
                    }
                });
                fwog.on('change', function (e) {
                    if (fwog.get('checked')) {
                        fglo.set('checked', false);
                    }
                });
            }

            // display the "loading" icon after the filter button is pressed
            var fform       = Y.one('#amosfilter_form');
            var fsubmit     = fform.one('button[type="submit"]');
            var translatorw = Y.one('.translatorwrapper');
            var ficonholder = fform.one('#amosfilter_submitted_icon');

            fform.on('submit', function(e) {
                        fsubmit.setStyle('minWidth', fsubmit.getComputedStyle('width'));
                        fsubmit.set('text', M.util.get_string('processing', 'local_amos'));
                        ficonholder.setStyle('display', 'inline');
                        translatorw.setStyle('display', 'none');
                        });
        },

        /**
         * Filter the list of components
         *
         * @method filter_components
         * @param {Y.Event} e
         * @param {Y.Node} searchfield
         * @param {Y.Node} componentslist the wrapping node of the list of components
         */
        filter_components: function(e, searchfield, componentslist) {
            this.filtersearchneedle = searchfield.get('value').toString().replace(/^ +| +$/, '');
            componentslist.all('table tr td.labelled_checkbox').each(function(component, index, componentslist) {
                if (this.filtersearchneedle == '' || component.one('label').get('text').toString().indexOf(this.filtersearchneedle) !== -1) {
                    row = component.get('parentNode');
                    row.show();
                    row.removeClass('hidden');
                } else {
                    row = component.get('parentNode');
                    row.hide();
                    row.addClass('hidden');
                }
            }, this);
        },
    }, {
        NAME : 'amos_filter', // used as a prefix for events
        ATTRS : { }
    });

    M.local_amos = M.local_amos || {};

    M.local_amos.init_filter = function(config) {
        M.local_amos.Filter = new Filter(config);
    }

}, '0.0.1', { requires:['base', 'node', 'event', 'selector-css3'] });
