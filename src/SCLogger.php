<?php

namespace SyscloudLogger\SCLogger;

class SCLogger
{
    public static $_handler;
    
    public static function getLogger($config)
    {
        if(!self::$_handler)
        {
            self::$_handler = new SCLogHandler($config->channelName, $config->appName, $config->appConfig);
        }
            
        return self::$_handler;    
    }
}

