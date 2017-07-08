<?php
namespace Virge\ORM\Component;

use Virge\Database;
use Virge\Database\Exception\InvalidQueryException;

/**
 * 
 * @author Michael Kramer
 */

class Model extends \Virge\Core\Model 
{    
    const ERROR_CODE_OK = '00000';
    
    //const error types
    const ERR_DELETE = 'delete';
    const ERR_LOAD = 'load';
    const ERR_SAVE = 'save';
    
    protected $_table = '';
    
    protected $_tableData = [];
    
    protected static $_globalTableData;
    
    protected $_connection = 'default';

    protected $_updateLock = false;

    protected $_tracked = false;
    
    protected static $_cache = [];
    
    protected static function getFromCache($className, $keyField, $keyValue)
    {
        if(!isset(self::$_cache[$className]) || !isset(self::$_cache[$className][$keyField]) || !isset(self::$_cache[$className][$keyField][$keyValue]))
        {
            return null;
        }
        
        return self::$_cache[$className][$keyField][$keyValue];
    }
    
    protected static function setCache($className, $keyField, $keyValue, $object)
    {
        if(!isset(self::$_cache[$className])) {
            self::$_cache[$className] = [];
        }
        
        if(!isset(self::$_cache[$className][$keyField])) {
            self::$_cache[$className][$keyField] = [];
        }
        
        return self::$_cache[$className][$keyField][$keyValue] = $object;
    }

    protected static function clearCache()
    {
        self::$_cache = [];
    }
    
    /**
     * Construct our object, will assign properties in key => value
     * @param array $data
     */
    public function __construct($data = []) {
        
        if(!is_array($data)){
            return;
        }
        
        foreach ($data as $key => $value) {
            $key = $this->_getKey($key);
            if(!$key) {
                continue;
            }
            
            $this->$key = $value;
        }
    }
    
    /**
     * Allow external access to what connection the model is configured for
     * @return string
     */
    public function _getConnection(){
        return $this->_connection;
    }
    
    /**
     * Change default database connection
     * @param string $connection
     * @return \Virge\ORM\Component\Model
     */
    public function _connection($connection) {
        $this->_connection = $connection;
        return $this;
    }
    
    /**
     * Delete this model, if real is passed in, then actually delete the row,
     * otherwise just update the deleted on
     * @param type $real
     * @return type
     * @throws UnloadedModelException
     */
    public function delete($real = false) 
    {
        
        $primaryKey = $this->_getPrimaryKey();
        $primaryKeyValue = $this->_getPrimaryKeyValue();
        
        if($primaryKeyValue === NULL) {
            throw new UnloadedModelException("Attempted to deleted model that had no primary key");
        }
        
        $this->setLastError(NULL);
        
        if($real){
            $sql = "DELETE FROM `{$this->_table}` WHERE `{$primaryKey}` =? LIMIT 1";
            $stmt = Database::connection($this->_connection)->prepare($sql, array($primaryKeyValue));
            $stmt->execute();
            if ($stmt->errorCode() !== self::ERROR_CODE_OK) {
                $this->_handleError(self::ERR_DELETE, $this->_formatSQLError($stmt));
            }
            $stmt->close();
        } else {
            $this->setDeletedOn(date('Y-m-d H:i:s'));
            $this->save();
        }
        
        return $this->getLastError() === NULL ? true : false;
    }

    /**
     * Save this model
     * @return boolean
     */
    public function save() 
    {
        if ($this->_getTracked() && $this->_getPrimaryKeyValue()) {
            
            $this->setLastModifiedOn(new \DateTime);
            //update
            return $this->_update();
        }
        
        if(!$this->getCreatedOn()) {
            $this->setCreatedOn(new \DateTime);
        }
        
        return $this->_insert();
    }
    
    /**
     * Insert into the database table
     * @return boolean
     */
    protected function _insert() {
        $def = $this->_getDef();
        if(!isset($def) || !$def){
            return false;
        }
        
        foreach ($this->_tableData as $field) {
            $fields[] = $field['field_name'];
            $value_holder[] = '?';
            $sql_fields[] = '`' . $field['field_name'] . '`';
            $field_name = $field['field_name'];
            
            if(false !== ($overrideKey = $this->_getOverrideKey($field_name))){
                $field_name = $overrideKey;
            }
            
            $$field_name = $this->$field_name;
            
            if($$field_name instanceof \DateTime){
                $$field_name = $$field_name->format('Y-m-d H:i:s');
            }
            
            if(is_array($$field_name)){
                $$field_name = json_encode($$field_name);
            }
            
            $values[] = $$field_name;
        }
        
        $this->setLastError(NULL);
        
        $sql = "INSERT INTO `{$this->_table}` (" . implode(',', $sql_fields) . ") VALUES (" . implode(',', $value_holder) . ")";
        $stmt = Database::connection($this->_connection)->prepare($sql, $values);
        $stmt->execute();
        $success = true;

        if ($stmt->errorCode() !== self::ERROR_CODE_OK) {
            $this->_handleError(self::ERR_SAVE, $this->_formatSQLError($stmt));
            $success = false;
        }
        if($success){
            $id = Database::connection($this->_connection)->insertId();
            $primaryKey = $this->_getPrimaryKey();
            $this->{$primaryKey} = $id;
            //set tracked, saves will trigger updates
            $this->_setTracked(true);
        }
        $stmt->close();
        
        return $success;
    }

    /**
     * Update this model in the database
     * @return boolean
     */
    protected function _update() {
        $this->_getDef();
        
        //prepare
        foreach ($this->_tableData as $field) {
            if($field['primary']) {
                $primaryKey = $field['field_name'];
                $primaryValue = $this->$primaryKey;
                continue;
            }
            
            $sql_fields[] = '`' . $field['field_name'] . '` =?';
            
            $field_name = $field['field_name'];
            
            if(false !== ($overrideKey = $this->_getOverrideKey($field_name))){
                $field_name = $overrideKey;
            }
            
            if($this->$field_name instanceof \DateTime){
                $$field_name = $this->$field_name->format('Y-m-d H:i:s');
            } elseif(is_array($$field_name)){
                $$field_name = json_encode($$field_name);
            } else {
                $$field_name = $this->$field_name;
            }
            
            $values[] = $$field_name;
        }
        
        $values[] = $primaryValue;
        
        $sql = "UPDATE `{$this->_table}` SET " . implode(',', $sql_fields) . " WHERE `{$primaryKey}` =?";
        $stmt = Database::connection($this->_connection)->prepare($sql, $values);
        $stmt->execute();
        $this->setLastError(NULL);
        if ($stmt->errorCode() !== self::ERROR_CODE_OK) {
            $this->_handleError(self::ERR_SAVE, $this->_formatSQLError($stmt));
        }
        
        $stmt->close();

        if($this->_updateLock) {
            $this->_releaseLock();
        }
        
        return $this->getLastError() === NULL ? true : false;
    }

    public function loadForUpdate($id = 0, $by_field = false)
    {
        $this->_updateLock = true;
        return $this->load($id, $by_field);
    }
    
    /**
     * Load model by value (defaults to load from column `id`)
     * @param string $id
     * @param string $by_field
     * @return boolean
     * @throws \Exception
     */
    public function load($id = 0, $by_field = false, $use_cache = false) {
        
        if($id === NULL && $this->_getPrimaryKeyValue()){
            $id = $this->_getPrimaryKeyValue();
        }
        
        if (!$id) {
            return false;
        }
        
        $def = $this->_getDef();
        
        $key_field = '';
        
        foreach ($def as $field) {
            if ($field['primary'] == true && $by_field == false) {
                $key_field = $field['field_name'];
            } else if($field['field_name'] == $by_field){
                $key_field = $field['field_name'];
            }
        }
        
        if ($key_field == '') {
            return false;
        }

        if($this->_updateLock) {
            if(!Database::connection($this->_connection)->getResource()->inTransaction()) {
                if(!Database::connection($this->_connection)->beginTransaction()) {
                    throw new \RuntimeException("Failed to start transaction. SQL Error: " . Database::connection($this->_connection)->getError());
                }
            }
        }
        
        if(!$use_cache || null === ($data = self::getFromCache(static::class, $key_field, $id))) {
            $sql = "SELECT * FROM `{$this->_table}` WHERE `{$key_field}` =? LIMIT 0,1";
            if($this->_updateLock) {
                $sql .= " FOR UPDATE";
            }
            $stmt = Database::connection($this->_connection)->prepare($sql, array($id));
            if(!$stmt){
                throw new InvalidQueryException('Failed to prepare SQL query: ' . $sql);
            }
            $stmt->execute();
            if ($stmt->errorCode() !== self::ERROR_CODE_OK) {
                $this->_handleError(self::ERR_LOAD, $this->_formatSQLError($stmt));
            }
            $data = $stmt->fetch_assoc();
            $stmt->close();

            if(!$data){
                return false;
            }

            foreach ($data as $key => $value) {
                $key = $this->_getKey($key);
                if(!$key) {
                    continue;
                }
                
                $this->$key = $value;
            }
            
            static::setCache(static::class, $key_field, $id, $this);
        } else {
            foreach($data as $key => $value) {
                $key = $this->_getKey($key);
                if(!$key) {
                    continue;
                }
                
                $this->$key = $value;
            }
        }

        //set tracked, saves will trigger updates
        $this->_setTracked(true);
        
        return true;
    }
    
    /**
     * Get the table definition as an associative array
     * @return array|null
     */
    public function _getDef() {
        
        if (isset(self::$_globalTableData[$this->_table])) {
            return $this->_tableData = self::$_globalTableData[$this->_table];
        }
        
        if(!isset($this->_table) || $this->_table === ''){
            return;
        }

        //load from cache
        /*if(Cache::data('mysql:def:' . get_class($this))){
            return Cache::data('mysql:def:' . get_class($this));
        }*/
        
        $sql = "SHOW COLUMNS FROM `{$this->_table}`";
        $result = Database::connection($this->_connection)->query($sql);
        if($result){
            foreach ($result as $row) {
                $field_name = $row['Field'];
                $primary = false;
                if ($row['Key'] == 'PRI') {
                    $primary = true;
                }
                $type = $row['Type'];
                $this->_tableData[] = array(
                    'field_name'    => $field_name,
                    'type'          => $type,
                    'primary'       => $primary
                );
            }
            
            return self::$_globalTableData[$this->_table] = $this->_tableData; //Cache::data('mysql:def:' . get_class($this), $this->_tableData);
        }
        
        return NULL;
    }

    public function _getLock()
    {
        $this->_updateLock = true;
        Database::connection($this->_connection)->beginTransaction();
        if(!Database::query("SELECT null FROM `{$this->_table}` WHERE `{$this->_getPrimaryKey()}` = ? FOR UPDATE", [$this->_getPrimaryKeyValue()])) {
            throw new \RuntimeException("Failed to get lock: " . Database::connection($this->_connection)->getError());
        }

    }

    public function _releaseLock()
    {
        if(!Database::connection($this->_connection)->commit()) {
            throw new \RuntimeException("Failed to commit SQL Transaction: " . Database::connection($this->_connection)->getError());
        }

        $this->_updateLock = false;
    }

    public function _rollBack()
    {
        if(!Database::connection($this->_connection)->rollBack()) {
            throw new \RuntimeException("Failed to Rollback: " . Database::connection($this->_connection)->getError());
        }
        $this->_updateLock = false;
    }
    
    /**
     * Return the primary key from this model's database table
     * @return boolean|string
     */
    protected function _getPrimaryKey(){
        $def = $this->_getDef();
        foreach($def as $column){
            if($column['primary'] === true){
                return $column['field_name'];
            }
        }
        
        return false;
    }
    
    /**
     * Get the primary key value of this model
     * @return mixed
     */
    protected function _getPrimaryKeyValue(){
        $primaryKey = $this->_getPrimaryKey();
        return $this->{$primaryKey};
    }
    
    /**
     * Find a entity by filter callback
     * @param callable $filter
     * @param int $limit
     * @param int $start
     * @return array
     */
    public static function find($filter = NULL, $limit = 25, $start = 0){
        if(is_callable($filter)){
            $collection = Collection::model(get_called_class())->filter($filter);
        } else {
            $collection = Collection::model(get_called_class())->filter(function() use ($filter){
                Filter::start();
                Filter::eq('id', $filter);
                Filter::end();
            });
        }
        $collection->setLimit($limit);
        $collection->setStart($start);
        $collection = $collection->get();
        $results = [];
        while($row = $collection->fetch()){
            $results[] = $row;
        }
        if(empty($results)){
            return [];
        }
        return $results;
    }
    
    /**
     * Find single row with filter
     * @param type $filter
     * @return Model|null
     */
    public static function findOne($filter = NULL){
        
        $results = self::find($filter, 1);
        if(!empty($results)){
            return $results[0];
        }
        
        return NULL;
    }
    
    public function getSqlTable() {
        return $this->_table;
    }
    
    protected $_override;
    
    /**
     * Set our override key
     * @param type $key
     * @param type $original
     */
    protected function _overrideKey($key, $original) {
        $this->_override[$original] = $key;
    }
    
    /**
     * If we have an override key, return the original
     * @param string $key
     * @return string
     */
    protected function _getOverrideKey($key) {
        return isset($this->_override[$key]) ? $this->_override[$key] : false;
    }
    
    /**
     * 
     * @param string $key
     * @return string
     */
    protected function _getKey($key) {
        
        if(trim($key) === ''){
            return false;
        }
        
        if(strpos($key, ' ') !== false) {
            $original = $key;
            //we have an override key
            $key = str_replace(' ', '_', $key);
            $this->_overrideKey($key, $original);
        }
        
        return $key;
    }
    
    /**
     * Set value ( will call setter )
     * @param string $key
     * @param mixed $value
     * @return boolean
     * @throws \InvalidArgumentException
     */
    public function set($key, $value){
        
        $key = $this->_getKey($key);
        
        if(!is_string($key)){
            throw new \InvalidArgumentException("key must be a string");
        }
        
        if(!$key) {
            return false;
        }
        
        return parent::set($key, $value);
    }
    
    /**
     * Get value (will call getter )
     * @param string $key
     * @param mixed $defaultValue
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function get($key, $defaultValue = null) {
        
        $key = $this->_getKey($key);
        
        if(!is_string($key)){
            throw new \InvalidArgumentException("key must be a string");
        }
        
        if(!$key) {
            return null;
        }
        
        return parent::get($key, $defaultValue);
    }

    public function _setTracked($tracked)
    {
        $this->_tracked = $tracked;

        return $this;
    }

    public function _getTracked()
    {
        return $this->_tracked;
    }

    protected function _formatSQLError($stmt) : string
    {
        $info = $stmt->errorInfo();
        return sprintf("SQLSTATE [{$info[0]}] ({$info[1]}): {$info[2]}");
    }

    protected function _handleError($errorType, $errorMessage)
    {
        $this->setLastError($this->_formatSQLError($stmt));
    }
}