<?php

namespace SyscloudLogger\SCLogger;


use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RedisHandler;


class SCLogHandler
{
    private $_config;
    private $_handler = array();
    private $_formatType;
    private $_appName;
    private $_debug = 0;
    
    private static $_redis = null;
    private static $_fileStreamHandler = null;
    private static $_redisStreamHandler = null;
    
    /**
     * initialize logger
     * @param type $channel - Channel Name.
     * @param type $appName - Application Name.
     * @param type $configs - This parameter accepts keys (logfilepath,redisHost)
     * logfilepath (Example:- /home/ubuntu/SCSGBackup/SCSLog/)
     * redisHost (Example:- elasticcachehost.XXXXXX.XX.XXXX.XXX.cache.amazonaws.com)
     */
    public function __construct($channel, $appName, $configs = array())
    {
        $this->_config =  new SCLogConfig($channel, $configs);
        $this->_redishost = $this->_config->_redishost;
        $this->_appName = $appName;
        $this->_debug = $configs['debug'];
        
        $logger = new Logger($this->_config->_channel);
        $handlers = $this->getStreamHandler();
        foreach($handlers as $handler)
        {
            $pushHandler = $logger->pushHandler($handler);
        }
        $this->_handler = $pushHandler;
    }
    
    public function __destruct() 
    {
        
    }
    
    private function getCustomMessage($errorCode, $message)
    {
        if($message instanceof  \Exception)
        {
            $message = $message->getMessage();
        }

        return $this->getFormattedError($errorCode, $message);
    }
    
    public function debug($code, $message)
    {
        $this->_handler->addDebug(ErrorIntensity::SYS_LOG_DEBUG, $code, $this->getCustomMessage($code, $message));
    }

    public function info($code, $message)
    {
        $this->_handler->addInfo(ErrorIntensity::SYS_LOG_INFO, $code, $this->getCustomMessage($code, $message));
    }

    public function error($code, $message)
    {
        $this->_handler->addError(ErrorIntensity::SYS_LOG_ERROR, $code, $this->getCustomMessage($code, $message));
    }

    public function warning($code, $message)
    {
        $this->_handler->addWarning(ErrorIntensity::SYS_LOG_WARNING, $code, $this->getCustomMessage($code, $message));
    }

    public function alert($code, $message)
    {
        $this->_handler->addAlert(ErrorIntensity::SYS_LOG_ALERT, $code, $this->getCustomMessage($code, $message));
    }

    public function emergency($code, $message)
    {
        $this->_handler->addEmergency(ErrorIntensity::SYS_LOG_EMERGENCY, $code, $this->getCustomMessage($code, $message));
    }
    
    /**
     * function to send logs in respective channels
     * @param type $logLevel - Log level.
     * @param type $errorCode - Error Code.
     * @param type $message - Message.
     * @return boolean
     */
    public function log($logLevel, $errorCode, $message)
    {
        try
        {
            if($message instanceof  \Exception)
            {
                $message = $message->getMessage();
            }
            
            $errorMessage = $this->getFormattedError($errorCode, $message);
        
                $handler = $this->_handler;
                switch($logLevel)
                {
                    case ErrorIntensity::SYS_LOG_INFO:
                        $handler->addInfo($errorMessage);
                        break;

                    case ErrorIntensity::SYS_LOG_ERROR:
                        $handler->addError($errorMessage);
                        break;

                    case ErrorIntensity::SYS_LOG_WARNING:
                        $handler->addWarning($errorMessage);
                        break;

                    case ErrorIntensity::SYS_LOG_ALERT:
                        $handler->addAlert($errorMessage);
                        break;

                    case ErrorIntensity::SYS_LOG_EMERGENCY:
                        $handler->addEmergency($errorMessage);
                        break;
                    
                    case ErrorIntensity::SYS_LOG_DEBUG:
                        if($this->_debug)
                        {
                            $handler->addDebug($errorMessage);
                        }
                        break;
                }
          
        } 
        catch (Exception $ex) 
        {
            error_log("Problem with monologger");
        }
        
        return true;
    }
    
    /**
     * function to format the error.
     * @param type $errorCode - Error code.
     * @param type $message - Error Message.
     * @return type
     */
    private function getFormattedError($errorCode, $message)
    {
        $errorText = "";
        switch($this->_formatType)
        {
            case "text": 
                $errorText = "Code: " . $errorCode . " Message: " . $message;
                break;
            
            case "json":
                $errorText = array(
                    "Code" => $errorCode,
                    "Message" => $message,
                    "time" => time(),
                    "userId" => $this->_config->_userId
                );
                $errorText = json_encode($errorText);
                break;
        }
        
        return $errorText;
    }
    
    /**
     * function to select streams depends upon channels
     * @return type
     */
    private function getStreamHandler()
    {
        $stream = "";
        switch($this->_config->_stream)
        {
             case "file":
                 $stream = array($this->getFileStreamHandler());
                 $this->_formatType = "text";
                 break;
             
             case "redis":
                 $stream = array($this->getRedisStreamHandler());
                 $this->_formatType = "json";
                 break;
             
             case "file_redis":
                 $stream1 = $this->getRedisStreamHandler();
                 $stream2 = $this->getFileStreamHandler();
                 $this->_formatType = "json";
                 $stream = array($stream1, $stream2);
                 break;
             
             
             default: 
                 $stream = array($this->getFileStreamHandler());
                 $this->_formatType = "text";
                 break;
                 
        }
        return $stream;
    }
    
    /**
     * function to get file stream
     * @return StreamHandler
     */
    private function getFileStreamHandler()
    {
        if(!self::$_fileStreamHandler)
        {
            $filename = $this->_config->_filename;
            $path = pathinfo($filename, PATHINFO_DIRNAME);

            if(!file_exists($path))
            {
                mkdir($path, DIRECTORY_PERMISSIONS, true);
            }
            chmod($path, DIRECTORY_PERMISSIONS);

            self::$_fileStreamHandler =  new StreamHandler($filename, Logger::DEBUG);
        }
        
        return self::$_fileStreamHandler;
    }
    
    /**
     * function to get Redis cache stream
     * @return RedisHandler
     */
    private function getRedisStreamHandler()
    {
        if(!self::$_redis)
        {
            $host = $this->_redishost;
            $redis = new \Redis();
            $redis->pconnect($host, 6379);
            self::$_redis = $redis;
            unset($redis);
        }
        
        $setName = $this->getSetName();
        
        if(!self::$_redisStreamHandler)
        {
            self::$_redisStreamHandler =  new RedisHandler(self::$_redis, $setName, 'prod');
        }
        
        return self::$_redisStreamHandler;
    }
    
    /**
     * function to get formatted setKey
     * @return type
     */
    private function getSetName()
    {
        return $this->_appName . ":" . $this->_config->_businessUserId . ":" . $this->_config->_domainId . ":" . $this->_config->_cloudId . ":" . date("dmY");
    }
    
}


