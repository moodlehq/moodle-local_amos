/**
 * YUI module for AMOS string timeline overlay
 *
 * @author  David Mudrak <david@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
YUI.add('moodle-local_amos-timeline', function(Y) {

    var Timeline = function() {
        Timeline.superclass.constructor.apply(this, arguments);
    }

    Y.extend(Timeline, Y.Base, {
        initializer : function(config) {
            var translator = Y.one('#amostranslator');
            if (translator) {
                translator.delegate('click', this.display, '.info-timeline a', this);
            }
        },

        display : function(e, args) {
            e.preventDefault();
            var a = e.currentTarget;
            var ajaxurl = a.get('href') + '&ajax=1';
            var closebtn = Y.Node.create('<a id="closetimelinebox" href="#"><img src="' + M.util.image_url('t/delete', 'moodle') + '" /></a>');
            Y.use('overlay', 'io', function(Y) {
                var overlay = new Y.Overlay({
                    headerContent: closebtn,
                    bodyContent: Y.Node.create('<img src="' + M.util.image_url('i/loading', 'core') + '" class="spinner" />'),
                    id: 'timelinebox',
                    constrain: true,
                    visible: true,
                    centered: true,
                    width: '60%'
                });
                overlay.render(Y.one('body'));
                closebtn.on('click', function (e) {
                        e.preventDefault();
                        a.focus();
                        this.destroy();
                    }, overlay);
                closebtn.focus();

                var ajaxcfg = {
                    method: 'get',
                    context : this,
                    on: {
                        success: function(id, o, node) {
                            overlay.set('bodyContent', o.responseText);
                        },
                        failure: function(id, o, node) {
                            var debuginfo = o.statusText;
                            if (M.cfg.developerdebug) {
                                debuginfo += ' (' + ajaxurl + ')';
                            }
                            overlay.set('bodyContent', debuginfo);
                        }
                    }
                };

                Y.io(ajaxurl, ajaxcfg);
            });
        }

    }, {
        NAME : 'amos_timeline',
        ATTRS : {
                 aparam : {}
        }
    });

    M.local_amos = M.local_amos || {};

    M.local_amos.init_timeline = function(config) {
        M.local_amos.Timeline = new Timeline(config);
    }

}, '@VERSION@', { requires:['overlay'] });
