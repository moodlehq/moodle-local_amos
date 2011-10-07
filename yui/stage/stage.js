/**
 * YUI module for AMOS stage
 *
 * @author  David Mudrak <david@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
YUI.add('moodle-local_amos-stage', function(Y) {

    var STAGE = function() {
        STAGE.superclass.constructor.apply(this, arguments);
    }

    Y.extend(STAGE, Y.Base, {
        initializer : function(config) {
            this.setup_protector();
            this.replace_unstage_buttons();
            this.setup_diffmode_switcher();
        },

        /**
         * Sets a confirmation handler on buttons that should be protected from accidental submitting
         *
         * @method setup_protection protect from accidental submissions
         */
        setup_protector : function() {
            Y.all('.stagewrapper .protected form').on('submit', function(e) {
                if (!confirm(M.util.get_string('confirmaction', 'local_amos'))) {
                    e.halt();
                }
            });
        },

        replace_unstage_buttons : function() {
            Y.all('#amosstage .singlebutton.unstagebutton').each(function(wrapper) {
                // hide the original form
                var form = wrapper.one('form');
                form.setStyle('display', 'none');
                // create a new button that will trigger AJAX request
                var button = Y.Node.create('<button type="button">' + M.util.get_string('unstage', 'local_amos') + '</button>');
                wrapper.append(button);
                // find all required values from the original form and set them as the button's data
                form.all('input[name=unstage],input[name=component],input[name=lang],input[name=branch]').each(function(input) {
                    this.setData(input.get('name'), input.get('value'));
                }, button);
                button.setData('sesskey', M.cfg.sesskey);
                // attach the onclick handler for the button that triggers the request
                button.on('click', function (e, button) {
                    e.halt();
                    // when the user clicks for the 1st time, let them to confirm it
                    if (!(button.hasClass('tobeconfirmed') || button.hasClass('confirmed'))) {
                        button.setContent(M.util.get_string('unstageconfirm', 'local_amos'));
                        button.addClass('tobeconfirmed');
                        return;
                    }
                    // when the user clicks for the 3rd time, do nothing
                    if (button.hasClass('confirmed')) {
                        return;
                    }
                    // let's rock!
                    button.replaceClass('tobeconfirmed', 'confirmed');
                    var ajaxurl = M.cfg.wwwroot + '/local/amos/unstage.ajax.php';
                    var ajaxcfg = {
                        method : 'POST',
                        data : build_querystring(button.getData()),
                        context : this,
                        'arguments' : { button: button },
                        on : {
                            start: function(transid, args) {
                                args.button.setContent(M.util.get_string('unstaging', 'local_amos'));
                                wheel = Y.Node.create(' <img src="' + M.cfg.loadingicon + '" />');
                                args.button.get('parentNode').append(wheel);
                            },
                            success: function(transid, outcome, args) {
                                try {
                                    result = Y.JSON.parse(outcome.responseText);
                                } catch(e) {
                                    result = { 'success': false, 'error': 'Can not parse response' };
                                }

                                if (result.success) {
                                    var wrapper = args.button.get('parentNode')
                                    args.button.destroy(true);
                                    wrapper.setContent('');
                                    var row = wrapper.ancestor('tr');
                                    // update the page heading - the number of staged strings
                                    var numofstrings = {staged: row.ancestor('table').all('tr').size() - 2}; // one for header, one to be removed
                                    Y.one('#numberofstagedstrings').setContent(M.util.get_string('stagestringsnocommit', 'local_amos', numofstrings));
                                    // remove the row from the table
                                    var anim = new Y.Anim({
                                        node: row,
                                        duration: 0.25,
                                        from: { opacity: 1 },
                                        to: { opacity: 0 },
                                    });
                                    anim.run();
                                    anim.on('end', function() {
                                        var row = this.get('node'); // this === anim
                                        row.get('parentNode').removeChild(row);
                                    });
                                    // if all strings unstaged, reload the stage screen
                                    if (numofstrings.staged <= 0) {
                                        location.href = M.cfg.wwwroot + '/local/amos/stage.php';
                                    }
                                } else {
                                    args.button.get('parentNode').setContent('Error: ' + result.error);
                                    args.button.get('parentNode').addClass('error');
                                }
                            },
                            failure: function(transid, outcome, args) {
                                var debuginfo = outcome.statusText;
                                if (M.cfg.developerdebug) {
                                    debuginfo += ' (' + ajaxurl + ')';
                                }
                                args.button.get('parentNode').addClass('error');
                                args.button.get('parentNode').setContent(debuginfo);
                            }
                        },
                    };
                    Y.io(ajaxurl, ajaxcfg);
                }, this, button);
            }, this);
        },

        setup_diffmode_switcher: function() {
            var links = Y.all('#amosstage .translation .diffmode');
            links.setContent(M.util.get_string('diffstaged', 'local_amos'));
            links.on('click', function(e) {
                e.halt();
                var link = e.currentTarget;
                var cell = link.ancestor('td');
                cell.all('.stringtext').each(function(stringtext) {
                    if (stringtext.getStyle('display') == 'none') {
                        stringtext.setStyle('display', 'block');
                    } else if (stringtext.getStyle('display') == 'block') {
                        stringtext.setStyle('display', 'none');
                    }
                });
            });
        }

    }, {
        NAME : 'amos_stage',
        ATTRS : { }
    });

    M.local_amos = M.local_amos || {};

    M.local_amos.init_stage = function(config) {
        M.local_amos.STAGE = new STAGE(config);
    }

}, '@VERSION@', { requires:['base', 'io-base', 'json-parse', 'anim'] });
