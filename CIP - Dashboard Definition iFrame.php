<?php
/*
  Definition iframe Dashboard
  by Telmo Chiri
*/
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

//Global Variables
$apiSql = getenv('API_SQL');
$apiToken = getenv('API_TOKEN');
$apiHost = getenv('API_HOST');
$reportTable = getenv('CIP_VARIABLE_REPORT');
$collectionDashboardTypes = getenv('CIP_DASHBOARD_TYPES_ID');
$dataReturn = [];
$reportName = $data["reportName"];
$sqlDashboardTypes = "SELECT DT.data->>'$.CIP_DT_CODE' AS CODE, DT.data->>'$.CIP_DT_NAME' AS NAME
FROM collection_$collectionDashboardTypes AS DT
WHERE DT.data->>'$.CIP_DT_STATUS' = 'ACTIVE'";
$dashboardTypes = apiGuzzle($apiHost . $apiSql, "POST", encodeSql($sqlDashboardTypes));
//Get Columns Details
$sql = "SELECT fieldsTable.variable,";
$sql .= " fieldsTable.label";
$sql .= " FROM collection_" . $reportTable . " as R";
$sql .= " INNER JOIN JSON_TABLE(";
$sql .= " R.data->>'$.CIP_VR_REPORT_VARIABLES',";
$sql .= " '$[*]'";
$sql .= " COLUMNS(";
$sql .= " variable VARCHAR(50) PATH '$.CIP_VR_VARIABLE_NAME',";
$sql .= " label VARCHAR(50) PATH '$.CIP_VR_VARIABLE_DESCRIPTION',";
$sql .= " statusVariable VARCHAR(50) PATH '$.CIP_VR_STATUS'";
$sql .= " )) AS fieldsTable";
$sql .= " WHERE R.data->>'$.CIP_VR_REPORT_NAME' = '" . $reportName . "'";
$sql .= " and fieldsTable.statusVariable = 'ACTIVE'";
$getSqlColumns = apiGuzzle($apiHost . $apiSql, "POST", encodeSql($sql));

$sqlColumnExist = "SHOW COLUMNS FROM VW_TOTAL_COUNT_CASES_RECEIVED";
$getSqlColumnsExist = apiGuzzle($apiHost . $apiSql, "POST", encodeSql($sqlColumnExist));

$allColumns = array_column($getSqlColumnsExist, 'Field');
array_unshift($allColumns, "no");

$columnsHead = [];
foreach ($getSqlColumns as $row) {
    $resp = array_search($row['variable'], $allColumns);
    if ($resp != false) {
        $columnsHead[] = ["value" => $row['variable'], "label" => $row['label']];
    }
}
//Get Columns Dashboard by Agents
$sqlAgents = "SELECT fieldsTable.variable,";
$sqlAgents .= " fieldsTable.label";
$sqlAgents .= " FROM collection_" . $reportTable . " as R";
$sqlAgents .= " INNER JOIN JSON_TABLE(";
$sqlAgents .= " R.data->>'$.CIP_VR_REPORT_VARIABLES',";
$sqlAgents .= " '$[*]'";
$sqlAgents .= " COLUMNS(";
$sqlAgents .= " variable VARCHAR(50) PATH '$.CIP_VR_VARIABLE_NAME',";
$sqlAgents .= " label VARCHAR(50) PATH '$.CIP_VR_VARIABLE_DESCRIPTION',";
$sqlAgents .= " statusVariable VARCHAR(50) PATH '$.CIP_VR_STATUS'";
$sqlAgents .= " )) AS fieldsTable";
$sqlAgents .= " WHERE R.data->>'$.CIP_VR_REPORT_NAME' = 'Dashboard by Agents' ";
$sqlAgents .= " AND fieldsTable.statusVariable = 'ACTIVE'";
$getSqlAgentsColumns = apiGuzzle($apiHost . $apiSql, "POST", encodeSql($sqlAgents));

$sqlAgentsColumnExist = "SHOW COLUMNS FROM VW_TOTAL_IN_PROGRESS_TRACKING";
$getSqlAgentsColumnsExist = apiGuzzle($apiHost . $apiSql, "POST", encodeSql($sqlAgentsColumnExist));

$allAgentsColumns = array_column($getSqlAgentsColumnsExist, 'Field');
array_unshift($allAgentsColumns, "no");

$columnsAgentsHead = [];
foreach ($getSqlAgentsColumns as $row) {
    $resp = array_search($row['variable'], $allAgentsColumns);
    if ($resp != false) {
        $columnsAgentsHead[] = ["value" => $row['variable'], "label" => $row['label']];
    }
}
//Generate header table
$html = '<html><head><link href="https://cdnjs.cloudflare.com/ajax/libs/fomantic-ui/2.9.2/semantic.min.css" type="text/css" rel="stylesheet">';
$html .= '<link href="https://cdn.datatables.net/1.13.7/css/dataTables.semanticui.min.css" type="text/css" rel="stylesheet">
<script src="https://code.highcharts.com/dashboards/dashboards.js"></script>
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/highcharts-more.js"></script>
<script src="https://code.highcharts.com/modules/drilldown.js"></script>
<script src="https://code.highcharts.com/modules/solid-gauge.js"></script>
<script src="https://code.highcharts.com/modules/accessibility.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://code.highcharts.com/dashboards/css/dashboards.css" rel="stylesheet">
</head>';
$html .= '<body style="background: #FFFFFF;">';
// Tabs Definition
$html .= '<!-- Nav pills -->
    <ul class="nav nav-pills nav-justified" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="pill" href=".general" val="">General</a>
        </li>';
        foreach ($dashboardTypes as $dashboard) {
            $html .= '<li class="nav-item">
            <a class="nav-link" data-bs-toggle="pill" href=".general" val="' . $dashboard['CODE'] . '">' . $dashboard['NAME'] . '</a>
            </li>';
        }
    $html .= '</ul>
    <div id="loader" style="display:none;">
        <div class="row">
            <div class="col-md-4"></div>
            <div class="col-md-4">
                <img src="/public-files/INTEMBEKO-LOADING.svg">
            </div>
            <div class="col-md-4"></div>
        </div>
    </div>
    <!-- Dashboard General -->
    <div class="row p-1 pt-3 dGeneral" style="background: #f1f1f1;">
        <div class="col-md-3">
            <figure class="highcharts-figure">
                <div id="containerTotal"></div>
            </figure>
            <div class="row mt-2 d-none">
                <div class="col-md-6 d-none">
                    <figure class="highcharts-figure">
                        <div id="containerMM" class="chart-container2"></div>
                    </figure>
                </div>
                <div class="col-md-6 d-none">
                    <figure class="highcharts-figure">
                        <div id="containerFF" class="chart-container2"></div>
                    </figure>
                </div>
                <figure class="highcharts-figure d-none">
                    <div id="containerBars"></div>
                </figure>
            </div>
            <div id="containerRolledOver"></div>
        </div>
        <div class="col-md-9">
            <div class="row">
                <div class="col-md-3">
                    <figure class="highcharts-figure">
                        <div id="containerBacklog"></div>
                    </figure>
                </div>
                <div class="col-md-3">
                    <div id="containerProcessed"></div>
                </div>
                <div class="col-md-3">
                    <div id="containerProcesing"></div>
                </div>
                <div class="col-md-3">
                    <div id="containerAuthorised"></div>
                </div>
                <div class="col-md-3">
                    <div id="containerReturnedToClients"></div>
                </div>
                <div class="col-md-3">
                    <div id="containerReturnedFromClients"></div>
                </div>
                <div class="col-md-3">
                    <div id="containerPended"></div>
                </div>
                <div class="col-md-3">
                    <div id="containerReturnedFromPend"></div>
                </div>
                <div class="col-md-3">
                    <div id="containerOpenExceptions"></div>
                    <div id="containerCompletedExceptions"></div>
                </div>
                <div class="col-md-3">
                    <div id="containerReturnedFromTheAuthoriser"></div>
                </div>
                <div class="col-md-3 d-none">
                    <div id="containerCorrections"></div>
                </div>
                <div class="col-md-3 d-none">
                    <div id="containerSLAPerformance"></div>
                </div>
            </div>
        </div>
    </div>
    <!-- DataTable Agents -->
    <div class="row p-1 mt-3 dGeneral" style="background: #FFF;">
        <div class="col-md-12">
        <h3 style="padding-top: 0.45rem;font-size: 1.2em; color: rgb(51, 51, 51); font-weight: bold; text-align: center; fill: rgb(51, 51, 51); font-family: Helvetica, Arial, sans-serif;">In Progress tracking</h3>
        <h6 class="highcharts-subtitle" data-z-index="4" style="text-align:center; color: rgb(102, 102, 102); font-size: 0.8em; fill: rgb(102, 102, 102);" y="42" aria-hidden="true">
            In Validation, In Capture, In Authorisation
        </h6>
            <div style="overflow-x: hidden; overflow-y: scroll; width: 100%; padding: 1rem;">
                <table id="tableByAgents" class="ui celled table" style="width:100%">
                    <thead>
                        <tr>';
                            $columnsAgents = [];
                            for ($i = 0; $i < count($columnsAgentsHead); $i++) {
                                $html .= "<th>" . $columnsAgentsHead[$i]['label'] . "</th>";
                                $columnsAgents[] = ["data" => $columnsAgentsHead[$i]['value']];
                            }
                        $html .= '</tr>
                    </thead>
                    <tfoot>
                        <tr>';
                            for ($i = 0; $i < count($columnsAgentsHead); $i++) {
                                $html .= "<th>" . $columnsAgentsHead[$i]['label'] . "</th>";
                            }
                        $html .= '</tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>';
$html .= '<!-- The Modal Detail -->
    <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModal" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document" style="--bs-modal-width: 94% !important;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="myModal"> </h5>
                    <a type="button" class="btn-close" data-bs-dismiss="modal"></a>
                </div>
                <div class="modal-body">
                    <div style="overflow-x: scroll; overflow-y: scroll; width: 100%; padding: 1rem;">
                        <table id="TablePipelineReport" class="ui celled table" style="width:100%">
                            <thead>
                                <tr>';
                                for ($i = 0; $i < count($columnsHead); $i++) {
                                    $html .= "<th>" . $columnsHead[$i]['label'] . "</th>";
                                }
                        $html .= '</tr>
                            </thead>
                            <tfoot>
                                <tr>';
                                $columns = [];
                                for ($i = 0; $i < count($columnsHead); $i++) {
                                    $html .= "<th>" . $columnsHead[$i]['label'] . "</th>";
                                    $columns[] = ["data" => $columnsHead[$i]['value']];
                                }
                                $html .= '</tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <a type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</a>
                </div>
            </div>
        </div>
    </div> ';
$html .= '
    <style>
        .ui.celled.table>thead>tr>th {
        color: #000 !important;
        background-color: #E7A65F !important;
        border-color: #E7A65F !important;
        }
        table.dataTable.table>tbody>tr:hover{
        background-color: #ffe6ca;
        }
        .ui.pagination.menu .active.item {
        background-color: #E7A65F !important;
        }
        tfoot input {
        width: 100%;
        padding: 7px 4px;
        box-sizing: border-box;
        font-size: 1em;
        background: #fff;
        border: 1px solid rgba(34, 36, 38, .15);
        color: rgba(0, 0, 0, .87);
        border-radius: .28571429rem;
        box-shadow: 0 0 0 0 transparent inset;
        transition: color .1s ease, border-color .1s ease;
        }
        .ui.table>tfoot>tr>td, .ui.table>tfoot>tr>th {
            background: #eee !important;
        }
        #TablePipelineReport_filter, #tableByAgents_filter{
            display: none;
        }
        .chart-container {
            margin-top:-5rem;
        }
        .nav-justified {
            background: #FFF;
            border-bottom: 6px solid #ecebeb;
        }
        .nav-pills .nav-link.active, .nav-pills .show > .nav-link {
            background-color: #E7A65F !important;
            color: #FFF !important;
            font-weight: 600;
        }
        .nav-link {
            color: #8a8a8a !important;
        }
        .nav-pills {
            --bs-nav-pills-border-radius: var(--bs-border-radius);
            --bs-nav-pills-link-active-color: #fff;
            --bs-nav-pills-link-active-bg: #ff8100;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.semanticui.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fomantic-ui/2.9.2/semantic.min.js"></script>
    <script type="text/javascript">
        var totalCount = 0;
        var dataView = taskView = currentStatus = dashboardType = exception = "";
        // Pipelining function for DataTables. To be used to the `ajax` option of DataTables
        $.fn.dataTable.pipeline = function ( opts ) {
            // Configuration options
            var conf = $.extend( {
                pages: 2,
                url: "",
                data: null,
                method: "GET",
                alternativeReportName: ""
            }, opts );
            // Private variables for storing the cache
            var cacheLower = -1;
            var cacheUpper = null;
            var cacheLastRequest = null;
            var cacheLastJson = null;
            return function ( request, drawCallback, settings ) {
                var ajax          = false;
                var requestStart  = request.start;
                var drawStart     = request.start;
                var requestLength = request.length;
                var requestEnd    = requestStart + requestLength;
                if ( settings.clearCache ) {
                    ajax = true;
                    settings.clearCache = false;
                }
                else if ( cacheLower < 0 || requestStart < cacheLower || requestEnd > cacheUpper ) {
                    ajax = true;
                }
                else if ( JSON.stringify( request.order )   !== JSON.stringify( cacheLastRequest.order ) ||
                        JSON.stringify( request.columns ) !== JSON.stringify( cacheLastRequest.columns ) ||
                        JSON.stringify( request.search )  !== JSON.stringify( cacheLastRequest.search )
                ) {
                    ajax = true;
                }
                cacheLastRequest = $.extend( true, {}, request );
                if ( ajax ) {
                    if ( requestStart < cacheLower ) {
                        requestStart = requestStart - (requestLength*(conf.pages-1));
                        if ( requestStart < 0 ) {
                            requestStart = 0;
                        }
                    }
                    cacheLower = requestStart;
                    cacheUpper = requestStart + (requestLength * conf.pages);
                    request.start = requestStart;
                    request.length = requestLength*conf.pages;
                    if ( $.isFunction ( conf.data ) ) {
                        var d = conf.data( request );
                        if ( d ) { $.extend( request, d ); }
                    }
                    else if ( $.isPlainObject( conf.data ) ) {
                        $.extend( request, conf.data );
                    }
                    request.reportName = conf.alternativeReportName != "" ? conf.alternativeReportName : "'.$reportName.'";
                    request.table = conf.alternativeReportName != "" ? "" : dataView ?? "";
                    request.task = conf.alternativeReportName != "" ? "" : taskView ?? "";
                    request.currentStatus = conf.alternativeReportName != "" ? "" : currentStatus ?? "";
                    request.exception = conf.alternativeReportName != "" ? "" : exception ?? "";
                    request.timeZone = "'.($data['timeZone'] ?? '').'";
                    request.dashboardType = dashboardType ?? "";
                    let newData = {"data": JSON.stringify(request)}
                    settings.jqXHR = $.ajax( {
                        "type":     conf.method,
                        "url":      conf.url,
                        "data":     newData,
                        "dataType": "json",
                        "cache":    false,
                        "success":  function ( json ) {
                            cacheLastJson = $.extend(true, {}, json);
                            if ( cacheLower != drawStart ) {
                                json.data.splice( 0, drawStart-cacheLower );
                            }
                            json.data.splice( requestLength, json.data.length );
                            drawCallback( json );
                        }
                    } );
                }
                else {
                    json = $.extend( true, {}, cacheLastJson );
                    json.draw = request.draw;
                    json.data.splice( 0, requestStart-cacheLower );
                    json.data.splice( requestLength, json.data.length );
                    drawCallback(json);
                }
            }
        };
        $.fn.dataTable.Api.register( "clearPipeline()", function () {
            return this.iterator( "table", function ( settings ) {
                settings.clearCache = true;
            } );
        } );
        function getSubtitle(totalNumber) {
            return `<span style="font-size: 80px">${totalNumber}</span>`;
        }';
        // JS Nav Pills
        $html .= "
        $('.nav-pills a').click(function(){
            $(this).tab('show');
            $('.dGeneral').hide('hide');
            dashboardType = $(this).attr('val');
            getSpecificDashboard(dashboardType);
        })
        ";
        // Gauge Definition
        $html .= "const gaugeOptions = {
            chart: {
                type: 'solidgauge',
                height: '110%',
            },
            title: 'Processed Count',
            pane: {
                center: ['50%', '85%'],
                size: '90%',
                startAngle: -90,
                endAngle: 90,
                background: {
                    backgroundColor: Highcharts.defaultOptions.legend.backgroundColor || '#EEE',
                    innerRadius: '60%',
                    outerRadius: '100%',
                    shape: 'arc'
                }
            },
            exporting: {
                enabled: true
            },
            tooltip: {
                enabled: true
            },
            yAxis: {
                stops: [
                    [0.1, '#DF5353'], // green
                    [0.5, '#f4ed22'], // yellow
                    [0.9, '#55BF3B'] // red
                ],
                lineWidth: 0,
                tickWidth: 0,
                minorTickInterval: null,
                tickAmount: 1,
                title: { y: 80 },
                labels: { y: 16 }
            },
            plotOptions: {
                solidgauge: {
                    dataLabels: {
                        y: 5,
                        borderWidth: 0,
                        useHTML: true
                    }
                }
            }
        };
        $('#containerRolledOver').click(function(){
            dataView = 'VW_ROLLED_OVER_COUNTING';
            taskView = '';
            currentStatus = '';
            exception = '';
            $('#myModal').modal('show');
        });
        $('#containerOpenExceptions').click(function(){
            dataView = 'VW_TOTAL_COUNT_CASES_RECEIVED';
            taskView = '';
            currentStatus = '';
            exception = 'Yes';
            $('#myModal').modal('show');
        });
        $('#containerCompletedExceptions').click(function(){
            dataView = 'VW_AUTHORISED_COUNT';
            taskView = '';
            currentStatus = '';
            exception = 'Yes';
            $('#myModal').modal('show');
        });";
        $html .= '
        $(document).ready(function() {
            getSpecificDashboard("");
            $("#myModal").on("shown.bs.modal", function (event) {
                if ( $.fn.dataTable.isDataTable("#TablePipelineReport") ) {
                    $("#TablePipelineReport").empty();
                }
                let table = new DataTable("#TablePipelineReport", {
                    "processing": true,
                    "serverSide": true,
                    "bDestroy": true,
                    "ajax": $.fn.dataTable.pipeline( {
                        url: "'.$apiHost.'/pstools/script/get-data-json-dashboard-detail",
                        pages: 1,
                    } ),
                    "columns": '.json_encode($columns).',
                    "columnDefs": [{
                        "target": [1, 2],
                        "defaultContent": "-",
                        "targets": "_all"
                    }]
                } );
            })
        });
        function getSpecificDashboard(dashboardType) {
            dataTableByAgents();
            let request = {};
            request.timeZone = "'.($data['timeZone'] ?? '').'";
            request.dashboardType = dashboardType ?? "";
            let newData = {"data": JSON.stringify(request)}
            $.ajax( {
                "type":     "post",
                "url":      "'.$apiHost.'/pstools/script/get-data-json-dashboard-general",
                "data":     newData,
                "dataType": "json",
                "cache":    false,
                "beforeSend": function (xhr){
                    loader("show");
                },
                "success":  function ( json ) { ';
                    /**=========================== Total Count ===========================**/
                    $html .= 'totalCount = json.data[0] ? json.data[0].VW_TOTAL_COUNT_CASES_RECEIVED : 0;
                    // Create the chart
                    Highcharts.chart("containerTotal", {
                        chart: {
                            type: "pie",
                            height: "110%",
                        },
                        title: {
                            text: "Total count of cases"
                        },
                        subtitle: {
                            useHTML: true,
                            text: getSubtitle(totalCount),
                            floating: true,
                            verticalAlign: "middle",
                            y: 30
                        },
                        credits: { enabled: false},
                        legend: { enabled: true },
                        plotOptions: {
                            series: {
                                cursor: "pointer",
                                size: "100%",
                                innerSize: "65%",
                                point: {
                                    events: {
                                        click: function () {
                                            if (this.view) {
                                                dataView = this.view;
                                                taskView = "";
                                                currentStatus = "";
                                                exception = "";
                                                $("#myModal").modal("show");
                                            }
                                        }
                                    }
                                }
                            }
                        },
                        colors: ["#6D5959"],
                        series: [{
                            name: "Total",
                            colorByPoint: true,
                            data: [{
                                name: "Total count of cases",
                                y: totalCount,
                                view: "VW_TOTAL_COUNT_CASES_RECEIVED"
                            }]
                        }]
                    });';
                    /**=========================== Processed Count ===========================**/
                    $html .= " var processedCount = json.data[0] ? json.data[0].VW_PROCESSED_COUNTS : 0;
                    // The Processed gauge
                    const chartProcessed = Highcharts.chart('containerProcessed', Highcharts.merge(gaugeOptions, {
                        title: { text: 'Processed count' },
                        subtitle: {
                            text: 'Cases pushed from Capture instruction to Authorisation',
                            align: 'center'
                        },
                        yAxis: {
                            min: 0,
                            max:  totalCount,
                            title: {
                                text: ''
                            }
                        },
                        credits: { enabled: false },
                        series: [{
                            cursor: 'pointer',
                            name: 'VW_PROCESSED_COUNTS',
                            data: [processedCount],
                            dataLabels: {
                                format:
                                    '<div style=\'text-align:center\'>' +
                                    '<span style=\'font-size:25px\'>{y}</span><br/>' +
                                    '<span style=\'font-size:12px;opacity:0.4\'>cases</span>' +
                                    '</div>'
                            },
                            tooltip: { valueSuffix: ' cases' },
                            events: {
                                click: function () {
                                        if (this.name) {
                                            dataView = this.name;
                                            taskView = '';
                                            currentStatus = '';
                                            exception = '';
                                            $('#myModal').modal('show');
                                        }
                                    }
                            }
                        }]
                    }));
                    let point = chartProcessed.series[0].points[0];
                    point.update(point.y); ";
                    /**=========================== Backlog Count ===========================**/
                    $html .= "// The Backlog gauge
                    var backlogCount = json.data[0] ? json.data[0].VW_TOTAL_BACKLOGS_COUNTS : 0;
                    const chartBacklog = Highcharts.chart('containerBacklog', Highcharts.merge(gaugeOptions, {
                        title: { text: 'Backlog count' },
                        subtitle: {
                            text: 'Indexing, Validation, Authorisation',
                            align: 'center'
                        },
                        yAxis: {
                            min: 0,
                            max:  totalCount,
                            title: {
                                text: ''
                            }
                        },
                        credits: { enabled: false },
                        series: [{
                            cursor: 'pointer',
                            name: 'VW_TOTAL_BACKLOGS_COUNTS',
                            data: [backlogCount],
                            dataLabels: {
                                format:
                                    '<div style=\'text-align:center\'>' +
                                    '<span style=\'font-size:25px\'>{y}</span><br/>' +
                                    '<span style=\'font-size:12px;opacity:0.4\'>cases</span>' +
                                    '</div>'
                            },
                            tooltip: {
                                valueSuffix: ' cases'
                            },
                            events: {
                                click: function () {
                                        if (this.name) {
                                            dataView = this.name;
                                            taskView = '';
                                            currentStatus = '';
                                            exception = '';
                                            $('#myModal').modal('show');
                                        }
                                    }
                            }
                        }]
                    }));
                    let pointBacklog = chartBacklog.series[0].points[0];
                    pointBacklog.update(pointBacklog.y);";
                    /**=========================== In Progress counts Count ===========================**/
                    $html .= "// The In Progress counts gauge
                    var totalAuthorisationCount = json.data[0] ? json.data[0].TOTAL_AUTHORISATION : 0;
                    var totalCaptureCount = json.data[0] ? json.data[0].TOTAL_CAPTURE : 0;
                    var totalValidationCount = json.data[0] ? json.data[0].TOTAL_VALIDATION : 0;
                    Highcharts.chart('containerProcesing', {
                        chart: {
                            type: 'pie',
                            height: '110%',
                        },
                        title: {
                            text: 'In Progress counts',
                            align: 'center'
                        },
                        subtitle: {
                            text: 'Cases in Authorisation, Capture and Validation Stage',
                            align: 'center'
                        },
                        credits: {
                            enabled: false
                        },
                        accessibility: {
                            announceNewData: { enabled: true },
                            point: { valueSuffix: ''}
                        },
                        plotOptions: {
                            series: {
                                borderRadius: 5,
                                cursor: 'pointer',
                                size: '100%',
                                innerSize: '50%',
                                point: {
                                    events: {
                                        click: function () {
                                            if (this.view) {
                                                dataView = this.view;
                                                taskView = this.task;
                                                currentStatus = '';
                                                exception = '';
                                                $('#myModal').modal('show');
                                            }
                                        }
                                    }
                                },
                                dataLabels: [{
                                    enabled: true,
                                    distance: 15,
                                    format: '{point.name}'
                                }, {
                                    enabled: true,
                                    distance: '-30%',
                                    filter: {
                                        property: 'percentage',
                                        operator: '>',
                                        value: 5
                                    },
                                    format: '{point.y}',
                                    style: {
                                        fontSize: '0.9em',
                                        textOutline: 'none'
                                    }
                                }]
                            }
                        },
                        tooltip: {
                            headerFormat: '<span style=\"font-size:11px\">{series.name}</span><br>',
                            pointFormat: '<span style=\"color:{point.color}\">{point.name}</span>: <b>{point.y}</b> cases<br/>'
                        },
                        series: [
                            {
                                name: 'Cases in Process',
                                colorByPoint: true,
                                data: [
                                    {
                                        name: 'Authorisation Stage',
                                        y: totalAuthorisationCount,
                                        view: 'VW_TOTAL_COUNT_CASES_RECEIVED',
                                        task: 'Allocated for Authorisation,Authorisation Instruction'
                                    },
                                    {
                                        name: 'Capture Stage',
                                        y: totalCaptureCount,
                                        view: 'VW_TOTAL_COUNT_CASES_RECEIVED',
                                        task: 'Capture Instructions,Allocated for Capture'
                                    },
                                    {
                                        name: 'Validation Stage',
                                        y: totalValidationCount,
                                        view: 'VW_TOTAL_COUNT_CASES_RECEIVED',
                                        task: 'Validate Instruction,Allocated for Validation'
                                    }
                                ]
                            }
                        ]
                    });";
                    /**=========================== Authorised Count ===========================**/
                    $html .= "// The Authorised gauge
                    var authorisedCount = json.data[0] ? json.data[0].VW_AUTHORISED_COUNT : 0;
                    const chartAuthorised = Highcharts.chart('containerAuthorised', Highcharts.merge(gaugeOptions, {
                        title: { text: 'Authorised count' },
                        yAxis: {
                            min: 0,
                            max:  totalCount,
                            title: { text: '' }
                        },
                        credits: { enabled: false },
                        series: [{
                            cursor: 'pointer',
                            name: 'Authorised',
                            data: [authorisedCount],
                            dataLabels: {
                                format:
                                    '<div style=\'text-align:center\'>' +
                                    '<span style=\'font-size:25px\'>{y}</span><br/>' +
                                    '<span style=\'font-size:12px;opacity:0.4\'>cases</span>' +
                                    '</div>'
                            },
                            tooltip: { valueSuffix: ' cases' },
                            events: {
                                click: function () {
                                        if (this.name) {
                                            dataView = 'VW_AUTHORISED_COUNT';
                                            taskView = '';
                                            currentStatus = '';
                                            exception = '';
                                            $('#myModal').modal('show');
                                        }
                                    }
                            }
                        }]
                    }));
                    let pointAuthorised = chartAuthorised.series[0].points[0];
                    pointAuthorised.update(pointAuthorised.y);";
                    /**=========================== Returned to Clients Count ===========================**/
                    $html .= "// The Returned to clients gauge
                    var returnedToClientCount = json.data[0] ? json.data[0].returnToClient : 0;
                    const chartReturnedToClients = Highcharts.chart('containerReturnedToClients', Highcharts.merge(gaugeOptions, {
                        title: { text: 'Returned to Clients' },
                        yAxis: {
                            min: 0,
                            max:  totalCount,
                        },
                        credits: { enabled: false },
                        series: [{
                            cursor: 'pointer',
                            name: 'Returned to clients',
                            data: [{
                                name: 'Returned to clients',
                                y: returnedToClientCount,
                                view: 'VW_TOTAL_COUNT_CASES_RECEIVED',
                                task: 'Await Client response'
                            }],
                            dataLabels: {
                                format:
                                    '<div style=\'text-align:center\'>' +
                                    '<span style=\'font-size:25px\'>{y}</span><br/>' +
                                    '<span style=\'font-size:12px;opacity:0.4\'>cases</span>' +
                                    '</div>'
                            },
                            tooltip: { valueSuffix: ' cases' },
                            events: {
                                click: function () {
                                        if (this.name) {
                                            dataView = this.options.data[0].view;
                                            taskView = this.options.data[0].task;
                                            currentStatus = '';
                                            exception = '';
                                            $('#myModal').modal('show');
                                        }
                                    }
                            }
                        }]
                    }));
                    let pointReturnedToClient = chartReturnedToClients.series[0].points[0];
                    pointReturnedToClient.update(pointReturnedToClient.y);";
                    /**=========================== Returned from Clients Count ===========================**/
                    $html .= "// The Returned from clients gauge
                    var returnedFromClientCount = json.data[0] ? json.data[0].returnFromClient : 0;
                    const chartReturnedFromClients = Highcharts.chart('containerReturnedFromClients', Highcharts.merge(gaugeOptions, {
                        title: { text: 'Returned from Clients'},
                        yAxis: {
                            min: 0,
                            max:  totalCount,
                            title: { text: '' }
                        },
                        credits: { enabled: false },
                        series: [{
                            cursor: 'pointer',
                            name: 'Returned from clients',
                            data: [{
                                name: 'Returned from clients',
                                y: returnedFromClientCount,
                                view: 'VW_TOTAL_COUNT_CASES_RECEIVED',
                                element_name: 'Allocated for Validation',
                                currentStatus: '%Returned To Client%'
                            }],
                            dataLabels: {
                                format:
                                    '<div style=\'text-align:center\'>' +
                                    '<span style=\'font-size:25px\'>{y}</span><br/>' +
                                    '<span style=\'font-size:12px;opacity:0.4\'>cases</span>' +
                                    '</div>'
                            },
                            tooltip: {
                                valueSuffix: ' cases'
                            },
                            events: {
                                click: function () {
                                        if (this.name) {
                                            dataView = this.options.data[0].view;
                                            taskView = this.options.data[0].element_name;
                                            currentStatus = this.options.data[0].currentStatus;
                                            exception = '';
                                            $('#myModal').modal('show');
                                        }
                                    }
                            }
                        }]
                    }));
                    let pointReturnedFromClient = chartReturnedFromClients.series[0].points[0];
                    pointReturnedFromClient.update(pointReturnedFromClient.y);";
                    /**=========================== Pendend Count ===========================**/
                    $html .= "// The Pended gauge
                    var pendedCount = json.data[0] ? json.data[0].returnToPending : 0;
                    const chartPended = Highcharts.chart('containerPended', Highcharts.merge(gaugeOptions, {
                        title: { text: 'Pended'},
                        yAxis: {
                            min: 0,
                            max:  totalCount,
                            title: { text: '' }
                        },
                        credits: { enabled: false },
                        series: [{
                            cursor: 'pointer',
                            name: 'Pended',
                            data: [{
                                name: 'Pended',
                                y: pendedCount,
                                view: 'VW_TOTAL_COUNT_CASES_RECEIVED',
                                task: 'Pending'
                            }],
                            dataLabels: {
                                format:
                                    '<div style=\'text-align:center\'>' +
                                    '<span style=\'font-size:25px\'>{y}</span><br/>' +
                                    '<span style=\'font-size:12px;opacity:0.4\'>cases</span>' +
                                    '</div>'
                            },
                            tooltip: {
                                valueSuffix: ' cases'
                            },
                            events: {
                                click: function () {
                                        if (this.name) {
                                            dataView = this.options.data[0].view;
                                            taskView = this.options.data[0].task;
                                            currentStatus = '';
                                            exception = '';
                                            $('#myModal').modal('show');
                                        }
                                    }
                            }
                        }]
                    }));
                    let pointPended = chartPended.series[0].points[0];
                    pointPended.update(pointPended.y);";
                    /**=========================== Returned From Pending Count ===========================**/
                    $html .= "// The Returned From Pending gauge
                    var returnedFromPendCount = json.data[0] ? json.data[0].returnFromPending : 0;
                    const chartReturnedFromPend = Highcharts.chart('containerReturnedFromPend', Highcharts.merge(gaugeOptions, {
                        title: { text: 'Returned From Pending'},
                        yAxis: {
                            min: 0,
                            max:  totalCount,
                            title: { text: '' }
                        },
                        credits: { enabled: false },
                        series: [{
                            cursor: 'pointer',
                            name: 'Returned From Pending',
                            data: [{
                                name: 'Returned From Pending',
                                y: returnedFromPendCount,
                                view: 'VW_TOTAL_COUNT_CASES_RECEIVED',
                                element_name: 'Allocated for Validation',
                                currentStatus: '%Pending%'
                            }],
                            dataLabels: {
                                format:
                                    '<div style=\'text-align:center\'>' +
                                    '<span style=\'font-size:25px\'>{y}</span><br/>' +
                                    '<span style=\'font-size:12px;opacity:0.4\'>cases</span>' +
                                    '</div>'
                            },
                            tooltip: {
                                valueSuffix: ' cases'
                            },
                            events: {
                                click: function () {
                                        if (this.name) {
                                            dataView = this.options.data[0].view;
                                            taskView = this.options.data[0].element_name;
                                            currentStatus = this.options.data[0].currentStatus;
                                            exception = '';
                                            $('#myModal').modal('show');
                                        }
                                    }
                            }
                        }]
                    }));
                    let pointReturnedFromPend = chartReturnedFromPend.series[0].points[0];
                    pointReturnedFromPend.update(pointReturnedFromPend.y);";

                    /**=========================== Open Exceptions Count ===========================**/
                    $html .= "// The Rolled over gauge
                    var openExceptionsCount = json.data[0] ? json.data[0].totalException : 0;
                    $('#containerOpenExceptions').html(`<div class='mt-1'>
                        <div style='background: white; cursor: pointer; padding-top: 0.45rem; padding-left: 2rem; padding-right: 2rem; padding-bottom: 2rem;'>
                        <h3 style='font-size: 1.2em; color: rgb(51, 51, 51); font-weight: bold; text-align: center; fill: rgb(51, 51, 51); font-family: Helvetica, Arial, sans-serif;'>Open Exceptions</h3>
                        <h1 style='text-align:center; font-size:4em; color: rgb(102, 102, 102);'>`+openExceptionsCount+`</h1>
                        <h5 style='text-align:center; font-size: 12px; opacity: 0.4; font-weight: bold;font-family: Helvetica, Arial, sans-serif;'>cases</h5>
                        </span>
                    </div>`);";

                    /**=========================== Completed Exceptions Comp Count ===========================**/
                    $html .= "// The Rolled over gauge
                    var completedExceptionsCount = json.data[0] ? json.data[0].totalExceptionCompleted : 0;
                    $('#containerCompletedExceptions').html(`<div class='mt-1'>
                        <div style='background: white; cursor: pointer; padding-top: 0.45rem; padding-left: 2rem; padding-right: 2rem; padding-bottom: 2rem;'>
                        <h3 style='font-size: 1.2em; color: rgb(51, 51, 51); font-weight: bold; text-align: center; fill: rgb(51, 51, 51); font-family: Helvetica, Arial, sans-serif;'>Completed Exceptions</h3>
                        <h1 style='text-align:center; font-size:4em; color: rgb(102, 102, 102);'>`+completedExceptionsCount+`</h1>
                        <h5 style='text-align:center; font-size: 12px; opacity: 0.4; font-weight: bold;font-family: Helvetica, Arial, sans-serif;'>cases</h5>
                        </span>
                    </div>`);";

                    /**=========================== Returned From Pending Count ===========================**/
                    $html .= "// The Returned From Pending gauge
                    var returnedFromTheAuthoriser = json.data[0] ? json.data[0].returnAuthorise : 0;
                    const chartReturnedFromTheAuthoriser = Highcharts.chart('containerReturnedFromTheAuthoriser', Highcharts.merge(gaugeOptions, {
                        title: { text: 'Returned from the Authoriser'},
                        yAxis: {
                            min: 0,
                            max:  totalCount,
                            title: { text: '' }
                        },
                        credits: { enabled: false },
                        series: [{
                            cursor: 'pointer',
                            name: 'Returned from the Authoriser',
                            data: [{
                                name: 'Returned from the Authoriser',
                                y: returnedFromTheAuthoriser,
                                view: 'VW_TOTAL_COUNT_CASES_RECEIVED',
                            }],
                            dataLabels: {
                                format:
                                    '<div style=\'text-align:center\'>' +
                                    '<span style=\'font-size:25px\'>{y}</span><br/>' +
                                    '<span style=\'font-size:12px;opacity:0.4\'>cases</span>' +
                                    '</div>'
                            },
                            tooltip: {
                                valueSuffix: ' cases'
                            },
                            events: {
                                click: function () {
                                        if (this.name) {
                                            dataView = this.options.data[0].view;
                                            taskView = 'Returned from the Authoriser';
                                            currentStatus = '';
                                            exception = '';
                                            $('#myModal').modal('show');
                                        }
                                    }
                            }
                        }]
                    }));
                    let pointReturnedFromTheAuthoriser = chartReturnedFromTheAuthoriser.series[0].points[0];
                    pointReturnedFromTheAuthoriser.update(pointReturnedFromTheAuthoriser.y);";

                    /**=========================== Bars  Count ===========================**/
                    $html .= "// The Feeder Fund gauge
                    Highcharts.chart('containerBars', {
                        chart: {
                            type: 'bar'
                        },
                        title: {
                            text: 'Historic World Population by Region',
                            align: 'left'
                        },
                        subtitle: {
                            text: 'Source',
                            align: 'left'
                        },
                        xAxis: {
                            categories: ['Africa', 'America', 'Asia', 'Europe'],
                            title: {
                                text: null
                            },
                            gridLineWidth: 1,
                            lineWidth: 0
                        },
                        yAxis: {
                            min: 0,
                            title: {
                                text: 'Population (millions)',
                                align: 'high'
                            },
                            labels: {
                                overflow: 'justify'
                            },
                            gridLineWidth: 0
                        },
                        tooltip: {
                            valueSuffix: ' millions'
                        },
                        plotOptions: {
                            bar: {
                                borderRadius: '50%',
                                dataLabels: {
                                    enabled: true
                                },
                                groupPadding: 0.1,
                                point: {
                                    events: {
                                        click: function () {
                                            alert('Category: ' + this.series.name + ', value: ' + this.y + ' >> ' + this.category);
                                        }
                                    }
                                }
                            }
                        },
                        legend: {
                            layout: 'vertical',
                            align: 'right',
                            verticalAlign: 'top',
                            x: -40,
                            y: 80,
                            floating: true,
                            borderWidth: 1,
                            backgroundColor:
                                Highcharts.defaultOptions.legend.backgroundColor || '#FFFFFF',
                            shadow: true
                        },
                        credits: {
                            enabled: false
                        },
                        series: [{
                            name: 'MM',
                            data: [1545, 727, 3202, 721]
                        }, {
                            name: 'FF',
                            data: [814, 841, 3714, 726]
                        }]
                    });
                    ";
                    /**=========================== Corrections Count ===========================**/
                    $html .= "// The Correction Counts PIE
                    var totalAuthorisationCount = json.data[0] ? json.data[0].TOTAL_AUTHORISATION : 0;
                    var totalCaptureCount = json.data[0] ? json.data[0].TOTAL_CAPTURE : 0;
                    var totalValidationCount = json.data[0] ? json.data[0].TOTAL_VALIDATION : 0;
                    var totalCorrectionsUT = 40;
                    var totalCorrectionsRL = 50;
                    Highcharts.chart('containerCorrections', {
                        chart: {
                            type: 'pie',
                            height: '110%',
                        },
                        title: {
                            text: 'Corrections',
                            align: 'center'
                        },
                        subtitle: {
                            text: 'Products domains with corrections',
                            align: 'center'
                        },
                        credits: {
                            enabled: false
                        },
                        accessibility: {
                            announceNewData: {
                                enabled: true
                            },
                            point: {
                                valueSuffix: ''
                            }
                        },
                        plotOptions: {
                            series: {
                                borderRadius: 5,
                                cursor: 'pointer',
                                size: '100%',
                                innerSize: '50%',
                                point: {
                                    events: {
                                        click: function () {
                                            if (this.name) {
                                                alert('Category: ' + this.name + ', value: ' + this.y);
                                            }
                                        }
                                    }
                                },
                                dataLabels: [{
                                    enabled: true,
                                    distance: 15,
                                    format: '{point.name}'
                                }, {
                                    enabled: true,
                                    distance: '-30%',
                                    filter: {
                                        property: 'percentage',
                                        operator: '>',
                                        value: 5
                                    },
                                    format: '{point.y}',
                                    style: {
                                        fontSize: '0.9em',
                                        textOutline: 'none'
                                    }
                                }]
                            }
                        },
                        tooltip: {
                            headerFormat: '<span style=\"font-size:11px\">{series.name}</span><br>',
                            pointFormat: '<span style=\"color:{point.color}\">{point.name}</span>: <b>{point.y}</b> cases<br/>'
                        },
                        series: [
                            {
                                name: 'Corrections',
                                colorByPoint: true,
                                data: [
                                    {
                                        name: 'UT',
                                        y: totalCorrectionsUT,
                                        view: 'VW_TOTAL_COUNT_CASES_RECEIVED',
                                        drilldown: 'pieUT'
                                    },
                                    {
                                        name: 'R&L',
                                        y: totalCorrectionsRL,
                                        view: 'VW_TOTAL_COUNT_CASES_RECEIVED',
                                        drilldown: 'pieRL'
                                    },
                                ]
                            }
                        ],
                        drilldown: {
                            series: [
                                {
                                    id: 'pieUT',
                                    data: [
                                        {
                                            name:'Cats',
                                        y:10,
                                        point: {
                                            events: {
                                                click: function () {
                                                    alert('Category: ' + this.name + ', value: ' + this.y);
                                                }
                                            }
                                        }
                                        },
                                        ['Dogs', 2],
                                        ['Cows', 1],
                                        ['Sheep', 2],
                                        ['Pigs', 1]
                                    ]
                                },
                                {
                                    id: 'pieRL',
                                    data: [
                                        {
                                            name: 'Animals',
                                            y: 5,
                                        }, {
                                            name: 'Fruits',
                                            y: 2,
                                        }, {
                                            name: 'Cars',
                                            y: 4,
                                        }
                                    ]
                                }
                            ]
                        }
                    });";
                    /**=========================== Corrections Count ===========================**/
                    $html .= "// The SLA Performance
                    const trackColors = Highcharts.getOptions().colors.map(color =>
                        new Highcharts.Color(color).setOpacity(0.3).get()
                    );
                    Highcharts.chart('containerSLAPerformance', {
                        chart: {
                            type: 'solidgauge',
                            height: '110%',
                            events: {}
                        },

                        title: {
                            text: 'SLA Performance',
                        },
                        credits: {
                            enabled: false
                        },
                        tooltip: {
                            borderWidth: 0,
                            backgroundColor: 'none',
                            shadow: false,
                            valueSuffix: '%',
                            pointFormat: '{series.name}<br>' +
                                '<span style=\'font-size: 2em; color: {point.color}; ' +
                                'font-weight: bold\'>{point.y}</span>',
                            positioner: function (labelWidth) {
                                return {
                                    x: (this.chart.chartWidth - labelWidth) / 2,
                                    y: (this.chart.plotHeight / 2) + 15
                                };
                            }
                        },

                        pane: {
                            startAngle: 0,
                            endAngle: 360,
                            background: [{ // Track for Conversion
                                outerRadius: '112%',
                                innerRadius: '88%',
                                backgroundColor: trackColors[0],
                                borderWidth: 0
                            }, { // Track for Engagement
                                outerRadius: '87%',
                                innerRadius: '63%',
                                backgroundColor: trackColors[1],
                                borderWidth: 0
                            }, { // Track for Feedback
                                outerRadius: '62%',
                                innerRadius: '38%',
                                backgroundColor: trackColors[2],
                                borderWidth: 0
                            }]
                        },

                        yAxis: {
                            min: 0,
                            max: 100,
                            lineWidth: 0,
                            tickPositions: []
                        },

                        plotOptions: {
                            solidgauge: {
                                dataLabels: {
                                    enabled: false
                                },
                                linecap: 'round',
                                stickyTracking: false,
                                rounded: true
                            }
                        },
                        series: [
                            {
                                name: 'Exception SLA',
                                data: [{
                                    color: Highcharts.getOptions().colors[0],
                                    radius: '112%',
                                    innerRadius: '88%',
                                    y: 80
                                }],
                                custom: {
                                    icon: 'filter',
                                    iconColor: '#303030'
                                }
                            }, {
                                name: 'Query SLA',
                                data: [{
                                    color: Highcharts.getOptions().colors[1],
                                    radius: '87%',
                                    innerRadius: '63%',
                                    y: 65
                                }],
                                custom: {
                                    icon: 'comments-o',
                                    iconColor: '#ffffff'
                                }
                            }, {
                                name: 'SLA',
                                data: [{
                                    color: Highcharts.getOptions().colors[2],
                                    radius: '62%',
                                    innerRadius: '38%',
                                    y: 50
                                }],
                                custom: {
                                    icon: 'commenting-o',
                                    iconColor: '#303030'
                                }
                            }
                        ]
                    });";
                    /**=========================== Rolled over Count ===========================**/
                    $html .= "// The Rolled over gauge
                    var rolledOverCount = json.data[0] ? json.data[0].VW_ROLLED_OVER_COUNTING : 0;
                    $('#containerRolledOver').html(`<div class='mt-1'>
                        <div style='background: white; cursor: pointer; padding-top: 0.45rem; padding-left: 2rem; padding-right: 2rem; padding-bottom: 2rem;'>
                        <h3 style='font-size: 1.2em; color: rgb(51, 51, 51); font-weight: bold; text-align: center; fill: rgb(51, 51, 51); font-family: Helvetica, Arial, sans-serif;'>Rolled Over count</h3>
                        <h1 style='text-align:center; font-size:4em; color: rgb(102, 102, 102);'>`+rolledOverCount+`</h1>
                        <h5 style='text-align:center; font-size: 12px; opacity: 0.4; font-weight: bold;font-family: Helvetica, Arial, sans-serif;'>cases</h5>
                        </span>
                    </div>`);";
                    $html .= '
                    // Already load
                    $(".dGeneral").show();
                },
                "complete": function(){
                    loader("hide");
                }
            } );
        }
        function dataTableByAgents() {
            if ( $.fn.dataTable.isDataTable("#tableByAgents") ) {
                $("#tableByAgents").empty();
            }
            let tableByAgents = new DataTable("#tableByAgents", {
                "processing": true,
                "serverSide": true,
                "bDestroy": true,
                "ajax": $.fn.dataTable.pipeline( {
                    url: "'.$apiHost.'/pstools/script/get-data-json-dashboard-by-agents",
                    pages: 1,
                    alternativeReportName: "Dashboard by Agents"
                } ),
                "columns": '.json_encode($columnsAgents).',
                "columnDefs": [{
                    "target": [1, 2],
                    "defaultContent": "",
                    "targets": "_all"
                }]
            });
        }
        function loader(action) {
            if (action == "show") {
                $("#loader").show();
            }
            if (action == "hide") {
                $("#loader").hide();
            }
        }
    </script>
</body>
</html>';
return [
    'PSTOOLS_RESPONSE_HTML' => $html
];
//------------------------------> Extra Functions <------------------------------//
function apiGuzzle($url, $requestType, $postfiles)
{
    global $apiToken, $apiHost;
    $headers = [
        "Content-Type" => "application/json",
        "Accept" => "application/json",
        'Authorization' => 'Bearer ' . $apiToken
    ];
    $client = new Client([
        'base_uri' => $apiHost,
        'verify' => false,
    ]);
    $request = new Request($requestType, $url, $headers, json_encode($postfiles));
    try {
        $res = $client->sendAsync($request)->wait();
        $res = $res->getBody()->getContents();
    } catch (Exception $exception) {
        $responseBody = $exception->getResponse()->getBody(true);
        $res = $responseBody;
    }
    $res = json_decode($res, true);
    return $res;
}
function encodeSql($string)
{
    $variablePut = [
        "SQL" => base64_encode($string)
    ];
    return $variablePut;
}