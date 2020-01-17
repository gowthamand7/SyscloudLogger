<?php

require_once 'sql_helper.php';
$connectionParamsJsonPath = __DIR__ . '/connection.json';

$obj = new SqlHelper($connectionParamsJsonPath);

$userId = "3744180140205146112";
$domainId = "GDR1079";
$error_code = "GDR1080";
$createdAt = date("Y-m-d H:i:s");
$cloudId = "1";
$eventStatus = "0";
$query = "EXEC SCS_InsertUserEventDetails @sUserId=?,@sDomainId=?,@sErrorCode=?,@sCreatedAt=?,@sCloudId=?,@sEventStatus=?";

$values = array(
    $userId,
    $domainId,
    $error_code,
    $createdAt,
    $cloudId,
    $eventStatus
    
);


$out = $obj->executeSqlQuery($userId, $query, $values,
        false, true);

print_r($out);




