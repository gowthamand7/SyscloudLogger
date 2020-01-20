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
        $this->insert($message);
    }
    
    public function addError($message)
    {
        $this->insert($message);
    }
    
    public function addWarning($message)
    {
        
    }
    public function addAlert($message)
    {
        $this->insert($message);
    }
    
    public function addEmergency($message)
    {
        $this->insert($message);
    }
    
    private function insert($message)
    {
        $details = json_decode($message, true);
        $query = "EXEC SCS_InsertUserEventDetails @sUserId=?,@sDomainId=?,@sErrorCode=?,@sCreatedAt=?,@sCloudId=?,@sEventStatus=?";

        $values = array(
            $details["userId"],
            $details["domainId"],
            $details["Code"],
            date("Y-m-d H:i:s", $details["time"]),
            $details["cloudId"],
            0

        );
        $businessUserId = $details["businessUserId"];
        
        if($businessUserId == null || $businessUserId == "")
            return;
        
        $this->executeSqlQuery($businessUserId, $query, $values,
        false, true);
    }

}

