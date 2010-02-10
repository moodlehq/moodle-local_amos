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
 * @namespace
 */
M.local_amos = {};

/**
 * Called by PHP
 *
 * @param {Object} Y YUI instance
 * @param {String} container id if the DOM element to render translation table into
 * @param {String} datafeed url of the source of strings data
 */
M.local_amos.init_translator = function(Y, container, datafeed) {

    /**
     * @object translator
     */
    var translator = {

        /**
         * column definitions
         */
        aColumnDefs : [],

        /**
         * where the data for the table are fetched from
         */
        oDataSource : null,
        oTblCfg     : {},
        tbl         : null,

        oVersionMenuButton: null,

        /**
         * Defines columns and data source and initializes the table
         */
        init: function(Y, container, datafeed) {
            Y.use("yui2-oDataSource", "yui2-datatable", "yui2-menu", "yui2-button", function(Y) {

                // columns definition
                this.aColumnDefs = [
                    { key: "branch", label: "Branch", sortable: true },
                    { key: "component", label: "Component", sortable: true },
                    { key: "stringid", label: "String ID", sortable: true },
                    { key: "origin", label: "Origin" },
                    { key: "translation", label: "Translation" },
                ];

                // define source of table data
                this.oDataSource = new YAHOO.util.DataSource(datafeed);
                this.oDataSource.responseType = YAHOO.util.DataSource.TYPE_JSON;
                this.oDataSource.connXhrMode = "queueRequests";
                this.oDataSource.responseSchema = {
                    resultsList: "items",
                    fields: [
                        { key: "branch" },
                        { key: "component" },
                        { key: "stringid" },
                        { key: "origin" },
                        { key: "translation" },
                    ]
                };
                this.oDataSource.doBeforeParseData = function(oRequest, oFullResponse, oCallback) {
                    if (oFullResponse.code) {
                        if (oFullResponse.code > 299) {
                            alert(oFullResponse.text);
                            return {};
                        }
                    } else {
                        alert(oFullResponse);
                    }
                    return oFullResponse;
                };

                // table configuration
                this.oTblCfg = {
                    generateRequest : this.buildRequest,
                    handleDataReturnPayload : this.handlePayload,
                    initialLoad     : false,
                }

                // translation table
                this.tbl = new YAHOO.widget.DataTable(container, this.aColumnDefs, this.oDataSource, this.oTblCfg);
            });
        },

        buildRequest: function(oState, oSelf) {
            // Get states or use defaults
            oState          = oState || { pagination: null, sortedBy: null };
            var sort        = (oState.sortedBy) ? oState.sortedBy.key : oSelf.getColumnSet().keys[0].getKey();
            var dir         = (oState.sortedBy && oState.sortedBy.dir === YAHOO.widget.DataTable.CLASS_DESC) ? "desc" : "asc";
            var startIndex  = (oState.pagination) ? oState.pagination.recordOffset : 0;
            var results     = (oState.pagination) ? oState.pagination.rowsPerPage : 100;

            // Build custom request
            return  "?sort="        + sort +
                    "&dir="         + dir +
                    "&format=json"  +
                    "&version="     + CF.settings.state1;
        },

        handlePayload: function(oRequest, oResponse, oPayload) {
            // The payload object usually represents DataTable's state values, including:
            // oPayload.totalRecords = [number of total records]
            // oPayload.pagination.rowsPerPage = [number of rows per page]
            // oPayload.pagination.recordOffset = [index of first record of current page]
            // oPayload.sortedBy.key =  [key of currently sorted column]
            // oPayload.sortedBy.dir = [direction of currently sorted column]
            return oPayload;
        },

    }
    translator.init(Y, container, datafeed);
}
