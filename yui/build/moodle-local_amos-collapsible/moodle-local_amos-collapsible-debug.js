YUI.add('moodle-local_amos-collapsible', function (Y, NAME) {

var CSS = {
        CONTROL: 'collapsible-control',
        COLLAPSED: 'collapsible-collapsed'
    },
    SELECTORS = {
        WRAPPER: '',
        CONTROL: ''
    };

M.local_amos = M.local_amos || {};

M.local_amos.collapsible = {

    /**
     * @method init
     */
    init: function(selectors) {
        SELECTORS.WRAPPER = selectors.wrapper;
        SELECTORS.CONTROL = selectors.wrapper + ' ' + selectors.control;

        this.initState();
        this.initControls();
    },

    /**
     * @method initState
     */
    initState: function() {
        Y.all(SELECTORS.WRAPPER + '[data-initial-state="collapsed"]').addClass(CSS.COLLAPSED);
    },

    /**
     * @method initControls
     */
    initControls: function() {
        // Convert controls into links.
        Y.all(SELECTORS.CONTROL).each(function (control) {
            control.setHTML(Y.Node.create('<a href="#">' + control.getHTML() + '</a>'))
                   .addClass(CSS.CONTROL);
        });
        // Set up control links delegation.
        Y.one('body').delegate('click', this.handleControlClick, SELECTORS.CONTROL + ' a[href="#"]', this);
    },

    /**
     * @method handleControlClick
     */
    handleControlClick: function(e) {
        e.preventDefault();
        e.currentTarget.ancestor(SELECTORS.WRAPPER).toggleClass(CSS.COLLAPSED);
    }
};


}, '@VERSION@', {"requires": ["base", "node", "node-event-delegate"]});
