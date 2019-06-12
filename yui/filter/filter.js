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
            var flnghtml    = '<div class="btn-group btn-group-toggle" data-toggle="buttons">' +
                              '<label id="amosfilter_flng_actions_all" class="btn btn-light amosfilter_flng_actions_select">' +
                              '<input type="radio" name="amosfilter_flng_actions" autocomplete="off">' +
                              M.util.get_string('languagesall', 'local_amos') + '</label>' +
                              '<label id="amosfilter_flng_actions_none" class="btn btn-light amosfilter_flng_actions_select">' +
                              '<input type="radio" name="amosfilter_flng_actions" autocomplete="off">' +
                              M.util.get_string('languagesnone', 'local_amos') + '</label></div>';
            flngactions.set('innerHTML', flnghtml);
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
            var fcmphtml    = '<div class="btn-group btn-group-toggle" data-toggle="buttons">'+
                              '<label id="amosfilter_fcmp_actions_allstandard" class="btn btn-light amosfilter_fcmp_actions_select">' +
                              '<input type="radio" name="amosfilter_fcmp_actions" autocomplete="off">' +
                              M.util.get_string('componentsstandard', 'local_amos') + '</label>' +
                              '<label id="amosfilter_fcmp_actions_allapp" class="btn btn-light amosfilter_fcmp_actions_select">' +
                              '<input type="radio" name="amosfilter_fcmp_actions" autocomplete="off">' +
                              M.util.get_string('componentsapp', 'local_amos') + '</label>' +
                              '<label id="amosfilter_fcmp_actions_all" class="btn btn-light amosfilter_fcmp_actions_select">' +
                              '<input type="radio" name="amosfilter_fcmp_actions" autocomplete="off">' +
                              M.util.get_string('componentsall', 'local_amos') + '</label>' +
                              '<label id="amosfilter_fcmp_actions_none" class="btn btn-light amosfilter_fcmp_actions_select">' +
                              '<input type="radio" name="amosfilter_fcmp_actions" autocomplete="off">' +
                              M.util.get_string('componentsnone', 'local_amos') + '</label>' +
                              '</div>'+
                              '<input id="amosfilter_fcmp_actions_search" class="search-query form-control ml-2" name="amosfilter_fcmp_actions_search" type="text" size="8" maxlenght="20" placeholder="' +
                              M.util.get_string('filter', 'core') + ' "/>' +
                              '<div class="ml-auto">' +
                              '<button id="amosfilter_fcmp_actions_enlarge" type="button class="btn btn-light">' +
                              '<i class="fa fa-arrows-v" aria-hidden="true"></i>' + M.util.get_string('componentsenlarge', 'local_amos') +
                              '</button></div>';

            fcmpactions.set('innerHTML', fcmphtml);
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
            fcmpselectallapp.on('click', function(e) {
                // Check all displayed standard components.
                e.preventDefault();
                filter.all('.amosfilter_fcmp_actions_select').removeClass('active');
                fcmp.all('tr:not(.hidden) input').set('checked', false);
                fcmp.all('tr.app:not(.hidden) input').set('checked', true);
                filter.one('#fapp input').set('checked', true);
                filter.one('#amosfilter_fmis_collapse').removeClass('collapse');
                filter.one('#fapp').ancestor().removeClass('collapse');
                filter.all('.amosfilter_version input').set('checked', false);
                filter.one('.amosfilter_version .current').set('checked', true);
            });
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

            // search for components
            var fcmpsearch = filter.one('#amosfilter_fcmp_actions_search');
            fcmpsearch.on('keypress', function(e) {
                if (e.keyCode == 13) {
                    // Do not submit the form
                    e.halt();
                }
            });
            fcmpsearch.on('valuechange', this.filter_components, this, fcmpsearch, fcmp);

             // set up collapsible fields
            if (filter.one('.collapse.show')) {
                var collapsibleControl = Y.Node.create('<button class="btn btn-light" data-toffle="collapse" type="button" role="button" aria-expanded="false" data-target=".collapse">' + M.util.get_string('morefilteringoptions', 'local_amos') + '</button>');
                filter.one('.collapsible-control').replace(collapsibleControl);
                filter.all('.collapse.show').removeClass('show');

                collapsibleControl.on('click', function(e) {
                    e.preventDefault();
                    filter.all('.collapse').toggleClass('show');
                });
            }

            // scroll the components selector to the first checked item
            var firstcheckcomponent = fcmp.one('.labelled_checkbox.preset');
            if (firstcheckcomponent) {
                // Align with bottom to prevent the main window from scrolling too.
                firstcheckcomponent.scrollIntoView(false);
            }

            // make greylist related checkboxed mutally exclusive
            var fglo = filter.one('#amosfilter_fglo');
            var fwog = filter.one('#amosfilter_fwog');
            fglo.on('change', function(e) {
                if (fglo.get('checked')) {
                    fwog.set('checked', false);
                }
            });
            fwog.on('change', function(e) {
                if (fwog.get('checked')) {
                    fglo.set('checked', false);
                }
            });

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
