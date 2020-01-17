<?php


namespace SyscloudLogger\SCLogger;
/**
 * Our custom class for DB exceptions.
 */
class DbException extends \Exception
{    
}


/**
 * Class to execute SQL queries for Master and transaction databases.
 * Actually it is just a wrapper for sqlsrv_ functions.
 */
class SqlHelper 
{
    // Connection options for MasterDB.
    private $connectionOptions;
    // MasterDB connection.
    private $masterDb = null;
    // TransDBs connection.
    private $transDbs = [];
    // Path to JSON file where we cache connection options.
    private $connectionOptionsCacheJsonPath = null;


    /**
     * Just constructor.
     * 
     * @param $connectionParamsJsonPath         Path to JSON file where we store connection options for MasterDB.
     * @param $connectionOptionsCacheJsonPath   If set, then we will cache connection
     *                                          options to this file.
     */
    public function __construct($connectionParamsJsonPath, $connectionOptionsCacheJsonPath = null)
    {
        $jsonData = file_get_contents($connectionParamsJsonPath);
        $this->connectionOptions = json_decode($jsonData, true);
        $this->connectionOptionsCacheJsonPath = $connectionOptionsCacheJsonPath;
    }

    /**
     * Just destructor. Close all connections here.
     */
    public function __destruct()
    {
        $this->close();
    }    
    
    /**
     * The function closes all opened connections.
     */
    public function close()
    {
        if ($this->masterDb)
        {
            sqlsrv_close($this->masterDb);
            $this->masterDb = null;
        }

        foreach ($this->transDbs as $conn)
            sqlsrv_close($conn);

        $this->transDbs = [];
    }    

    /**
     * The function to decrypt encrypted string. Used mainly to decrypt
     * values of DBName and DBPassword for TransDB connection.
     * 
     * @param $strEncrypted Encrypted string.
     * @param $strKey       Encryption key.
     * 
     * @return Decrypted value.
     */
    static public function decryptString($strEncrypted, $strKey)
    {
        $key = md5($strKey, true);
        $key = $key . $key;
        $keySize = 24;
        $tripleKey = substr($key, 0, 24);
        $decryptedString = openssl_decrypt($strEncrypted, 'DES-EDE3', $tripleKey);

        if (!$decryptedString)
            return $decryptedString;

        $lastByte = ord($decryptedString[strlen($decryptedString) - 1]);

        if ($lastByte == 0 || $lastByte > $keySize)
            return $decryptedString;

        $decryptedText = substr($decryptedText, 0, -$lastByte);

        return $decryptedText;
    }

    /**
     * Just executes SQL query in Master/Trans database.
     * 
     * @param $userId   Business user ID.
     * @param $query    SQL query string.
     * @param $values   List of values for query parameters.
     * @param $isInsert Flag for insert query.
     * @param $useAdvancedFetching  Flag for advanced fetching (set to true
     *                              when query/SP returns multiple result data sets.
     * 
     * @return Result rows.
     * 
     * @throws Exception on any SQL server error.
     */
    public function executeSqlQuery($userId, $query, $values = [],
        $isInsert = false, $useAdvancedFetching = false)
    {
        $conn = $this->getDbConnection($userId);

        if (!$conn)
        {
            error_log("DAL Query: Failed to get TransDB connection for $userId: " . $this->getLastSqlSrvErrorMessage());
            throw new DbException("Failed to get TransDB connection");
        }

        $stmt = sqlsrv_prepare($conn, $query, $values);

        if ($stmt === false)
        {
            $message = $this->getLastSqlSrvErrorMessage();
            error_log("DAL Query: $query - $message, parameters: " . json_encode($values));
            throw new DbException($message);
        }

        if (!sqlsrv_execute($stmt))
        {
            $message = $this->getLastSqlSrvErrorMessage();

            if (strpos($message, 'policyviolationsenumerator') === false &&
                strpos($message, 'duplicate key') === false)
            {
                error_log("DAL Query: $query - $message, parameters: " . json_encode($values));
            }

            sqlsrv_free_stmt($stmt);
            throw new DbException($message);
        }

        $rows = [];

        if ($isInsert)
        {
            $resultSetCount = 1;

            do
            {
                sqlsrv_next_result($stmt);
                sqlsrv_fetch($stmt);
                $rows = (int) sqlsrv_get_field($stmt, 0);

                if ($rows > 0 || $resultSetCount > 10)
                    break;

                $resultSetCount++;
            }
            while(1);
        }
        else
        {
            if ($useAdvancedFetching)
            {
                do
                {
                    while (1)
                    {
                        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                        
                        if (!$row)
                        {
                            $error = sqlsrv_errors();
                            
                            if (!$error || $error[0]['code'] == -28)
                                break;
                            
                            continue;
                        }
                        
                        $rows[] = $row;
                    }
                }
                while (sqlsrv_next_result($stmt));
            }
            else
            {
                while (1)
                {
                    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

                    if (!$row)
                    {
                        $error = sqlsrv_errors();
                        
                        if (isset($error[0]))
                            $error = $error[0];

                        if (!$error || $error['code'] == -28)
                            break;

                        continue;
                    }

                    $rows[] = $row;
                }
            }
        }

        sqlsrv_free_stmt($stmt);

        return $rows;
    }
    
    /**
     * Gets connection options from cache or MasterDB for the specified Trans DB.
     * 
     * @param $businessUserId   Business user ID.
     
     * @return Connection options.
     */
    function getConnectionOptions($businessUserId)
    {
        $connectionOptions = null;        
        $cacheData = [];
        $fd = null;
    
        if ($this->connectionOptionsCacheJsonPath)
        {
            $fd = fopen($this->connectionOptionsCacheJsonPath, "c+");
        }

        if ($fd)
        {
            flock($fd, LOCK_EX);

            $stat = fstat($fd);
            $size = $stat['size'];
            $modifiedTime = $stat['mtime'];

            if ($size > 0 &&
                time() - $modifiedTime < 60*60*24*7) // Update the cache every 1 week
            {
                $jsonData = fread($fd, $size);
                $cacheData = json_decode($jsonData, true);
            }
        }

        if (isset($cacheData[$businessUserId]))
        {
            $connectionOptions = $cacheData[$businessUserId];                    
        }
        else
        {        
            $query = "SELECT dbmap.DBName, serverdata.DBServerIPAddress, serverdata.DBUserName, serverdata.DBPassword
                FROM [SCSGBackup].[dbo].[UserDBMap_tbl] dbmap WITH (NOLOCK)
                INNER JOIN [SCSGBackup].[dbo].[DbServerMaster] serverdata WITH (NOLOCK) ON dbmap.DBServerID=serverdata.DBServerID
                WHERE dbmap.uid=?";

            try
            {
                $data = $this->executeSqlQuery('', $query, [$businessUserId]);
                if (empty($data))
                    throw new DbException("Failed to query connection options for $businessUserId");
                
                $row = $data[0];
                $ipAddress = $row['DBServerIPAddress'];

                $connectionOptions = [
                    "ServerName" => "tcp:$ipAddress",
                    "Database" => $row['DBName'],
                    "Uid" => $row['DBUserName'],
                    "PWD" => $row['DBPassword']
                ];
                                
                if ($fd)
                {
                    $cacheData[$businessUserId] = $connectionOptions;

                    fseek($fd, 0);
                    $jsonData = json_encode($cacheData);    

                    fwrite($fd, $jsonData, strlen($jsonData));
                    ftruncate($fd, strlen($jsonData));                              
                }                
            }
            catch (Exception $e)
            {
            }
        }
        
        if ($fd)
        {
            flock($fd, LOCK_UN);
            fclose($fd);
        }
                    
        if ($connectionOptions)
        {
            $connectionOptions['Uid'] = $this->decryptString($connectionOptions['Uid'], 'DBUserName');
            $connectionOptions['PWD'] = $this->decryptString($connectionOptions['PWD'], 'DBPassword');            
        }
            
        
        return $connectionOptions;
    }

    /**
     * Returns TransDB connection for the specified business user.
     * 
     * @param $businessUserId   Business user ID.
     * 
     * @return TransDB connection.
     * 
     * @throws Exception on connection error.
     */
    private function getDbConnection($businessUserId)
    {
        if ($this->masterDb == null)
        {
            $connectionOptions = $this->connectionOptions;
            $connectionOptions['Database'] = 'SCSGBackup';
            $serverName = $connectionOptions['ServerName'];
            unset($connectionOptions['ServerName']);

            for ($iTry=0; $iTry < 4; ++$iTry)
            {
                $this->masterDb = sqlsrv_connect($serverName, $connectionOptions);

                if ($this->masterDb || $iTry == 3)
                    break;

                sleep(1);
            }

            if ($this->masterDb == null)
            {
                $errorMessage = $this->getLastSqlSrvErrorMessage();
                throw new DbException($errorMessage);
            }
        }

        if ($businessUserId == '' || $businessUserId == 0)
            return $this->masterDb;

        // If we don't have connection options for TransDB, let's get them.
        if (!isset($this->transDbs[$businessUserId]))
        {            
            $connectionOptions = $this->getConnectionOptions($businessUserId);
            if (!$connectionOptions)
                throw new DbException("Failed to query connection options for $businessUserId");
                       
            $serverName = $connectionOptions['ServerName'];
            unset($connectionOptions['ServerName']);

            for ($iTry = 0; $iTry < 4; ++$iTry)
            {
                $conn = sqlsrv_connect($serverName, $connectionOptions);

                if ($conn || $iTry == 3)
                    break;

                sleep(1);
            }

            if ($conn == null)
            {
                $errorMessage = $this->getLastSqlSrvErrorMessage();
                throw new DbException($errorMessage);
            }

            $this->transDbs[$businessUserId] = $conn;
        }
        else
        {
            $conn = $this->transDbs[$businessUserId];
        }

        return $conn;
    }

    /**
     * Just returns the last sqlsrv error message.
     * 
     * @return SQL error message.
     */
    private function getLastSqlSrvErrorMessage()
    {
        $errors = sqlsrv_errors();

        if (!isset($errors[0]))
            return '';

        $errorDetails = $errors[0];
        $errorMessage = $errorDetails['message'];

        return $errorMessage;
    }   
}

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
