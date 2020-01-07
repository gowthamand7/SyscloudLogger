<?php

namespace SyscloudLogger\SCLogger;

class SyscloudLogger
{
    public static $_handler;
    private $_config;
    
    public static function getInstance()
    {
        if(!self::$_handler)
        {
            self::$_handler = new SCLogger($this->_config->channelName, $this->_config->appName, $this->_config->appConfig);
        }
            
        return self::$_handler;    
    }
    
    public function setConfig($config)
    {
        $this->_config = $config;
    }
}

