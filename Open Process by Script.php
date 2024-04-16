<?php
/*  Create Application in Other Process
*
*/

$baseURL = getenv("HOST_URL");
$bankName = getenv("BANK_NAME");
$accountApplicationProcessId = $config["accountApplicationProcessId"];  // ID OTHER PROCESSS


function createApplication($requestData) {
    global $accountApplicationProcessId;

    $pmheaders = [
        'Authorization' => 'Bearer ' . getenv('API_TOKEN'),
        'Accept'        => 'application/json',
    ];
    $apiHost = getenv('API_HOST');
    $event = "customer_start";      // EVENT PM (START EVENT)
    $client = new GuzzleHttp\Client(['verify' => false]);

    try {
        $res = $client->request("POST", $apiHost . "/process_events/$accountApplicationProcessId?event=$event", [
            "headers" => $pmheaders,
            "json" => (array) $requestData
        ]);
        $statusCode = $res->getStatusCode();
        if ($statusCode >= 200 && $statusCode <= 204) {
            $response = json_decode($res->getBody(), true);
            $applicationId = $response["id"];
            $createStatus = "Success";
        } else {
            $createStatus = "Failed";
            $errorMessage = "Request start failed with status code - " . $statusCode;
        }
    } catch (\Exception $e) {
        $createStatus = "Exception";
        $errorMessage = "Request start failed with error - " . $e->getMessage();
    }
    return [
        "createStatus" => $createStatus,
        "requestId" => $applicationId,
        "errorMessage" => $errorMessage
    ];
}

// Extract the details of the person into the primary contact after passing IDS
$primaryContact = $data['idaPersonBasicInfo'];
$primaryContact[0]["idaResponse"] = $data['idaResponse'];

// prepare the request data
$processId = $data["_request"]["process_id"];
$requestId = $data["_request"]["id"];
$applicationId = $data["applicationId"];

$requestData = [
    "aoRequestId" => $requestId,
    "AORequestId" => $requestId,
    "aoProcessId" => $processId,
    "applicationId" => $applicationId,
    "primaryContact" => $primaryContact,
    "currentRole" => "Customer",
    "baseURL" => $baseURL,
    "bankName" => $bankName,
    "requestStatus" => "Primary Owner Information",
    "pageTitle" => "Personal Account Opening",
    "bankerStarted" => false
];

// create the application request
$customerApplication = createApplication($requestData);

return [
    "baseURL" => $baseURL,
    "primaryContact" => $primaryContact,
    "customerApplication" => $customerApplication,
    "idaPersonBasicInfo" => null,
    "idaResponse" => null,
    "idaQuestions" => null
];