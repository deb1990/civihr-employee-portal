(function($) {
    'use strict';

    /**
     * Define HRReport object.
     */
    function HRReport() {
        var data = [];
        var pivotTableContainer = jQuery("#reportPivotTable");
        var orbContainer = null;
        var derivedAttributes = {};
        var orbInstance = null;
    }

    /**
     * Initialization function.
     * 
     * @param {JSON} options - Settings for the object.
     */
    HRReport.prototype.init = function (options) {
        $.extend(this, options);
    };

    /**
     * Init PivotTable.js library
     */
    HRReport.prototype.initPivotTable = function() {
        this.pivotTableContainer.pivotUI(this.data, {
            rendererName: "Table",
            renderers: CRM.$.extend(
                jQuery.pivotUtilities.renderers, 
                jQuery.pivotUtilities.c3_renderers,
                jQuery.pivotUtilities.export_renderers
            ),
            vals: ["Total"],
            rows: ["Employee gender"],
            cols: ["Contract location"],
            aggregatorName: "Count",
            unusedAttrsVertical: false,
            derivedAttributes: this.derivedAttributes
        }, false);
    }

    /**
     * Converts some number fields into Float for Orb library
     *
     * @param {JSON} data
     */
    HRReport.prototype.orbConvertData = function(data) {
        var orbData = [];
        for (var i in data) {
            var orbRow = [];
            for (var j in data[i]) {
                if (
                    j === 'Employee ID'
                ) {
                    data[i][j] = parseFloat(data[i][j]);
                }
                orbRow.push(data[i][j]);
            }
            orbData.push(orbRow);
        }
        var orbFields = [];
        var j = 0;
        for (var i in data[0]) {
            orbFields.push({
                name: j++,
                caption: i
            });
        }
        orbFields.push(
            {
                name: j++,
                caption: 'Duration',
                dataSettings: {
                    aggregateFunc: 'avg',
                    formatFunc: function(value) {
                        return Number(value).toFixed(0);
                    }
                }
            }
        );

        return {
            'data': orbData,
            'fields': orbFields
        }
    }

    /**
     * Initialize Orb library
     */
    HRReport.prototype.initOrb = function() {
        var orbConfig = function(f, d) {
            return {
                width: 1110,
                height: 645,
                dataSource: d,
                dataHeadersLocation: 'columns',
                theme: 'blue',
                toolbar: {
                    visible: true
                },
                grandTotal: {
                        rowsvisible: true,
                        columnsvisible: true
                },
                subTotal: {
                        visible: true,
                    collapsed: true
                },
                fields: f,
                rows    : [],
                columns : [],
                data    : [],
                preFilters : {
                }
            };
        };
        var orbElem = this.orbContainer;
        var orbConvertedData = this.orbConvertData(this.data);
        this.orbInstance = new orb.pgridwidget(orbConfig(orbConvertedData.fields, orbConvertedData.data));
        this.orbInstance.render(orbElem);
    }

    /**
     * Shows the Report
     */
    HRReport.prototype.show = function () {
        this.initPivotTable();
        this.initOrb();
        this.bindFilters();
    };

    /**
     * Refresh JSON data and Pivot Tables using provided filter values
     *
     * @param string filterValues
     */
    HRReport.prototype.refreshJson = function(filterValues) {
        var that = this;
        if (!this.jsonUrl) {
            return;
        }
        CRM.$.ajax({
            url: this.jsonUrl + filterValues,
            error: function () {
                console.log('Error refreshing Report JSON data.');
            },
            success: function (data) {
                // Refreshing Pivot Table:
                that.pivotTableContainer.pivotUI(data, {
                    rendererName: "Table",
                    renderers: CRM.$.extend(
                        jQuery.pivotUtilities.renderers, 
                        jQuery.pivotUtilities.c3_renderers
                    ),
                    unusedAttrsVertical: false
                }, false);
                // Refreshing Orb Pivot Table:
                var orbConvertedData = that.orbConvertData(data);
                that.orbInstance.refreshData(orbConvertedData.data);
            },
            type: 'GET'
        });
    }

    /**
     * Refresh data table using provided filter values
     *
     * @param string filterValues
     */
    HRReport.prototype.refreshTable = function(filterValues) {
        var that = this;
        if (!this.tableUrl) {
            return;
        }
        var tableDomId = this.getReportTableDomID();
        CRM.$.ajax({
            url: that.tableUrl + filterValues,
            error: function () {
                console.log('Error refreshing Report data table.');
            },
            success: function (data) {
                that.tableContainer.html(data);
                that.refreshReportTableViewInstance(tableDomId);
            },
            type: 'GET'
        });
    }

    /**
     * Return unique Drupal View's DOM ID of data table
     */
    HRReport.prototype.getReportTableDomID = function() {
        var reportTableDiv = CRM.$('#reportTable > div.view:first');
        var reportTableClasses = reportTableDiv.attr('class').split(' ');
        for (var i in reportTableClasses) {
            if (reportTableClasses[i].substring(0, 12) === 'view-dom-id-') {
                return reportTableClasses[i].substr(12);
            }
        }
        return null;
    }

    /**
     * Reinitialize View instance of data table for given View's DOM ID
     *
     * @param string viewReportDataTableId
     */
    HRReport.prototype.refreshReportTableViewInstance = function(viewReportDataTableId) {
        var viewReportDataTableSettings = Drupal.settings.views.ajaxViews['views_dom_id:' + viewReportDataTableId];
        var viewReportDataTableNewId = this.getReportTableDomID();

        delete Drupal.settings.views.ajaxViews['views_dom_id:' + viewReportDataTableId];
        delete Drupal.views.instances['views_dom_id:' + viewReportDataTableId];

        viewReportDataTableSettings.view_dom_id = viewReportDataTableNewId;
        Drupal.settings.views.ajaxViews['views_dom_id:' + viewReportDataTableNewId] = viewReportDataTableSettings;
        Drupal.views.instances['views_dom_id:' + viewReportDataTableNewId] = new Drupal.views.ajaxView(Drupal.settings.views.ajaxViews['views_dom_id:' + viewReportDataTableNewId]);
    }

    /**
     * Bind filters UI events
     */
    HRReport.prototype.bindFilters = function() {
        // Filters bindings
        var that = this;
        if (!this.filters) {
            return;
        }
        CRM.$('#expose-filters-btn').bind('click', function(e) {
            e.preventDefault();
            CRM.$(this).addClass('hidden');
            CRM.$('#collapse-filters-btn').removeClass('hidden');
            CRM.$('#report-filters').removeClass('hidden');
        });
        CRM.$('#collapse-filters-btn').bind('click', function(e) {
            e.preventDefault();
            CRM.$(this).addClass('hidden');
            CRM.$('#expose-filters-btn').removeClass('hidden');
            CRM.$('#report-filters').addClass('hidden');
        });
        CRM.$('#report-filters input[type="submit"]').bind('click', function(e) {
            e.preventDefault();
            var formSerialize = CRM.$('#report-filters form:first').formSerialize();
            if (that.jsonUrl) {
                that.refreshJson('?' + formSerialize);
            }
            if (that.tableUrl) {
                that.refreshTable('?' + formSerialize);
            }
        });
    }

    /**
     * Main Reports object instance
     */
    Drupal.behaviors.civihr_employee_portal_reports = {
        instance: null,
        attach: function (context, settings) {
            this.instance = new HRReport();

            // Tabs bindings
            CRM.$('.report-tabs a').bind('click', function(e) {
                e.preventDefault();
                CRM.$('.report-tabs li').removeClass('active');
                CRM.$(this).parent().addClass('active');
                CRM.$('.report-block').addClass('hidden');
                CRM.$('.report-block.' + CRM.$(this).data('tab')).removeClass('hidden');
            });
            CRM.$('.report-tabs a:first').click();
        }
    }
})(jQuery);
