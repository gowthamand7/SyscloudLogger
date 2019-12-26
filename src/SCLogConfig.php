<?php

namespace SyscloudLogger\SCLogger;


class SCLogConfig
{
    
    /**
     * initialize Configurations
     * @param type $channel - Channel Name.
     * @param type $appName - Application Name.
     */   
    public function __construct($channel, $appName)
    {
        $this->_stream = "";
        $this->_channel = $channel;
        
        switch($channel)
        {
            case 'file_logger':
                $ths->_stream = "file";
                break;
            
            case 'cache_logger':
                 $ths->_stream = "redis";
                break;
        }
       
        $this->_filename = $this->getLogPath() . $appName . "-" . date("Y-m-d");
        $this->_redishost = $this->getElasticCacheHost();
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
        $elasticCacheHost = "backupelasticcache.h7tcur.ng.0001.use1.cache.amazonaws.com";
        
        return $elasticCacheHost;
    }
}

