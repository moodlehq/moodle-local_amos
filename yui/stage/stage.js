/**
 * YUI module for AMOS stage
 *
 * @author  David Mudrak <david@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
YUI.add('moodle-local_amos-stage', function(Y) {

    var Stage = function() {
        Stage.superclass.constructor.apply(this, arguments);
    }

    Y.extend(Stage, Y.Base, {
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
            Y.all('#amosstage .unstagebutton').each(function(button) {
                // attach the onclick handler for the button that triggers the request
                button.on('click', function (e, button) {
                    e.halt();
                    // when the user clicks for the 1st time, let them to confirm it
                    if (!(button.hasClass('tobeconfirmed') || button.hasClass('confirmed'))) {
                        button.setContent(M.util.get_string('unstageconfirm', 'local_amos'));
                        button.addClass('tobeconfirmed');
                        button.replaceClass('btn-warning', 'btn-danger');
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
                                args.button.insert(wheel, 'after');
                            },
                            success: function(transid, outcome, args) {
                                try {
                                    result = Y.JSON.parse(outcome.responseText);
                                } catch(e) {
                                    result = { 'success': false, 'error': 'Can not parse response' };
                                }

                                if (result.success) {
                                    var row = args.button.ancestor('.string-control-group');
                                    // update the page heading - the number of staged strings after the removal
                                    var numofstrings = {staged: Y.all('#amosstage .string-control-group').size() - 1};
                                    Y.one('#numberofstagedstrings').setContent(M.util.get_string('stagestringsnocommit', 'local_amos', numofstrings));
                                    // remove the row from the table
                                    var anim = new Y.Anim({
                                        node: row,
                                        duration: 0.5,
                                        to: { height: 0 },
                                    });
                                    anim.run();
                                    anim.on('end', function() {
                                        this.get('node').remove(true);
                                    });
                                    // if all strings unstaged, reload the stage screen
                                    if (numofstrings.staged <= 0) {
                                        location.href = M.cfg.wwwroot + '/local/amos/stage.php';
                                    }
                                } else {
                                    args.button.setContent('Error: ' + result.error);
                                    args.button.addClass('btn-inverse');
                                }
                            },
                            failure: function(transid, outcome, args) {
                                var debuginfo = outcome.statusText;
                                if (M.cfg.developerdebug) {
                                    debuginfo += ' (' + ajaxurl + ')';
                                }
                                args.button.setContent(debuginfo);
                                args.button.addClass('btn-inverse');
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
            var amosstage = Y.one('#amosstage');
            if (amosstage) {
                amosstage.delegate('click', function(e) {
                    Y.log(e);
                    var link = e.currentTarget;
                    var cell = link.ancestor('td');
                    cell.all('.stringtext').each(function(stringtext) {
                        if (stringtext.getStyle('display') == 'none') {
                            stringtext.setStyle('display', 'block');
                        } else if (stringtext.getStyle('display') == 'block') {
                            stringtext.setStyle('display', 'none');
                        }
                    });
                }, '.translation .diffmode');
            }
        }

    }, {
        NAME : 'amos_stage',
        ATTRS : { }
    });

    M.local_amos = M.local_amos || {};

    M.local_amos.init_stage = function(config) {
        M.local_amos.Stage = new Stage(config);
    }

}, '@VERSION@', { requires:['base', 'io-base', 'json-parse', 'anim'] });
