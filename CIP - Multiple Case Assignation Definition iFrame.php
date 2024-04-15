<?php
/*
  Definition iFrame Multiple Case Assignation
  by Telmo Chiri
*/

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

if ($data['filter'] == '') {
    return [
        'PSTOOLS_RESPONSE_HTML' => ''
    ];
}

//Global Variables
$apiSql = getenv('API_SQL');
$apiToken = getenv('API_TOKEN');
$apiHost = getenv('API_HOST');
$reportTable = getenv('CIP_VARIABLE_REPORT');

$dataReturn = [];

$reportName = "Exception Report";
$timeZoneUser = $data["timeZone"];
$addSql = "";
if (!empty($data["filter"]["columnsReport"])) {
    $a = base64_decode(html_entity_decode($data["filter"]["columnsReport"]));
    $columnsReport = json_decode($a);
    $columnsReportString = implode("', '", $columnsReport);
    if ($columnsReportString != '') {
        $addSql = " AND fieldsTable.variable IN ('$columnsReportString')";
    }
}

//Get Columns
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
$sql .= $addSql;

$getSqlColumns = apiGuzzle($apiHost . $apiSql, "POST", encodeSql($sql));

$sqlColumnExist = "SHOW COLUMNS FROM INTEMBEKO_DATA";
$getSqlColumnsExist = apiGuzzle($apiHost . $apiSql, "POST", encodeSql($sqlColumnExist));
//*
$getSqlColumnsExist[] = array(
    "Field" => "exceptionTypeLabel",
    "Type" => "Text",
    "Calc" => "T.ACII_EXCEPTION_TYPE_LABEL as exceptionTypeLabel"
);
$getSqlColumnsExist[] = array(
    "Field" => "agentName",
    "Type" => "Text",
    "Calc" => "'' AS agentName"
);


$allColumns = array_column($getSqlColumnsExist, 'Field');

array_unshift($allColumns, "no");


$columnsHead = [];
foreach ($getSqlColumns as $row) {
    $resp = array_search($row['variable'], $allColumns);
    if ($resp != false) {
        //*
        $columnsHead[] = ["value" => $row['variable'], "label" => $row['label']];
    }
}

//Get Data Report
$html = "";

//Generate header table
$html = '<link href="https://cdnjs.cloudflare.com/ajax/libs/fomantic-ui/2.9.2/semantic.min.css" type="text/css" rel="stylesheet">';
$html .= '<link href="https://cdn.datatables.net/1.13.7/css/dataTables.semanticui.min.css" type="text/css" rel="stylesheet">';
$html .= '<div style="overflow-x: scroll; overflow-y: hidden; width: 100%; padding: 1rem;"><table id="TablePipelineReport" class="ui celled table" style="width:100%">';
$html .= '<thead>';
$html .= '<tr>';

for ($i = 0; $i < count($columnsHead); $i++) {
    $html .= "<th>" . $columnsHead[$i]['label'] . "</th>";
}
$html .= "<th>To Assign</th>";
$html .= "</tr></thead>";
//Column Filter
$html .= '<tfoot>';
$html .= '<tr>';
$columns = [];
for ($i = 0; $i < count($columnsHead); $i++) {
    $html .= "<th>" . $columnsHead[$i]['label'] . "</th>";
    $columns[] = ["data" => $columnsHead[$i]['value']];
}
$html .= "<th>To Assign</th>";
/*$columns[] = [
            "data" => 'active',
            "render" => (data, type, row) =>
                type === 'display'
                    ? ''
                    : data,
            className: 'dt-body-center'
        ]*/
// return [$columnsHead, $columns];
$html .= "</tr></tfoot>";

$html .= "</table></div>";
//return $html;
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
        #TablePipelineReport_filter{
            display: none;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
    <script src="https://cdn.datatables.net/2.0.3/js/dataTables.js"></script>
    <!--<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>-->
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.semanticui.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fomantic-ui/2.9.2/semantic.min.js"></script>

    <script src="https://cdn.datatables.net/fixedcolumns/5.0.0/js/dataTables.fixedColumns.js"></script>
    <script src="https://cdn.datatables.net/fixedcolumns/5.0.0/js/fixedColumns.dataTables.js"></script>
    <script src="https://cdn.datatables.net/select/2.0.0/js/dataTables.select.js"></script>
    <script src="https://cdn.datatables.net/select/2.0.0/js/select.dataTables.js"></script>

    <script type="text/javascript">
        //
        // Pipelining function for DataTables. To be used to the `ajax` option of DataTables
        //
        $.fn.dataTable.pipeline = function ( opts ) {
            // Configuration options
            var conf = $.extend( {
                pages: 1,     // number of pages to cache
                url: "",      // script url
                data: null,   // function or object with parameters to send to the server
                            // matching how `ajax.data` works in DataTables
                method: "GET" // Ajax HTTP method
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
                    // API requested that the cache be cleared
                    ajax = true;
                    settings.clearCache = false;
                }
                else if ( cacheLower < 0 || requestStart < cacheLower || requestEnd > cacheUpper ) {
                    // outside cached data - need to make a request
                    ajax = true;
                }
                else if ( JSON.stringify( request.order )   !== JSON.stringify( cacheLastRequest.order ) ||
                        JSON.stringify( request.columns ) !== JSON.stringify( cacheLastRequest.columns ) ||
                        JSON.stringify( request.search )  !== JSON.stringify( cacheLastRequest.search )
                ) {
                    // properties changed (ordering, columns, searching)
                    ajax = true;
                }

                // Store the request for checking next time around
                cacheLastRequest = $.extend( true, {}, request );

                if ( ajax ) {
                    // Need data from the server
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

                    // Provide the same `data` options as DataTables.
                    if ( $.isFunction ( conf.data ) ) {
                        // As a function it is executed with the data object as an arg
                        // for manipulation. If an object is returned, it is used as the
                        // data object to submit
                        var d = conf.data( request );
                        if ( d ) {
                            $.extend( request, d );
                        }
                    }
                    else if ( $.isPlainObject( conf.data ) ) {
                        // As an object, the data given extends the default
                        $.extend( request, conf.data );
                    }
                    //Format to PM
                    request.reportName = "'.$reportName.'";
                    request.userId = "'.$data['userIdException'].'"
                    request.timeZoneUser = "'.$data['timeZoneExceptionReport'].'"
                    request.filter = JSON.stringify('.json_encode($data['filter'], true).');
                    //
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
                            if(typeof json.data != "undefined") {
                                json.data.splice( requestLength, json.data.length );
                            }

                            drawCallback( json );
                        }
                    } );
                }
                else {
                    json = $.extend( true, {}, cacheLastJson );
                    json.draw = request.draw; // Update the echo for each response
                    json.data.splice( 0, requestStart-cacheLower );
                    json.data.splice( requestLength, json.data.length );

                    drawCallback(json);
                }
            }
        };
        // Register an API method that will empty the pipelined data, forcing an Ajax
        // fetch on the next draw (i.e. `table.clearPipeline().draw()`)
        $.fn.dataTable.Api.register( "clearPipeline()", function () {
            return this.iterator( "table", function ( settings ) {
                settings.clearCache = true;
            } );
        } );
        //
        // DataTables initialisation
        //
        $(document).ready(function() {

            let columns = '.json_encode($columns).';
            columns.push({
                data: "active",
                render: (data, type, row) =>
                    type === "display"
                        ? ""
                        : data,
                className: "dt-body-center"
            });
            let table = new DataTable("#TablePipelineReport", {
                "processing": true,
                "serverSide": true,
                "ajax": $.fn.dataTable.pipeline( {
                    url: "'.$apiHost.'/pstools/script/get-data-json-exception-report",
                    pages: 1 // number of pages to cache
                } ),
                "columns": columns,
                columnDefs: [
                    {
                        orderable: false,
                        render: DataTable.render.select(),
                        targets: 0
                    }
                ],
                select: {
                    style: "os",
                    selector: "td:first-child"
                },
                order: [[1, "asc"]]
            } );
            table.on("click", "td.dt-body-center", function (e) {
                alert("here");
                let tr = e.target.closest("tr");
                let row = table.row(tr);
                if (!row.child.isShown()) {
                    drawDetail(row.data(), modalAudit)
                }
            });
        } );
    </script>
    ';

return [
    'PSTOOLS_RESPONSE_HTML' => $html
];
// --> Extra Functions <--
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
