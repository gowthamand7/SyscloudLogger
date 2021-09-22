<?php

namespace SyscloudLogger\SCLogger;


class SCLogConfig
{
    
    /**
     * initialize Configurations
     * @param type $channel - Channel Name.
     * @param type $appName - Application Name.
     */   
    public function __construct($channel, $configs)
    {
        $this->_stream = "";
        $this->_channel = $channel;
        
        $this->_businessUserId = $configs['metadata']['businessuserId'];
        $this->_userId = $configs['metadata']['userid'];
        $this->_cloudId = $configs['metadata']['cloudid'];
        $this->_domainId = $configs['metadata']['domainid'];
        $this->_module = $configs['metadata']['module'];
        
        if(!$this->_businessUserId ||
                !$this->_userId ||
                !$this->_cloudId ||
                !$this->_domainId ||
                !$this->_module)
        {
            throw new \Exception("Error log Configuration was wrong, Missing arguments...");
        }
        
        switch($channel)
        {
            case 'file_logger':
                $this->_stream = "file";
                break;
            
            case 'cache_logger':
                 $this->_stream = "redis";
                break;
            
            case 'file_cache_logger':
                $this->_stream = "file_redis";
                break;
            
            case 'file_cache_db_logger':
                $this->_stream = "file_redis_db";
                break;
            
            case 'file_db_logger':
                $this->_stream = "file_db";
                break;
        }
       
        $logPath = $configs['logfilepath'];
        $rediscacheHost = $configs['redisHost'];
        
        if(!$logPath){
            $logPath = $this->getLogPath();
        }else{
            
            if(!file_exists($logPath)){
                mkdir($logPath, 0777, true);
            }            
        }
        if(!$rediscacheHost){
            $rediscacheHost = $this->getElasticCacheHost();
        }
        
        $this->_filename = $logPath . $this->_module . ".log." . date("Y-m-d");
        $this->_redishost = $rediscacheHost;
    }
    
    private function getLogPath()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
        {
            $logPath = "C:\\SCSGBackup\\SCSLog\\";
        }
        else
        {
            $logPath = "/home/ubuntu/SCSGBackup/SCSLog/";
        }
        
        return $logPath;
    }
    
    private function getElasticCacheHost()
    {
        //Pass you own elastic cache address
        $elasticCacheHost = "elasticcache.XXX.XX.XXXX.XXXX.cache.amazonaws.com";
        
        return $elasticCacheHost;
    }
}



class ErrorIntensity
{
   const SYS_LOG_DEBUG = 0; //debug
   const SYS_LOG_INFO = 1; //Info
   const SYS_LOG_ERROR = 2; //Permenant Error
   const SYS_LOG_WARNING = 3; //Temporary Error
   const SYS_LOG_ALERT = 4; //Need Attention
   const SYS_LOG_EMERGENCY = 5; //System Internal Error
}

