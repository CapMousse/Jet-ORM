<?php

class OrmWrapper {
    public 
        $class,
        $connector,
        $tableName = "";
    
    private
        $_idSelector = "id",
        $_isNew = false,
        $_rawQuery = null,
        $_distinct = false,
        $_resultSelector = array("*"),
        $_data = array(),
        $_dirty = array(),
        $_values = array(),
        $_join = array(),
        $_joinTables = array(),
        $_where = array(),
        $_limit = null,
        $_limitOffset = false,
        $_offset = null,
        $_order = null,
        $_orderBy = array(),
        $_groupBy = array();
    
    public static
        $log = array();

    /**
     * Set the connector to database. Define the table name. Define the id column
     * @return \OrmWrapper
     */
    function __construct(){
        $this->connector = OrmConnector::getInstance();
        $this->parseTableName();
        $this->setIdName('id_'.str_replace(OrmConnector::$quoteSeparator, '', $this->tableName));
    }
    
    /**
     * Get the current model name and parse to table name
     * @return void
     */
    private function parseTableName(){
        $this->class = get_called_class();
        $this->tableName = $this->setQuotes(strtolower(preg_replace('/(?!^)[[:upper:]]/', '_\0', $this->class)));
    }
    
    /**
     * Lauch the current query for selection
     * @return array
     */
    private function run(){
        if(!$this->connector){
            return false;
        }
        
        if(is_null($this->_rawQuery)){
            $query = $this->buildSelect();
        }else{
            $query = $this->_rawQuery;
        }
        self::$log[] = $query;
        
        try{
            $preparedQuery = $this->connector->prepare($query);
            $preparedQuery->execute($this->_values);
        }catch(Exception $e){
            self::logError($e, $query);
        }
        
        $rows = array();
        while($row = $preparedQuery->fetch(PDO::FETCH_ASSOC)){
            $rows[] = $row;
        }
        
        return $rows;
    }
    
    /**
     * Create the query used by the run method
     * @return string
     */
    private function buildSelect(){
        return $this->joinIfNotEmpty(array(
            $this->buildSelectStart(),
            $this->buildJoin(),
            $this->buildWhere(),
            $this->buildGroupBy(),
            $this->buildOrderBy(),
            $this->buildLimit(),
            $this->buildOffset(),
        ));
    }
    
    /**
     * Create the query begining
     * @return string
     */
    private function buildSelectStart(){
        $resultColumns = join(', ', $this->_resultSelector);

        if ($this->_distinct) {
            $resultColumns = 'DISTINCT '.$resultColumns;
        }

        $fragment = "SELECT {$resultColumns} FROM " . $this->tableName;

        return $fragment;
    }
    
    /**
     * Create the join query
     * @return string
     */
    private function buildJoin(){
        if(!count($this->_join)){
            return '';
        }
        
        return join(" ", $this->_join);
    }
    
    /**
     * Create the where query 
     * @return string
     */
    private function buildWhere(){
        if(!count($this->_where)){
            return '';
        }
        
        $return = array();
        
        foreach($this->_where as $where){
            $return[] = $where[0];
            $this->_values = array_merge($this->_values, $where[1]);
        }
        
        return "WHERE ".join(" AND ", $return);
    }
    
    /**
     * Create the Group By query
     * @return string
     */
    private function buildGroupBy(){
        if(!count($this->_groupBy)){
            return '';
        }
        
        return "GROUP BY ".join(",", $this->_groupBy);
    }
    
    /**
     * Create the Order By query
     * @param  $direction
     * @return string
     */
    private function buildOrderBy($direction = null){
        if(!count($this->_orderBy) || is_null($this->_order)){
            return '';
        }
        
        return "ORDER BY ".join(",", $this->_orderBy).' '.$this->_order;
    }
    
    /**
     * Create the Limit query
     * @return string
     */
    private function buildLimit(){
        if(is_null($this->_limit)){
            return '';
        }
        
        return "LIMIT ".($this->_limitOffset ? $this->_limitOffset."," : "")." ".$this->_limit;
    }
    
    /**
     * Create the Offset query
     * @return string
     */
    private function buildOffset(){
        if(is_null($this->_offset)){
            return '';
        }
        
        return "OFFSET ".$this->_offset;
    }
    
    /**
     * Create the Insert query
     * @return string
     */
    private function buildInsert(){
        $listFields = array_map(array($this, "setQuotes"), array_keys($this->_data));
        $values = $this->createPlaceholder($this->_data);
        
        $table = $this->tableName;
        $listFields = join(", ", $listFields);
        
        $query = "INSERT INTO $table ($listFields) VALUES ($values)";
        
        return $query;
    }
     
    /**
     * Create the Update query
     * @return string
     */
    private function buildUpdate(){
        $listFields = array();
        
        foreach($this->_dirty as $field => $value){
            $listFields[] = $this->setQuotes($field)." = ?";
        }
        
        $table = $this->tableName;
        $join = $this->buildJoin();
        $listFields = join(", ", $listFields);
        $id = $this->setQuotes($this->_idSelector);
        
        $query = "UPDATE $table $join SET $listFields WHERE $table.$id = ?";
        
        return $query;
    }
    
    /**
     * Hydrate the current model with send data
     * @param   array $data array of data 
     * @return  OrmWrapper
     */
    private function hydrate($data = array()){
        $this->_data = $data;
        return $this;
    }
    
    /**
     * Join different element from array to a string
     * @param   array $joinArray array of data 
     * @return  string
     */
    private function joinIfNotEmpty($joinArray){
        $returnArray = null;
        
        foreach($joinArray as $select){
            if(!empty($select)){
                $returnArray[] = trim($select);
            }
        }
        
        return join(" ", $returnArray);
    }
    
    /**
     * Add specific db quote to sent fragment
     * @param   string $fragment string to be quoted
     * @return  string
     */
    private function setQuotes($fragment){
        $parts = explode('.', $fragment);
        
        foreach($parts as &$part){
            $part = OrmConnector::$quoteSeparator . $part . OrmConnector::$quoteSeparator;
        }
        
        return join('.', $parts);
    }
   
    /**
     * Create instance from current model with specified row
     * @param   array $data  data to bound to new instance
     * @return  OrmWrapper
     */
    private function createInstance($data){
        $instance = clone $this;
        $instance->hydrate($data);
        return $instance;
    }
    
    /**
     * Create placeholder for data used in query
     * @param   array $dataArray
     * @return  string
     */
    private function createPlaceholder($dataArray){
        $number = count($dataArray);
        
        return join(",", array_fill(0, $number, "?"));
    }
    
    /**
     * Get id of current model
     * @return  int
     */
    public function getId(){
        return $this->__get($this->_idSelector);
    }
    
    /**
     * Set id column name for this model
     * @param   $name the id column name
     * @return  OrmWrapper
     */
    public function setIdName($name){
        $this->_idSelector = $name;
        
        return $this;
    }
    
    /**
     * Set the selected column in request
     * @param   string/array $rows
     * @return  OrmWrapper
     */
    public function select($rows){
        if(is_array($rows)){
            $rows = array_map(array($this, 'setQuotes'), $rows);
        }else{
            $rows = array($this->setQuotes($rows));
        }
        
        $this->_resultSelector = $rows;
        return $this;
    }
    
    /**
     * Create a where condition
     * @param   string $column      the column to be compared
     * @param   string $statement   type of comparison
     * @param   mixed  $value       value of comparison
     * @return  OrmWrapper
     */
    public function where($column, $statement, $value){
        if(!is_array($value)){
            $value = array($value);
        }
        
        $column = $this->setQuotes($column);
        
        $this->_where[] = array(" $column $statement ? ", $value);
        
        return $this;
    }
    
    /**
     * Create a join query
     * @param   string $type                type of join
     * @param   object $table               table to be join
     * @param   array/string $condition     condition of the join
     * @return  OrmWrapper
     */
    public function join($type, OrmWrapper $table, $conditions){
        $type = trim(strtoupper($type)." JOIN");
        $joinTable = $table->tableName;
        
        $this->_joinTables[] = $joinTable;
        $this->_join[] = "$type $joinTable ON ".$this->listJoinCondition($conditions);
        
        return $this;
    }
    
    /**
     * @param   array/string $conditions
     * @return  string
     */
    private function listJoinCondition($conditions){
        if(is_array($conditions)){
            $returnedConditions = "";
            
            foreach($conditions as $key => $value){
                $returnedConditions[] = ($key ? $key : "").$this->makeJoinCondition($value);
            }
            
            return join(" ", $returnedConditions);
        }
        
        return $conditions;
    }
    
    /**
     * @param   array/string  $conditions
     * @return  string
     */
    private function makeJoinCondition($condition){
        if(is_array($condition)){
            
            $joinTable = array_pop($this->_joinTables);
            list($firstCol, $statement, $lastCol) = $condition;
            $firstCol = $this->tableName.".".$this->setQuotes($firstCol);
            $lastCol = "$joinTable.".$this->setQuotes($lastCol);
            
            return "$firstCol $statement $lastCol";
        }
        
        return $condition;
    }

    /**
     * Set a limit to the query
     * @param   int offset
     * @param   bool|int $line
     * @return  current model
     */
    public function limit($offset, $line = false){
        if(!$line){
            $this->_limit = (int)$offset;
        }else{
            $this->_limitOffset = (int)$offset;
            $this->_limit = (int)$line;
        }

        return $this;
    }
    
    /**
     * Set a offset to the query
     * @param   int $offset
     * @return  current model
     */
    public function offset($offset){
        $this->_offset = (int)$offset;
        return $this;
    }
    
    /**
     * Set a distinct keyword to the query
     * @return  current model
     */
    public function distinct(){
        $this->_distinct = true;
        return $this;
    }
    
    /**
     * Set the group for the query
     * @param   string/array $rows
     * @return  OrmWrapper
     */
    public function group($rows){
        if(is_array($rows)){
            foreach($rows as &$row){
                $row = $this->setQuotes($row);
            }
        }else{
            $rows = array($this->setQuotes($rows));
        }
        
        $this->_groupBy = $rows;
        return $this;
    }
    
    /**
     * Set the rows used for order and direction
     * @param   string/array $rows
     * @param   string $direction
     * @return  current model
     */
    public function order($rows, $direction = 'ASC'){
        if(is_array($rows)){
            foreach($rows as &$row){
                $row = $this->setQuotes($row);
            }
        }else{
            $rows = array($this->setQuotes($rows));
        }
        $this->_orderBy = is_array($rows) ? $rows : array($rows);
        $this->_order = (in_array($direction, array('ASC', 'DESC'))) ? $direction : null;
        return $this;
    }
    
    /**
     * Create a new model
     * @param   array $data   data to be insert in the model
     * @return  current model
     */
    public function create($data = null){
        $this->_isNew = true;
        
        if(is_array($data)){
            $this->hydrate($data);
        }
        
        return $this;
    }
    
    /**
     * find the first elem of query
     * @param   array $id   search id
     * @return  OrmWrapper/false
     */
    public function findOne($id = null){
        if(!is_null($id)){
            $this->where($this->_idSelector, "=", $id);
        }
        $this->limit(1);
        $row = $this->run();
        
        if(empty($row)){
            return false;
        }
        
        return $this->hydrate($row[0]);
    }
    
    /**
     * find all elem of query
     * @return  OrmWrapper array/false
     */
    public function findMany(){
        $rows = $this->run();
        return $rows ? array_map(array($this, 'createInstance'), $rows) : false;
    }
    
    /**
     * save the state of current OrmWrapper
     * @return  boolean
     */
    public function save(){
        if(!$this->connector){
            return false;
        }
        
        $query = "";
        $values = array_values($this->_dirty);
        
        if($this->_isNew){
            $query = $this->buildInsert();
        }else{
            if(!count($values)){
                return true;
            }
            
            $query = $this->buildUpdate();
            $values[] = $this->getId();
        }
        
        self::$log[] = $query;
        self::$log[] = $values;
        
        $success = false;
        
        try{
            $query = $this->connector->prepare($query);
            $success = $query->execute($values);
        }catch(Exception $e){
            self::logError($e);
        }
        
        if($this->_isNew){
            $this->_isNew = false;
            
            if(is_null($this->getId())){
                $this->set($this->_idSelector, $this->connector->lastInsertId());
            }
        }
        
        return $success;
    }
    
    /**
     * Delete the current OrmWrapper
     * @return  boolean
     */
    public function delete(){
        $query = "DELETE FROM ".$this->tableName." WHERE ".$this->setQuotes($this->_idSelector)." = ?";
        $params = array($this->getId());
        
        self::$log[] = $query;
        
        try{
            $exec = $this->connector->prepare($query);
            $success = $exec->execute($params);
        }catch(Exception $e){
            self::logError($e);
        }
        
        return $success;
       
    }
    
    /**
     * Send a "manual" query to the orm
     * 
     * @param   string $query   the query to be run
     * @param   array  $values  values used in the query
     * @return  object current model
     */
    public function rawQuery($query, $values = array()){
        $this->_rawQuery = $query;
        $this->_values = $values;
        
        return $this->findMany();
    }
    
    /** 
     * Get the rows name of table
     * @return array
     */
    public function getRows(){
        if(count($this->_data) === 0){
            $test = $this->findOne();
        }
        
        return array_keys($this->_data);
    }
    
    /**
     * Get all data.
     * @return array
     */
    public function getAll(){
        return $this->_data;
    }
    
    public function __get($name){
        return isset($this->_data[$name]) ? $this->_data[$name] : null;
    }

    private function set($name, $value){
        $this->_data[$name] = $value;
        $this->_dirty[$name] = $value;
    }
    
    public function __set($name, $value){
        $this->set($name, $value);
    }

    public static function logError($error, $query = null){
        if(class_exists('Log')){
            if(null != $query){
                Log::info($query);
            }
            Log::fatal($error);
        }else{
            if(null != $query){
                self::$log[] = $query;
            }
            self::$log[] = $error;
        }
    }
}

?>