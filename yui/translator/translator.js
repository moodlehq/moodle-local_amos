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

        initializer : function(config) {
            this.init_translator();
            Y.log('Translator initialized', 'debug', 'moodle-local_amos-translator');
        },

        /**
         * Initialize JS support for the main translation page
         */
        init_translator: function() {
            var translator  = Y.one('#amostranslator');

            if (translator) {
                translator.delegate('click', function (e) {
                    if (e.target.ancestor('button.markuptodate', true, '.translatable')) {
                        // clicking the up-to-date button
                        // catch it when it bubbles up to the checkbox
                        if (e.target.test('button.markuptodate')) {
                            this.mark_update(e);
                        }
                    } else if (e.target.ancestor('.helptooltip', true, '.translatable')) {
                        // do nothing, propagate the event
                        return;
                    } else if (e.currentTarget.one('textarea.translation-edit')) {
                        // it is already in the edit mode
                        return;
                    } else {
                        // clicking on a translatable cell makes it editable
                        if (e.ctrlKey) {
                            this.make_editable(e, true);
                        } else {
                            this.make_editable(e, false);
                        };
                    }
                }, '.translatable', this);

                // turn editing mode on for all translatable missing strings by default
                var missing = translator.all('.translatable.missing.translation');
                missing.each(function (node, index, list) { this.editor_on(node, false, index + 1); }, this);

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
         * Event handler when a user clicks 'mark as up-to-date'
         *
         * @param {Y.Event} e
         */
        mark_update: function(e) {
            var translationid = e.currentTarget.ancestor('.string-control-group').getData('amos-translationid');

            if (! Y.Lang.isValue(translationid)) {
                alert('Error - Unknown translation id!');
                return;
            }

            this.uptodate(translationid);
        },

        /**
         * Make the event target editable translator cell
         *
         * @param {Y.Event} e
         * @param {Bool} nocleaning
         */
        make_editable: function(e, nocleaning) {
            this.editor_on(e.currentTarget, true, null, nocleaning);
        },

        /**
         * Turn editable translator table cell into editing mode
         *
         * @param {Y.Node} cell translator table cell
         * @param {Bool} focus shall the new editor get focus?
         * @param {Int} tabindex of the new editor
         * @param {Bool} nocleaning - text should not be cleaned
         */
        editor_on: function(cell, focus, tabindex, nocleaning) {
            var current     = cell.one('.translation-view');    // <div> with the current translation
            var stext       = current.get('text');
            stext           = stext.replace(/\u200b/g, '');     // remove U+200B zero-width space
            var editor      = Y.Node.create('<textarea class="translation-edit">' + stext + '</textarea>');
            
            if (nocleaning) {
                cell.addClass('nocleaning');
            } else {
                cell.removeClass('nocleaning');
            }

            if (tabindex) {
                editor.setAttribute('tabindex', tabindex);
            }

            editor.on('blur', this.editor_blur, this);
            var ch = cell.get('clientHeight') * 1;
            var eh = editor.get('offsetHeight') * 1;
            var cw = cell.get('clientWidth') * 1;
            var ew = editor.get('offsetWidth') * 1;

            cell.append(editor);

            if (eh < ch - 20) {
                editor.setStyle('height', ch - 20 + 'px');
            }
            if (ew < cw - 20) {
                editor.setStyle('width', cw - 20 + 'px');
            }

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
                cell.removeClass('nocleaning');
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
            
            var nocleaning = cell.hasClass('nocleaning');
            var nochanges;
            if (nocleaning) {
                nochanges = (oldtext === newtext);
            } else {
                nochanges = (oldtext === Y.Lang.trim(newtext));
            }
            
            if (nochanges) {
                if (oldtext !== '') {
                    this.editor_off(cell, null, null);
                    var updater = cell.one('.uptodatewrapper');
                    if (updater) {
                        updater.setStyle('display', 'block');
                    }
                }
            } else {
                editor.set('disabled', true);
                this.submit(cell, editor, nocleaning);
            }
        },

        /**
         * Send the translation to the server to be staged
         *
         * @param {Y.Node} cell
         * @param {Y.Node} editor
         * @param {Bool} nocleaning
         */
        submit: function(cell, editor, nocleaning) {
            // configure the async request
            var ctrlgrp = cell.ancestor('.string-control-group');
            var lang = ctrlgrp.getData('amos-lang');
            var originalid = ctrlgrp.getData('amos-originalid');
            var translationid = ctrlgrp.getData('amos-translationid');

            if (! Y.Lang.isValue(originalid) || ! Y.Lang.isString(lang)) {
                alert('Error - Unable to stage the translation!');
                return;
            }

            var uri = M.cfg.wwwroot + '/local/amos/saveajax.php';
            var cfg = {
                method: 'POST',
                data: build_querystring({
                    'lang': lang,
                    'originalid': originalid,
                    'translationid': translationid,
                    'text': editor.get('value'),
                    'sesskey': M.cfg.sesskey,
                    'nocleaning': nocleaning
                }),
                on: {
                    success : this.submit_success,
                    failure : this.submit_failure,
                },
                'arguments': {
                    cell: cell,
                },
                context: this
            };

            // stop the IO queue so we can add to it
            Y.io.queue.stop();

            // add a new async request into the queue
            Y.io.queue(uri, cfg);
            this.ajaxqueue.push(lang + '/' + originalid);

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
            if (outcome.nocleaning) {
                this.editor_off(cell, newtext, 'staged nocleaning');
            } else {
                this.editor_off(cell, newtext, 'staged');
            }
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
                var translator = Y.one('#amostranslator'),
                    btn = translator.one('#uptodate_' + amosid),
                    cell = btn.ancestor('.string-text'),
                    wrapper = btn.ancestor('.uptodatewrapper');

                wrapper.remove();
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
            if (translator === null) {
                // no strings available at the page
                return;
            }
            var iconcontainers = translator.all('.info-google');
            iconcontainers.each(this.init_google_action, this);
            translator.delegate('click', this.google_translate, '.info-google a', this);
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
        init_google_action: function(container, index, iconcontainers) {
            var lang = container.ancestor('.string-control-group').getData('amos-lang');
            if (Y.Lang.isString(lang) && Y.Array.indexOf(this.translatable, lang) >= 0) {
                var linkhtml = ' | <a href="#">' + M.util.get_string('googletranslate', 'local_amos') + '</a>';
                container.set('innerHTML', linkhtml);
            }
        },

        /**
         * @param {Y.Event} e
         */
        google_translate: function(e) {
            e.preventDefault();

            var actionlink = e.currentTarget,
                ctrlgrp = e.currentTarget.ancestor('.string-control-group'),
                lang = ctrlgrp.getData('amos-lang'),
                enid = ctrlgrp.getData('amos-originalid'),
                placeholder = ctrlgrp.one('.google-translation');

            if (! Y.Lang.isString(lang) || ! Y.Lang.isValue(enid)) {
                alert('Error - Unable to translate!');
                return;
            }

            // stop the IO queue so we can add to it
            Y.io.queue.stop();

            // configure the async request
            var uri = M.cfg.wwwroot + '/local/amos/translate.ajax.php';
            var cfg = {
                method: 'GET',
                data: build_querystring({enid: enid, lng: lang, sesskey: M.cfg.sesskey}),
                on: {
                    success : this.translate_success,
                    failure : this.translate_failure,
                },
                'arguments': {
                    'placeholder': placeholder,
                    'actionlink': actionlink,
                    'enid': enid,
                    'lang': lang
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
            var placeholder = args.placeholder,
                actionlink = args.actionlink,
                enid = args.enid,
                lang = args.lang;

            actionlink.remove();

            try {
                result = Y.JSON.parse(outcome.responseText);
                if (!result.error) {
                    placeholder.set('innerHTML', result.data.translation);
                    placeholder.addClass('googleok preformatted alert alert-success');
                } else {
                    placeholder.set('innerHTML', result.error.message);
                    placeholder.addClass('googleerror alert alert-error');
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
    };

}, '0.0.1', { requires:['base', 'node', 'event', 'io-queue', 'json', 'selector-css3'] });
