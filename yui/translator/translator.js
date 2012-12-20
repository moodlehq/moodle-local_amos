/**
 * YUI module for AMOS translator
 *
 * @author  David Mudrak <david@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
YUI.add('moodle-local_amos-translator', function(Y) {

    /**
     * @class Translator
     * @constructor
     */
    function Translator() {
        Translator.superclass.constructor.apply(this, arguments);
    }

    Y.extend(Translator, Y.Base, {

        /**
         * Local stack to control pending requests sent to server by AJAX
         *
         * @property ajaxqueue
         */
        ajaxqueue: [],

        /**
         * The text currently entered into the search component box
         *
         * @property filtersearchneedle
         */
        filtersearchneedle: '',

        initializer : function(config) {
            this.init_translator();
        },

        /**
         * Initialize JS support for the main translation page
         */
        init_translator: function() {
            var filter      = Y.one('#amosfilter');
            var translator  = Y.one('#amostranslator');

            // add select All / None links to the filter languages selector field
            var flng        = filter.one('#amosfilter_flng');
            var flngactions = filter.one('#amosfilter_flng_actions');
            var flnghtml    = '<a href="#" id="amosfilter_flng_actions_all">' +
                              M.util.get_string('languagesall', 'local_amos') + '</a>' +
                              ' / <a href="#" id="amosfilter_flng_actions_none">' +
                              M.util.get_string('languagesnone', 'local_amos') + '</a>';
            flngactions.set('innerHTML', flnghtml);
            var flngselectall = filter.one('#amosfilter_flng_actions_all');
            flngselectall.on('click', function(e) { flng.all('option').set('selected', true); });
            var flngselectnone = filter.one('#amosfilter_flng_actions_none');
            flngselectnone.on('click', function(e) { flng.all('option').set('selected', false); });

            // add select All / None links to the filter components selector field
            var fcmp        = filter.one('#amosfilter_fcmp');
            var fcmpactions = filter.one('#amosfilter_fcmp_actions');
            var fcmphtml    = '<a href="#" id="amosfilter_fcmp_actions_enlarge">' +
                              M.util.get_string('componentsenlarge', 'local_amos') + '</a>' +
                              ' / <a href="#" id="amosfilter_fcmp_actions_allstandard">' +
                              M.util.get_string('componentsstandard', 'local_amos') + '</a>' +
                              ' / <a href="#" id="amosfilter_fcmp_actions_all">' +
                              M.util.get_string('componentsall', 'local_amos') + '</a>' +
                              ' / <a href="#" id="amosfilter_fcmp_actions_none">' +
                              M.util.get_string('componentsnone', 'local_amos') + '</a>' +
                              ' <input type="text" size="8" maxlength="20" placeholder="' + M.util.get_string('search', 'core') +
                                '" name="amosfilter_fcmp_actions_search" id="amosfilter_fcmp_actions_search" />';
            fcmpactions.set('innerHTML', fcmphtml);
            var fcmpenlarge = filter.one('#amosfilter_fcmp_actions_enlarge');
            fcmpenlarge.on('click', function(e) {
                fcmp.setAttribute('size', parseInt(fcmp.getAttribute('size')) + 5);
            });
            var fcmpselectallstandard = filter.one('#amosfilter_fcmp_actions_allstandard');
            fcmpselectallstandard.on('click', function(e) {
                fcmp.all('optgroup:first-child option, optgroup:first-child + optgroup option').set('selected', true);
            });
            var fcmpselectall = filter.one('#amosfilter_fcmp_actions_all');
            fcmpselectall.on('click', function(e) {
                fcmp.all('option').each(function (option, index, options) {
                    if (!option.hasClass('hidden')) {
                        option.set('selected', true);
                    }
                })
            });
            var fcmpselectnone = filter.one('#amosfilter_fcmp_actions_none');
            fcmpselectnone.on('click', function(e) { fcmp.all('option').set('selected', false); });

            // search for components
            var fcmpsearch = filter.one('#amosfilter_fcmp_actions_search');
            fcmpsearch.on('keypress', this.filter_components, this, fcmpsearch, fcmp); // needed so we catch Enter pressed, too
            fcmpsearch.on('keyup', this.filter_components, this, fcmpsearch, fcmp);
            fcmpsearch.on('change', this.filter_components, this, fcmpsearch, fcmp);

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
            var fsubmit     = fform.one('input.submit');
            var translatorw = Y.one('.translatorwrapper');
            var ficonholder = fform.one('#amosfilter_submitted_icon');
            var ficonhtml   = '<img src="'+M.cfg.loadingicon+'" class="spinner" style="display:none" />';
            ficonholder.set('innerHTML', ficonhtml);

            fform.on('submit', function(e) {
                        fsubmit.set('value', 'Processing...');
                        ficonholder.one('img').setStyle('display', 'inline');
                        translatorw.setStyle('display', 'none');
                        });

            if (translator) {

                translator.delegate('click', function (e) {
                    if (e.target.ancestor('.uptodatecheckbox, .uptodatelabel', true, '.translatable')) {
                        // clicking the up-to-date checkbox (or its label) marks it as up-to-date
                        // catch it when it bubbles up to the checkbox
                        if (e.target.test('.uptodatecheckbox')) {
                            this.mark_update(e);
                        }
                    } else if (e.target.ancestor('.helplink', true, '.translatable')) {
                        // do nothing, propagate the event
                    } else if (e.currentTarget.one('textarea.translation-edit')) {
                        // it is already in the edit mode
                    } else {
                        // clicking on a translatable cell makes it editable
                        this.make_editable(e);
                    }
                }, '.translatable', this);

                // turn editing mode on for all translatable missing strings by default
                var missing = translator.all('.translatable.missing.translation');
                missing.each(function (node, index, list) { this.editor_on(node); }, this);

                // protect from leaving the page if there is a pending ajax request
                Y.one('body').delegate('click', function(e) {
                    if (this.ajaxqueue.length > 0) {
                        var a = e.currentTarget;
                        if (!a.ancestor('.helplink', true)) {
                            if (!confirm('There are unsaved changes on this page waiting to get processed. Do you really want to leave the page?')) {
                                e.halt();
                            }
                        }
                    }
                }, 'a', this);

                // initialize the google translate service support
                this.init_google_translator();
            }
        },

        /**
         * Filter the list of components
         *
         * @method filter_components
         * @param {Y.Event} e
         * @param {Y.Node} searchfield
         * @param {Y.NodeList} componentslist
         */
        filter_components: function(e, searchfield, componentslist) {
            // If enter was pressed, prevent a form submission from happening.
            if (e.keyCode == 13) {
                e.halt();
            }
            this.filtersearchneedle = searchfield.get('value').toString().replace(/^ +| +$/, '');
            var options = componentslist.all('option');
            options.each(function(option, index, options) {
                if (this.filtersearchneedle == '' || option.get('text').toString().indexOf(this.filtersearchneedle) !== -1) {
                    option.show();
                    option.removeClass('hidden');
                    if (option.get('parentNode').test('span')) {
                        option.unwrap();
                    }
                } else {
                    option.hide();
                    option.addClass('hidden');
                    if (!option.get('parentNode').test('span')) {
                        option.wrap('<span style="display: none;"/>');
                    }
                }
            }, this);
        },

        /**
         * Event handler when a user clicks 'mark as up-to-date'
         *
         * @param {Y.Event} e
         */
        mark_update: function(e) {
            var cell = e.currentTarget;
            var checkbox = cell.one('input.uptodatecheckbox');
            var amosid = checkbox.get('id').split('_', 2)[1];
            var checked  = checkbox.get('checked');
            if (checked) {
                this.uptodate(amosid);
            }
        },

        /**
         * Make the event target editable translator cell
         *
         * @param {Y.Event} e
         */
        make_editable: function(e) {
            this.editor_on(e.currentTarget, true);
        },

        /**
         * Turn editable translator table cell into editing mode
         *
         * @param {Y.Node} cell translator table cell
         * @param {Bool} focus shall the new editor get focus?
         */
        editor_on: function(cell, focus) {
            var current     = cell.one('.translation-view');    // <div> with the current translation
            var stext       = current.get('text');
            stext           = stext.replace(/\u200b/g, '');     // remove U+200B zero-width space
            var editor      = Y.Node.create('<textarea class="translation-edit">' + stext + '</textarea>');
            editor.on('blur', this.editor_blur, this);
            cell.append(editor);
            editor.setStyle('height', cell.getComputedStyle('height'));
            if (focus) {
                editor.focus();
            }
            current.setStyle('display', 'none');
            var updater = cell.one('.uptodatewrapper');
            if (updater) {
                updater.setStyle('display', 'none');
            }
        },

        /**
         * Turn editable translator table cell into display mode
         *
         * @param {Y.Node} cell translator table cell
         * @param {String} newtext or null to keep the current
         * @param {String} newclass or null to keep the current
         */
        editor_off: function(cell, newtext, newclass) {
            // set new properties of the cell
            if (newclass !== null) {
                cell.removeClass('translated');
                cell.removeClass('missing');
                cell.removeClass('staged');
                cell.addClass(newclass);
            }
            // set the new contents of the translation text and show it
            var holder = cell.one('.translation-view');    // <div> with the current translation
            if (newtext !== null) {
                holder.set('innerHTML', newtext);
            }
            holder.setStyle('display', null);
            // remove the editor
            var editor = cell.one('textarea.translation-edit');
            editor.detach('blur', this.editor_blur);
            editor.remove();
        },

        /**
         * Event handler when translation input field looses focus
         *
         * @param {Y.Event} e
         */
        editor_blur: function(e) {
            var editor      = e.currentTarget;
            var cell        = editor.get('parentNode');
            var current     = cell.one('.translation-view');    // <div> with the current translation
            var oldtext     = current.get('text');
            var newtext     = editor.get('value');

            if (Y.Lang.trim(oldtext) == Y.Lang.trim(newtext)) {
                // no change
                this.editor_off(cell, null, null);
                var updater = cell.one('.uptodatewrapper');
                if (updater) {
                    updater.setStyle('display', 'block');
                }
            } else {
                editor.set('disabled', true);
                this.submit(cell, editor);
            }
        },

        /**
         * Send the translation to the server to be staged
         *
         * @param {Y.Node} cell
         * @param {Y.Node} editor
         */
        submit: function(cell, editor) {
            // stop the IO queue so we can add to it
            Y.io.queue.stop();

            // configure the async request
            var cellid = cell.get('id');
            var uri = M.cfg.wwwroot + '/local/amos/saveajax.php';
            var cfg = {
                method: 'POST',
                data: build_querystring({stringid: cellid, text: editor.get('value'), sesskey: M.cfg.sesskey}),
                on: {
                    success : this.submit_success,
                    failure : this.submit_failure,
                },
                'arguments': {
                    cell: cell,
                },
                context: this
            };

            // add a new async request into the queue
            Y.io.queue(uri, cfg);
            this.ajaxqueue.push(cellid);

            // re-start queue processing
            Y.io.queue.start();
        },

        /**
         * Callback function for IO transaction
         *
         * @param {Int} tid transaction identifier
         * @param {Object} outcome from server
         * @param {Mixed} args
         */
        submit_success: function(tid, outcome, args) {
            var cell = args.cell;

            try {
                outcome = Y.JSON.parse(outcome.responseText);
            } catch(e) {
                alert('AJAX error - can not parse response');
                return;
            }

            var newtext = outcome.text;
            this.ajaxqueue.shift();
            this.editor_off(cell, newtext, 'staged');
        },

        /**
         * Callback function for IO transaction
         *
         * @param {Int} tid transaction identifier
         * @param {Object} outcome from server
         * @param {Mixed} args
         */
        submit_failure: function(tid, outcome, args) {
            alert('AJAX request failed: ' + outcome.status + ' ' + outcome.statusText);
        },

        /**
         * Send AJAX request to mark a translation as up-to-date
         *
         * @param {String} amosid the identifer of the string in amos_repository table
         */
        uptodate: function(amosid) {
            // stop the IO queue so we can add to it
            Y.io.queue.stop();

            // configure the async request
            var uri = M.cfg.wwwroot + '/local/amos/uptodate.ajax.php';
            var cfg = {
                method: 'POST',
                data: build_querystring({amosid: amosid, sesskey: M.cfg.sesskey}),
                on: {
                    success : this.uptodate_success,
                    failure : this.uptodate_failure,
                },
                'arguments': {
                    amosid: amosid,
                }
            };

            // add a new async request into the queue
            Y.io.queue(uri, cfg);

            // re-start queue processing
            Y.io.queue.start();
        },

        /**
         * Callback function for IO transaction
         *
         * @param {Int} tid transaction identifier
         * @param {Object} outcome from server
         * @param {Mixed} args
         */
        uptodate_success: function(tid, outcome, args) {
            var amosid = args.amosid;

            try {
                outcome = Y.JSON.parse(outcome.responseText);
            } catch(e) {
                alert('AJAX error - can not parse response');
                return;
            }

            var timeupdated = outcome.timeupdated;
            if (timeupdated > 0) {
                var translator = Y.one('#amostranslator');
                var checkbox = translator.one('#update_' + amosid);
                var cell = checkbox.get('parentNode').get('parentNode');
                checkbox.get('parentNode').remove(); // remove whole wrapping div
                cell.removeClass('outdated');
            }
        },

        /**
         * Callback function for IO transaction
         *
         * @param {Int} tid transaction identifier
         * @param {Object} outcome from server
         * @param {Mixed} args
         */
        uptodate_failure: function(tid, outcome, args) {
            alert('AJAX request failed: ' + outcome.status + ' ' + outcome.statusText);
        },

        /**
         * Initialize Google Translate support
         */
        init_google_translator: function() {
            var translator = Y.one('#amostranslator');
            if (translator == null) {
                // no strings available at the page
                return;
            }
            var iconcontainers = translator.all('.googleicon');
            iconcontainers.each(this.init_google_icon, this);
            translator.delegate('click', this.google_translate, '.googleicon img', this);
        },

        /**
         * Whitelist of Moodle language codes supported by Google Translator
         *
         * Moodle language code can be mapped to a Google one - see the mapping in the
         * translate.ajax.php file.
         *
         * @link https://developers.google.com/translate/v2/using_rest#language-params
         */
        translatable: [
            'af', 'ar', 'az', 'be', 'bg', 'bn', 'ca', 'cs', 'cy', 'da', 'de', 'el', 'en',
            'eo', 'es', 'et', 'eu', 'fa', 'fi', 'fr', 'ga', 'gl', 'gu', 'he', 'hi', 'hr',
            'ht', 'hu', 'id', 'is', 'it', 'iw', 'ja', 'ka', 'kn', 'ko', 'la', 'lt', 'lv',
            'mk', 'ms', 'mt', 'nl', 'no', 'pl', 'pt', 'pt_br', 'ro', 'ru', 'sk', 'sl',
            'sq', 'sr', 'sv', 'sw', 'ta', 'te', 'th', 'tl', 'tr', 'uk', 'ur', 'vi', 'yi',
            'zh_cn', 'zh_tw'
        ],

        /**
         * Prepare an icon for Google translation
         *
         * @param {Y.Node} container the div container of the icon
         */
        init_google_icon: function(container, index, iconcontainers) {
            var iconhtml = '<img alt="Google Translate" title="Use Google Translate service" src="' + M.util.image_url('google', 'local_amos') + '" />';
            var lang = container.get('parentNode').get('parentNode').one('td.lang .langcode').get('innerHTML');
            if (Y.Array.indexOf(this.translatable, lang) >= 0) {
                container.set('innerHTML', iconhtml);
            }
        },

        /**
         * @param {Y.Event} e
         */
        google_translate: function(e) {
            var icon = e.currentTarget;
            var row = icon.ancestor('tr');
            var rowid = row.one('td.translation').get('id').split('___');
            var lang = rowid[0];
            var enid = rowid[1];

            icon.set('src', M.cfg.loadingicon);

            // stop the IO queue so we can add to it
            Y.io.queue.stop();

            // configure the async request
            var uri = M.cfg.wwwroot + '/local/amos/translate.ajax.php';
            var cfg = {
                method: 'GET',
                data: build_querystring({enid: enid, lang: lang, sesskey: M.cfg.sesskey}),
                on: {
                    success : this.translate_success,
                    failure : this.translate_failure,
                },
                'arguments': {
                    icon: icon,
                    enid: enid,
                    lang: lang
                }
            };

            // add a new async request into the queue
            Y.io.queue(uri, cfg);

            // re-start queue processing
            Y.io.queue.start();
        },

        /**
         * Callback function for IO transaction
         *
         * @param {Int} tid transaction identifier
         * @param {Object} outcome from server
         * @param {Mixed} args
         */
        translate_success: function(tid, outcome, args) {
            var icon = args.icon;
            var enid = args.enid;
            var lang = args.lang;

            try {
                result = Y.JSON.parse(outcome.responseText);
                if (!result.error) {
                    container = icon.get('parentNode');
                    container.set('innerHTML', result.data.translation);
                    container.replaceClass('googleicon', 'googletranslation');
                    container.addClass('preformatted');
                } else {
                    container = icon.get('parentNode');
                    container.set('innerHTML', result.error.message);
                    container.replaceClass('googleicon', 'googleerror');
                }
            } catch(e) {
                alert('AJAX error - can not parse response');
                return;
            }
        },

        /**
         * Callback function for IO transaction
         *
         * @param {Int} tid transaction identifier
         * @param {Object} outcome from server
         * @param {Mixed} args
         */
        translate_failure: function(tid, outcome, args) {
            alert('AJAX request failed: ' + outcome.status + ' ' + outcome.statusText);
        }

    }, {
        NAME : 'amos_translator', // used as a prefix for events
        ATTRS : { }
    });

    M.local_amos = M.local_amos || {};

    M.local_amos.init_translator = function(config) {
        M.local_amos.Translator = new Translator(config);
    }

}, '0.0.1', { requires:['base', 'node', 'event', 'io-queue', 'json'] });
