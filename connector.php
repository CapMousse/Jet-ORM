<?php

class OrmConnector {
    protected static
        $instance,
        $connector;
    
    public static
        $config = array(),
        $quoteSeparator;

    /**
     * create the PDO object for request
     * @return \OrmConnector
     */
    protected function __construct(){
        if (!is_object(self::$connector)) {
            $connectionString = self::$config['type'].":host=".self::$config['host'].";dbname=".self::$config['base'];
            $username = self::$config['log'];
            $password = self::$config['pass'];
            
            try{
                $connector = new PDO($connectionString, $username, $password, null);
                $connector->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->setConnector($connector);
            }catch(Exception $e){
                //if the ORM is used with the Jet framework, use the Log class
                if(class_exists('Log')){
                    Log::fatal($e);
                }else{
                    trigger_error($e->getMessage(), E_USER_ERROR);
                }
            }
        }
    }
    
    /**
     * Set the PDO connector
     * @param PDO $connector 
     * @return void
     */
    private function setConnector($connector) {
        self::$connector = $connector;
        self::setQuoteSeparator();
    }

    /**
     * set the quote type for query
     */
    public static function setQuoteSeparator() {
        if (is_null(self::$quoteSeparator)) {
            switch(self::$connector->getAttribute(PDO::ATTR_DRIVER_NAME)) {
                case 'pgsql':
                case 'sqlsrv':
                case 'dblib':
                case 'mssql':
                case 'sybase':
                    self::$quoteSeparator = '"';
                    break;
                case 'mysql':
                case 'sqlite':
                case 'sqlite2':
                default:
                    self::$quoteSeparator = '`';
            }
        }
    }
    
    /**
     * Singleton for OrmConnector
     * @return \OrmConnector
     */
    public static function getInstance(){
        if(!isset(self::$instance) || !isset(self::$connector)){
            self::$instance = new self;
        }
        
        return self::$connector;
    }
}

?>
