/**
 * YUI module for AMOS stash page
 *
 * @author  David Mudrak <david.mudrak@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
YUI.add('moodle-local_amos-stash', function(Y) {

    var STASH = function() {
        STASH.superclass.constructor.apply(this, arguments);
    }

    Y.extend(STASH, Y.Base, {
        initializer : function(config) {
            this.setUpActionBar();
            this.protectDropStashButtons();
        },

        setUpActionBar : function() {
            Y.all('.stashwrapper .actions').setStyle('visibility', 'hidden');
            Y.all('.stashwrapper').on('mouseover', function(e) {
                Y.log(e.currentTarget);
                e.currentTarget.addClass('mousein');
                e.currentTarget.one('.actions').setStyle('visibility', 'visible');
            });
            Y.all('.stashwrapper').on('mouseout', function(e) {
                e.currentTarget.removeClass('mousein');
                e.currentTarget.one('.actions').setStyle('visibility', 'hidden');
            });
        },

        protectDropStashButtons : function() {
            Y.all('.stashwrapper .actions .drop form').on('submit', function(e) {
                if (!confirm(M.util.get_string('confirmaction', 'local_amos'))) {
                    e.halt();
                }
            });
        }

    }, {
        NAME : 'amos_stash',
        ATTRS : {
                 aparam : {}
        }
    });

    M.local_amos = M.local_amos || {};

    M.local_amos.init_stash = function(config) {
        return new STASH(config);
    }

}, '@VERSION@', { requires:['base', 'event-mouseenter'] });
