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
        }

    }, {
        NAME : 'amos_stage',
        ATTRS : { }
    });

    M.local_amos = M.local_amos || {};

    M.local_amos.init_stage = function(config) {
        M.local_amos.STAGE = new STAGE(config);
    }

}, '@VERSION@', { requires:['base'] });
