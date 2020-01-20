<?php

namespace SyscloudLogger\SCLogger;

use SyscloudLogger\SCLogger\LogToDB;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RedisHandler;


class SCLogHandler
{
    private $_config;
    private $_handler = array();
    private $_syscloudHandler = array();
    private $_formatType;
    private $_appName;
    private $_debug = 0;
    
    private static $_redis = null;
    private static $_fileStreamHandler = null;
    private static $_redisStreamHandler = null;
    private static $_dbStreamHandler = null;
    
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
        $streams = $this->getStreamHandler();
        $handlers = $streams['monolog'];
        foreach($handlers as $handler)
        {
            $pushHandler = $logger->pushHandler($handler);
        }

        $this->_handler = $pushHandler;
        $this->_syscloudHandler = $streams['syscloud'][0];
        
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
        try {
            $this->_handler->addDebug($this->getCustomMessage($code, $message));
        
            if(isset($this->_syscloudHandler))
                $this->_syscloudHandler->addDebug($this->getCustomMessage($code, $message));
            
        } catch (Exception $ex) {
            error_log("Error in monologger: " . $ex->getMessage());
        }
        
    }

    public function info($code, $message)
    {
         try {
        $this->_handler->addInfo($this->getCustomMessage($code, $message));
        
        if(isset($this->_syscloudHandler))
            $this->_syscloudHandler->addInfo($this->getCustomMessage($code, $message));
         } catch (Exception $ex) {
            error_log("Error in monologger: " . $ex->getMessage());
        }
    }

    public function error($code, $message)
    {
        try{
        $this->_handler->addError($this->getCustomMessage($code, $message));
        
        if(isset($this->_syscloudHandler))
            $this->_syscloudHandler->addError($this->getCustomMessage($code, $message));
         } catch (Exception $ex) {
            error_log("Error in monologger: " . $ex->getMessage());
        }
    }

    public function warning($code, $message)
    {
        try{
        $this->_handler->addWarning($this->getCustomMessage($code, $message));
        
        if(isset($this->_syscloudHandler))
            $this->_syscloudHandler->addWarning($this->getCustomMessage($code, $message));
         } catch (Exception $ex) {
            error_log("Error in monologger: " . $ex->getMessage());
        }
    }

    public function alert($code, $message)
    {
        try{
        $this->_handler->addAlert($this->getCustomMessage($code, $message));
        
        if(isset($this->_syscloudHandler))
            $this->_syscloudHandler->addAlert($this->getCustomMessage($code, $message));
         } catch (Exception $ex) {
            error_log("Error in monologger: " . $ex->getMessage());
        }
    }

    public function emergency($code, $message)
    {
        try{
        $this->_handler->addEmergency($this->getCustomMessage($code, $message));
        
        if(isset($this->_syscloudHandler))
            $this->_syscloudHandler->addEmergency($this->getCustomMessage($code, $message));
        
         } catch (Exception $ex) {
            error_log("Error in monologger: " . $ex->getMessage());
        }
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
                    "userId" => $this->_config->_userId,
                    "businessUserId" => $this->_config->_businessUserId,
                    "cloudId" => $this->_config->_cloudId,
                    "domainId" => $this->_config->_domainId,
                );
               // $errorText = json_encode($errorText);
                break;
        }
        
        return json_encode($errorText);
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
                 $stream = array(
                    "monolog" => array($this->getFileStreamHandler())
                     );
                 $this->_formatType = "text";
                 break;
             
             case "redis":
                 $stream = array(
                     "monolog" => array($this->getRedisStreamHandler())
                     );
                 $this->_formatType = "json";
                 break;
             
             case "file_redis":
                 $stream1 = $this->getRedisStreamHandler();
                 $stream2 = $this->getFileStreamHandler();
                 $this->_formatType = "json";
                 $stream = array(
                     "monolog" => array($stream1, $stream2)
                     );
                 break;
             
             case "file_redis_db":
                 $stream1 = $this->getRedisStreamHandler();
                 $stream2 = $this->getFileStreamHandler();
                 $stream3 = $this->getDBStreamHandler();
                 $this->_formatType = "json";
                 $stream = array(
                     "monolog" => array($stream1, $stream2), 
                     "syscloud" => array($stream3)
                         );
                 break;
             
             
             default: 
                 $stream = array(
                    "monolog" => array($this->getFileStreamHandler())
                     );
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
     * function to get sql stream handler.
     * @return type
     */
    private function getDBStreamHandler()
    {
        if(!self::$_dbStreamHandler)
        {
            $connectionParamsJsonPath = __DIR__ . '/connection.json';
            self::$_dbStreamHandler = new LogToDB($connectionParamsJsonPath);
        }
        
        return self::$_dbStreamHandler;
        
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


