<?php

class OrmConnector {
    /**
     * Current instance of the connector
     * @var \OrmConnector
     */
    protected static $instance;

    /**
     * @var \PDO
     */
    protected static $connector;

    /**
     * The configuration array of the PDO connector
     * @var array
     */
    public static $config = array();

    /**
     * The quote separator for the asked database
     * @var string
     */
    public static $quoteSeparator;

    /**
     * create the PDO object for request
     * @return \OrmConnector
     */
    protected function __construct(){
        if (!is_object(self::$connector)) {
            
            if(isset(self::$config['socket']) && !empty(self::$config['socket'])){
                $connectionString = self::$config['socket'].";dbname=".self::$config['base'];
            }else{
                $connectionString = self::$config['type'].":host=".self::$config['host'].";dbname=".self::$config['base'];
            }
            
            $username = self::$config['log'];
            $password = self::$config['pass'];
            
            try{
                $connector = new PDO($connectionString, $username, $password);
                $connector->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $connector->query("SET NAMES utf8");
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
     * @return \PDO
     */
    public static function getInstance(){
        if(!isset(self::$instance) || !isset(self::$connector)){
            self::$instance = new self;
        }
        
        return self::$connector;
    }
}