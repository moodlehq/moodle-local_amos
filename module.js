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
 * Javascript functions for AMOS
 *
 * @package   local-amos
 * @copyright 2010 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @namespace Local amos namespace
 */
M.local_amos = M.local_amos || {};

/**
 * Configuration and resources holder
 */
M.local_amos.Y = {};

/**
 * Local stack to control pending requests sent to server by AJAX
 */
M.local_amos.ajaxqueue = [];

////////////////////////////////////////////////////////////////////////////////
// TRANSLATOR //////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////

/**
 * Initialize JS support for the main translation page, called from view.php
 *
 * @param {Object} Y YUI instance
 */
M.local_amos.init_translator = function(Y) {
    M.local_amos.Y  = Y;

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
    var fcmphtml    = '<a href="#" id="amosfilter_fcmp_actions_allstandard">' +
                      M.util.get_string('componentsstandard', 'local_amos') + '</a>' +
                      ' / <a href="#" id="amosfilter_fcmp_actions_all">' +
                      M.util.get_string('componentsall', 'local_amos') + '</a>' +
                      ' / <a href="#" id="amosfilter_fcmp_actions_none">' +
                      M.util.get_string('componentsnone', 'local_amos') + '</a>';
    fcmpactions.set('innerHTML', fcmphtml);
    var fcmpselectallstandard = filter.one('#amosfilter_fcmp_actions_allstandard');
    fcmpselectallstandard.on('click', function(e) {
        fcmp.all('optgroup:first-child option, optgroup:first-child + optgroup option').set('selected', true);
    });
    var fcmpselectall = filter.one('#amosfilter_fcmp_actions_all');
    fcmpselectall.on('click', function(e) { fcmp.all('option').set('selected', true); });
    var fcmpselectnone = filter.one('#amosfilter_fcmp_actions_none');
    fcmpselectnone.on('click', function(e) { fcmp.all('option').set('selected', false); });

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
        // make all translatable fields editable
        var translatable = translator.all('.translatable');
        translatable.on('click', M.local_amos.make_editable);
        // in case of missing strings, turn editing mode on by default
        var missing = translator.all('.committable.missing.translation');
        missing.each(function(cell) { M.local_amos.editor_on(cell); });
        // catch all clicks to up-to-date marker
        var updaters = translator.all('.uptodatewrapper input, .uptodatewrapper label');
        updaters.on('click', M.local_amos.mark_update);
        // protect from leaving the page if there is a pending ajax request
        var links = Y.all('a');
        links.on('click', function(e) {
            if (M.local_amos.ajaxqueue.length > 0) {
                var a = e.currentTarget;
                var href = a.get('href');
                var id = a.get('id');
                if (!id.match('^helpicon')) {
                    if (!confirm('There are unsaved changes on this page waiting to get processed. Do you really want to leave the page?')) {
                        e.halt();
                    }
                }
            }
        });
    }

    google.load('language', 1, {'callback' : M.local_amos.init_google_translator});
}

/**
 * Event handler when a user clicks 'mark as up-to-date'
 *
 * @param {Y.Event} e
 */
M.local_amos.mark_update = function(e) {
    var checkbox = e.currentTarget;
    var amosid = checkbox.get('id').split('_', 2)[1];
    var checked  = checkbox.get('checked');
    if (checked) {
        M.local_amos.uptodate(amosid);
    }
    e.stopPropagation();
}

/**
 * Make the event target editable translator cell
 *
 * @param {Y.Event} e
 */
M.local_amos.make_editable = function(e) {
    M.local_amos.editor_on(e.currentTarget, true);
}

/**
 * Turn editable translator table cell into editing mode
 *
 * @param {Y.Node} cell translator table cell
 * @param {Bool} focus shall the new editor get focus?
 */
M.local_amos.editor_on = function(cell, focus) {
    var Y           = M.local_amos.Y;
    var current     = cell.one('.translation-view');    // <div> with the current translation
    var stext       = current.get('text');
    stext           = stext.replace(/\u200b/g, '');     // remove U+200B zero-width space
    var editor      = Y.Node.create('<textarea class="translation-edit">' + stext + '</textarea>');
    editor.on('blur', M.local_amos.editor_blur);
    cell.append(editor);
    editor.setStyle('height', cell.getComputedStyle('height'));
    if (focus) {
        editor.focus();
    }
    current.setStyle('display', 'none');
    Y.detach('click', M.local_amos.make_editable, cell);
    var updater = cell.one('.uptodatewrapper');
    if (updater) {
        updater.setStyle('display', 'none');
    }
}

/**
 * Turn editable translator table cell into display mode
 *
 * @param {Y.Node} cell translator table cell
 * @param {String} newtext or null to keep the current
 * @param {String} newclass or null to keep the current
 */
M.local_amos.editor_off = function(cell, newtext, newclass) {
    var Y = M.local_amos.Y;
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
    editor.remove();
    // make the cell editable again
    cell.on('click', M.local_amos.make_editable);
}

/**
 * Event handler when translation input field looses focus
 *
 * @param {Y.Event} e
 */
M.local_amos.editor_blur = function(e) {
    var Y           = M.local_amos.Y;
    var editor      = e.currentTarget;
    var cell        = editor.get('parentNode');
    var current     = cell.one('.translation-view');    // <div> with the current translation
    var oldtext     = current.get('text');
    var newtext     = editor.get('value');

    if (Y.Lang.trim(oldtext) == Y.Lang.trim(newtext)) {
        // no change
        M.local_amos.editor_off(cell, null, null);
        var updater = cell.one('.uptodatewrapper');
        if (updater) {
            updater.setStyle('display', 'block');
        }
    } else {
        editor.set('disabled', true);
        M.local_amos.submit(cell, editor);
    }
}

/**
 * Send the translation to the server to be staged
 *
 * @param {Y.Node} cell
 * @param {Y.Node} editor
 */
M.local_amos.submit = function(cell, editor) {
    var Y = M.local_amos.Y;

    // stop the IO queue so we can add to it
    Y.io.queue.stop();

    // configure the async request
    var cellid = cell.get('id');
    var uri = M.cfg.wwwroot + '/local/amos/saveajax.php';
    var cfg = {
        method: 'POST',
        data: build_querystring({stringid: cellid, text: editor.get('value'), sesskey: M.cfg.sesskey}),
        on: {
            success : M.local_amos.submit_success,
            failure : M.local_amos.submit_failure,
        },
        'arguments': {
            cell: cell,
        }
    };

    // add a new async request into the queue
    Y.io.queue(uri, cfg);
    M.local_amos.ajaxqueue.push(cellid);

    // re-start queue processing
    Y.io.queue.start();
}

/**
 * Callback function for IO transaction
 *
 * @param {Int} tid transaction identifier
 * @param {Object} outcome from server
 * @param {Mixed} args
 */
M.local_amos.submit_success = function(tid, outcome, args) {
    var Y = M.local_amos.Y;
    var cell = args.cell;

    try {
        outcome = Y.JSON.parse(outcome.responseText);
    } catch(e) {
        alert('AJAX error - can not parse response');
        return;
    }

    var newtext = outcome.text;
    M.local_amos.ajaxqueue.shift();
    M.local_amos.editor_off(cell, newtext, 'staged');
}

/**
 * Callback function for IO transaction
 *
 * @param {Int} tid transaction identifier
 * @param {Object} outcome from server
 * @param {Mixed} args
 */
M.local_amos.submit_failure = function(tid, outcome, args) {
    alert('AJAX request failed: ' + outcome.status + ' ' + outcome.statusText);
}

/**
 * Send AJAX request to mark a translation as up-to-date
 *
 * @param {String} amosid the identifer of the string in amos_repository table
 */
M.local_amos.uptodate = function(amosid) {
    var Y = M.local_amos.Y;
    // stop the IO queue so we can add to it
    Y.io.queue.stop();

    // configure the async request
    var uri = M.cfg.wwwroot + '/local/amos/uptodate.ajax.php';
    var cfg = {
        method: 'POST',
        data: build_querystring({amosid: amosid, sesskey: M.cfg.sesskey}),
        on: {
            success : M.local_amos.uptodate_success,
            failure : M.local_amos.uptodate_failure,
        },
        'arguments': {
            amosid: amosid,
        }
    };

    // add a new async request into the queue
    Y.io.queue(uri, cfg);

    // re-start queue processing
    Y.io.queue.start();
}

/**
 * Callback function for IO transaction
 *
 * @param {Int} tid transaction identifier
 * @param {Object} outcome from server
 * @param {Mixed} args
 */
M.local_amos.uptodate_success = function(tid, outcome, args) {
    var Y = M.local_amos.Y;
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
}

/**
 * Callback function for IO transaction
 *
 * @param {Int} tid transaction identifier
 * @param {Object} outcome from server
 * @param {Mixed} args
 */
M.local_amos.uptodate_failure = function(tid, outcome, args) {
    alert('AJAX request failed: ' + outcome.status + ' ' + outcome.statusText);
}

/**
 * Initialize Google translator features, called immediately after their API is loaded
 */
M.local_amos.init_google_translator = function() {
    var Y = M.local_amos.Y;
    var translator = Y.one('#amostranslator');
    if (translator == null) {
        // no strings available at the page
        return;
    }
    var iconcontainers = translator.all('.googleicon');
    iconcontainers.each(M.local_amos.init_google_icon);
    icons = translator.all('.googleicon img');
    icons.on('click', M.local_amos.google_translate);
    google.language.getBranding('googlebranding');
}

/**
 * Stores the information of whether the given language is translatable via Google
 */
M.local_amos.translatable = M.local_amos.translatable || []

/**
 * Prepare an icon for Google translation
 *
 * @param {Y.Node} container the div container of the icon
 */
M.local_amos.init_google_icon = function(container, index, iconcontainers) {
    var Y = M.local_amos.Y;
    var iconhtml = '<img src="' + M.util.image_url('google', 'local_amos') + '" />';
    var lang = container.get('parentNode').get('parentNode').one('td.lang .langcode').get('innerHTML');
    lang = M.local_amos.google_language_code(lang);
    if (Y.Lang.isUndefined(M.local_amos.translatable[lang])) {
        M.local_amos.translatable[lang] = google.language.isTranslatable(lang);
    }
    if (M.local_amos.translatable[lang]) {
        container.set('innerHTML', iconhtml);
    }
}

/**
 * @param {Y.Event} e
 */
M.local_amos.google_translate = function(e) {
    var Y = M.local_amos.Y;
    var english = e.currentTarget.get('parentNode').get('parentNode').one('.english').get('innerHTML');
    var lang = e.currentTarget.get('parentNode').get('parentNode').get('parentNode').one('td.lang .langcode').get('innerHTML');
    lang = M.local_amos.google_language_code(lang);
    e.currentTarget.set('src', M.cfg.loadingicon);
    google.language.translate(english, 'en', lang, function(result) {
        if (!result.error) {
            container = e.currentTarget.get('parentNode');
            container.set('innerHTML', result.translation);
            container.replaceClass('googleicon', 'googletranslation');
            container.addClass('preformatted');
        } else {
            container = e.currentTarget.get('parentNode');
            container.set('innerHTML', result.error.message);
            container.replaceClass('googleicon', 'googleerror');
        }
    });
}

/**
 * Maps Moodle language code to the one used by Google
 *
 * @param {String} code Moodle lang code
 * @return {String} Google code
 */
M.local_amos.google_language_code = function(moodlecode) {
    switch(moodlecode) {
        case 'zh_cn':
            return 'zh-CN';
            break;
        case 'zh_tw':
            return 'zh-TW';
            break;
        case 'pt_br':
            return 'pt';
            break;
        default:
            return moodlecode;
    }
}
