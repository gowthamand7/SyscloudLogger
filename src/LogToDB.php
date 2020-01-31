<?php

namespace SyscloudLogger\SCLogger;

class LogToDB extends sqlHelper
{
    public function __construct($connectionParamsJsonPath, $connectionOptionsCacheJsonPath = null) 
    {
        parent::__construct($connectionParamsJsonPath, $connectionOptionsCacheJsonPath);
    }
    
    public function addDebug($message)
    {
        
    }
    
    public function addInfo($message)
    {
        try
        {
            $this->insert($message);
        } 
        catch (Exception $ex) {
            error_log("Error occurred while inserting log in DB" . $ex->getMessage());
        }
        
    }
    
    public function addError($message)
    {
        try
        {
            $this->insert($message);
        } 
        catch (Exception $ex) {
            error_log("Error occurred while inserting log in DB" . $ex->getMessage());
        }
    }
    
    public function addWarning($message)
    {
        try
        {
            $this->insert($message);
        } 
        catch (Exception $ex) {
            error_log("Error occurred while inserting log in DB" . $ex->getMessage());
        }
    }
    public function addAlert($message)
    {
        try
        {
            $this->insert($message);
        } 
        catch (Exception $ex) {
            error_log("Error occurred while inserting log in DB" . $ex->getMessage());
        }
    }
    
    public function addEmergency($message)
    {
        try
        {
            $this->insert($message);
        } 
        catch (Exception $ex) {
            error_log("Error occurred while inserting log in DB" . $ex->getMessage());
        }
    }
    
    private function insert($message)
    {
        $details = json_decode($message, true);
        $query = "EXEC SCS_InsertUserEventDetails @sUserId=?,@sDomainId=?,@sErrorCode=?,@sCreatedAt=?,@sCloudId=?,@sEventStatus=?,@additionalDescription=?,@actionId=?,@actionItemId=?";
   
        $values = array(
            $details["userId"],
            $details["domainId"],
            $details["Code"],
            date("Y-m-d H:i:s", $details["time"]),
            $details["cloudId"],
            0,
            $details["additionalInfo"],
            $details["actionId"],
            $details["actionResultId"]
        );
        $businessUserId = $details["businessUserId"];
        
        if($businessUserId == null || $businessUserId == "")
            return;
        
        $this->executeSqlQuery($businessUserId, $query, $values,
        false, true);
    }

}

