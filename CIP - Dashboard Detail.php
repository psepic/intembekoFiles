<?php

/*
  Get Data Dashboard Detai
  by Telmo Chiri
*/

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

//Global Variables
$apiSql = getenv('API_SQL');
$apiToken = getenv('API_TOKEN');
$apiHost = getenv('API_HOST');
$reportTable = getenv('CIP_VARIABLE_REPORT');

$dataReturn = [];

$reportName = $data["reportName"];
$orderBy = $data["columns"][$data["order"][0]['column']]['data'];
$orderType = $data["order"][0]['dir'];
$pageSize = $data['length'];
$pageNumber = $data["draw"];
$start = $data['start'];

$viewTable = $data["table"] ?? "VW_TOTAL_COUNT_CASES_RECEIVED";
$viewDetail = $data["task"] ?? "";
$currentStatus = $data["currentStatus"] ?? "";
$exception = $data["exception"] ?? "";
$dashboardType = $data["dashboardType"] ?? '';
//
$userId = $data['userId'] ?? '';

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


$getSqlColumns = apiGuzzle($apiHost . $apiSql, "POST", encodeSql($sql));

$sqlColumnExist = "SHOW COLUMNS FROM $viewTable";
$getSqlColumnsExist = apiGuzzle($apiHost . $apiSql, "POST", encodeSql($sqlColumnExist));

$allColumns = array_column($getSqlColumnsExist, 'Field');

array_unshift($allColumns, "no");


$head = "";
$columnsHead = [];
foreach ($getSqlColumns as $row) {
    $resp = array_search($row['variable'], $allColumns);
    if ($resp != false) {
        if($getSqlColumnsExist[$resp-1]['Type'] == "datetime" || $getSqlColumnsExist[$resp-1]['Type'] == "timestamp") {
			$head .= "CONVERT_TZ(T." . $row['variable'] . ",'UTC', '". ($data['timeZone'] ?? '') ."') as " . $row['variable'] . ", ";
		} else {
			$head .= "T." . $row['variable'] . ", ";
		}
        $columnsHead[] = ["value" => $row['variable'], "label" => $row['label']];
    }
}
$head = substr($head, 0, -2);

//Get Data Report
$html = "";
$sqlReport = "SELECT $head FROM $viewTable AS T";
$whereDashboard = "";
$andDashboard = "";
if ($dashboardType != '') {
    $whereDashboard = " WHERE dashboard = '$dashboardType'";
    $andDashboard = " AND dashboard = '$dashboardType'";
}
if ($viewDetail != "") {
    if ($viewDetail == "Returned from the Authoriser") {
        $sqlReport .= " INNER JOIN process_request_tokens AS R ON R.process_request_id = T.requestId
        WHERE R.element_name = 'Calc Processing Time Standards in Authorisation' AND R.data->>'$.gatewayAuthorisationLane' IN ('4','3','5') $andDashboard) AS returnAuthorise";
        goto executeQuery;
    }
    $fields = explode(",", $viewDetail);
    $fields = implode("', '", $fields);
    $sqlReport .= " WHERE element_name IN ('$fields') ";
    if ($currentStatus != "") {
        $sqlReport .= " AND currentStatus LIKE '$currentStatus'";
    }
    if ($exception != "") {
        $sqlReport .= " AND exception = '$exception'";
    }
    $sqlReport .= " $andDashboard";
} else {
    if ($currentStatus != "") {
        $sqlReport .= " WHERE currentStatus LIKE '$currentStatus' ";
        if ($exception != "") {
            $sqlReport .= " AND exception = '$exception'";
        }
        $sqlReport .= " $andDashboard";
    } else {
        if ($exception != "") {
            $sqlReport .= " WHERE exception = '$exception'";
            $sqlReport .= " $andDashboard";
        } else {
            $sqlReport .= " $whereDashboard";
        }
    }
}
executeQuery:
//return $sqlReport;
//Conditionals
//Total without Limits
$sqlReportTotalWithoutLimit = $sqlReport;
//Total without Limits
$responseReportTotal = apiGuzzle($apiHost . $apiSql, "POST", encodeSql($sqlReportTotalWithoutLimit));
if (!empty($orderBy)) {
    $sqlReport .= " ORDER BY " . $orderBy . " " . strtoupper($orderType);
}
$sqlReport .= " LIMIT " . $pageSize . " OFFSET " . $start;
$responseReport = apiGuzzle($apiHost . $apiSql, "POST", encodeSql($sqlReport));

$aDataReturn = [
    "draw" => $data['draw'],
    "recordsTotal" => count($responseReportTotal),
    "recordsFiltered" => count($responseReportTotal),
    "data" => $responseReport,
    "url" => $userId,
    "sql" => $sqlReport
];

return $aDataReturn;
// ------------------------------> Extra Functions <------------------------------//
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