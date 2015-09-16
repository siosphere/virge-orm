<?php
namespace Virge\ORM\Component;

use Virge\Database;
use Virge\Database\Exception\InvalidQueryException;

/**
 * 
 * @author Michael Kramer
 */

class Model extends \Virge\Core\Model {
    
    protected $_table = '';
    
    protected $_tableData = array();
    
    protected static $_globalTableData;
    
    protected $_connection = 'default';
    
    /**
     * Construct our object, will assign properties in key => value
     * @param array $data
     */
    public function __construct($data = array()) {
        
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
    public function delete($real = false) {
        
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
            if ($stmt->error == '') {
                $this->setLastError($stmt->error);
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
    public function save() {
        if ($this->_getPrimaryKeyValue()) {
            
            $this->setLastModifiedOn(new \DateTime);
            //update
            return $this->_update();
        }
        
        $this->setCreatedOn(new \DateTime);
        
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
            $values[] = $$field_name;
        }
        
        $this->setLastError(NULL);
        
        $sql = "INSERT INTO `{$this->_table}` (" . implode(',', $sql_fields) . ") VALUES (" . implode(',', $value_holder) . ")";
        $stmt = Database::connection($this->_connection)->prepare($sql, $values);
        $stmt->execute();
        $success = true;
        if ($stmt->error !== '') {
            $this->setLastError($stmt->error);
            $success = false;
        }
        
        $id = $stmt->insert_id;
        
        $stmt->close();
        
        $primaryKey = $this->_getPrimaryKey();
        
        $this->{$primaryKey} = $id;
        
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
                $primaryValue = $this->$field['field_name'];
                continue;
            }
            
            $sql_fields[] = '`' . $field['field_name'] . '` =?';
            
            $field_name = $field['field_name'];
            
            if(false !== ($overrideKey = $this->_getOverrideKey($field_name))){
                $field_name = $overrideKey;
            }
            
            if($this->$field_name instanceof \DateTime){
                $$field_name = $this->$field_name->format('Y-m-d H:i:s');
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
        if($stmt->error !== ''){
            $this->setLastError($stmt->error);
        }
        
        $stmt->close();
        
        return $this->getLastError() === NULL ? true : false;
    }
    
    /**
     * Load model by value (defaults to load from column `id`)
     * @param string $id
     * @param string $by_field
     * @return boolean
     * @throws \Exception
     */
    public function load($id = 0, $by_field = false) {
        
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
        
        $sql = "SELECT * FROM `{$this->_table}` WHERE `{$key_field}` =? LIMIT 0,1";
        $stmt = Database::connection($this->_connection)->prepare($sql, array($id));
        if(!$stmt){
            throw new InvalidQueryException('Failed to prepare SQL query: ' . $sql);
        }
        $stmt->execute();
        
        if ($row = $stmt->fetch_assoc()) {
            $data = $row;
        }
        $stmt->close();
        
        if(!isset($data)){
            return false;
        }
        
        foreach ($data as $key => $value) {
            $key = $this->_getKey($key);
            if(!$key) {
                continue;
            }
            
            $this->$key = $value;
        }
        
        return true;
    }
    
    /**
     * Get the table definition as an associative array
     * @return array|null
     */
    protected function _getDef() {
        
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
        $results = array();
        while($row = $collection->fetch()){
            $results[] = $row;
        }
        if(empty($results)){
            return array();
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
}