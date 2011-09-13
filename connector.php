<?php

class OrmConnector {
    protected static
        $instance,
        $connector;
    
    public static
        $config = array(),
        $quoteSeparator;
    
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
                Debug::log($e);
            }
        }
    }
    
    public function setConnector($connector) {
        self::$connector = $connector;
        self::setQuoteSeparator();
    }

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
    
    public static function getInstance(){
        if(!isset(self::$instance) || !isset(self::$connector)){
            self::$instance = new self;
        }
        
        return self::$connector;
    }
}

?>
