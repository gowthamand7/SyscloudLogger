<?php

namespace SyscloudLogger\SCLogger;

class SyscloudLogger
{
    public static $_handler;
    public static $_config;
    
    public function getInstance()
    {
        if(!self::$_handler)
        {
            self::$_handler = new SCLogger(self::$_config->channelName, self::$_config->appName, self::$_config->appConfig);
        }
            
        return self::$_handler;    
    }
    
    public function setConfig($config)
    {
        self::$_config = $config;
    }
}

