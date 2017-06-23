<?php
namespace Virge\ORM\Component;

use Virge\Database;
use Virge\Database\Exception\InvalidQueryException;
use Virge\ORM\Component\Collection\Filter;
use Virge\ORM\Component\Collection\GroupBy;
use Virge\ORM\Component\Collection\Join;

/**
 * 
 * @author Michael Kramer
 */
class Collection extends \Virge\Core\Model {
    protected $model = NULL;
    protected $filters = array();
    protected $filterClosures = array();
    protected $joins = array();
    public $query = '';
    protected $start = 0;
    protected $limit = 25;
    protected $order = false;
    protected $dir = 'DESC';
    protected $result = NULL;
    protected $debug = false;
    protected $parameters = array();
    protected $stmt = null;
    protected $connection = 'default';
    protected $joinsBuilt = false;
    protected $whereBuilt = false;
    
    /**
     * @var GroupBy
     */
    protected $groupBy;
    
    /**
     * Set the database connection to use
     * @param string $connection
     */
    public function connection($connection) {
        $this->connection = $connection;
    }
    
    /**
     * Start a collection from a model class
     * @param string $model
     * @return \Virge\ORM\Component\Collection\Collection
     */
    public static function model($model, $alias = ''){
        $collection = new Collection();
        $collection->setModel(new $model);
        
        //set the collection to use the model connection type by default
        $collection->connection($collection->getModel()->_getConnection());
        
        $table = $collection->getModel()->getSqlTable();
        $collection->setTable($table);
        $collection->setAlias($alias ? $alias : $table);
        return $collection;
    }
    
    /**
     * Load a model with it's primary key only
     * @param string $model
     * @param string $primary
     * @return \Virge\ORM\Component\Collection\Collection
     */
    public static function lazy($model, $primary = 'id'){
        $collection = new Collection();
        $collection->setModel(new $model);
        $collection->setLazy(true);
        $collection->setPrimary($primary);
        $collection->setLimit(NULL);
        $table = $collection->getModel()->getSqlTable();
        $collection->setTable($table);
        return $collection;
    }
    
    /**
     * Filter this collection closure
     * @param Closure $closure
     * @return \Virge\ORM\Component\Collection\Collection
     */
    public function filter($closure){
        $this->filterClosures[] = $closure;
        return $this;
    }
    
    /**
     * Get the count of this collection
     * @param int $count
     * @return \Virge\ORM\Component\Collection\Collection
     * @throws InvalidQueryException
     */
    public function count(&$count){
        if($this->getCount()){
            return $this->getCount();
        }
        $table = $this->getTable();
        $this->buildJoins();
        $this->buildWhere();
        
        
        $alias = $this->getAlias();
        
        $query = "SELECT COUNT(*) AS `count` FROM `{$table}` AS `{$alias}`" . $this->query;

        if($this->getDebug()){
            echo $query . PHP_EOL;
        }
        
        $stmt = $this->_prepare($query);

        $stmt->execute();

        if(FALSE !== ($row = $stmt->fetch_assoc())){
            $count = $row['count'];
        }

        $stmt->close();

        return $this;
    }
    
    /**
     * Get the results of this collection
     * @return \Virge\ORM\Component\Collection\Collection
     * @throws \Exception
     */
    public function get($key = null, $defaultValue = null){
        $this->buildJoins();
        $this->buildWhere();
        if($this->order){
            $this->query .= " ORDER BY";
            if(is_array($this->order)){
                $i = 0;
                foreach($this->order as $field => $dir){
                    if($i > 0){
                        $this->query .= ",";
                    }
                    $this->query .= " " . self::escapeField($field). " {$dir}";
                    $i++;
                }
            } else {
                $this->query .= " " . self::escapeField($this->order). " {$this->dir}";
            }
        }
        if($this->getLimit() != NULL){
            $this->query .= " LIMIT {$this->start},{$this->limit}";
        }
        
        $table = $this->getTable();
        
        $sql = "SELECT ";
        
        $modelClass = $this->getModel();
        $model = new $modelClass();
        
        
        if($this->getLazy()){
            $sql .= "`{$this->getPrimary()}`";
        } else {
            $sql .= "{$this->getSelectQuery()}";
        }
        $alias = $this->getAlias();

        $selectFrom = " FROM `{$table}` AS `{$alias}`";
        
        $this->query = $sql . $selectFrom . $this->query;

        if($this->getForUpdate()) {
            $this->query .= " FOR UPDATE";
        }
        
        if($this->getDebug()){
            echo $this->query . PHP_EOL;
        }
        
        //prepared statements
        $stmt = $this->_prepare($this->query);
        
        $this->stmt = $stmt;
        
        $this->stmt->execute();
        
        return $this;
    }
    
    protected function buildWhere()
    {
        if($this->whereBuilt) {
            return;
        }
        foreach($this->filterClosures as $closure) {
            if(empty($this->filters)){
                $this->query .= ' WHERE';
            }
            Filter::$collection = $this;
            $closure($this);
            Filter::reset();
        }
        
        if($this->groupBy)
        {
            $this->query .= ' ' . $this->groupBy->getQuery();
        }
        
        $this->whereBuilt = true;
    }
    
    protected function buildJoins()
    {
        if($this->joinsBuilt) {
            return;
        }
        foreach($this->joins as $join) {
            $this->query .= $join->buildQuery($this->getAlias()) . " ";
        }
        $this->joinsBuilt = true;
    }
    
    /**
     * Fetch a row from our collection
     * @return \Virge\ORM\Component\Collection\className|boolean
     */
    public function fetch(){
        if($this->stmt === null){
            $this->get();
        }
        
        if(!$this->stmt){
            return false;
        }
        
        $row = $this->stmt->fetch_assoc();
        
        if($row){
            $className = $this->model;

            unset($row['_num_rows']);
            $mainModelFields = array();
            
            foreach($row as $key => $value) {
                if(strpos($key, $this->getAlias()) === 0) {
                    $newKey = str_replace($this->getAlias() . '_', '', $key);
                    $mainModelFields[$newKey] = $value;
                }
            }
            
            $model = new $className($mainModelFields);
            $model->_setTracked(true);
            
            $selectJoins = array_filter($this->joins, function($join) {
                return $join->getSelect();
            });
            
            if(empty($selectJoins)) {
                return $model;
            }
            
            return $this->returnDataFromJoins($model, $row, $selectJoins);
        }
        
        $this->stmt->close();
        $this->stmt = null;
        
        return false;
    }
    
    /**
     * Get all of the results back as an array
     * @return type
     */
    public function asArray(){
        $result = array();
        while($model = $this->fetch()){
            $result[] = $model;
        }
        return $result;
    }
    
    /**
     * Map the results through a callback function
     * @param callable $callback
     * @return array
     * @throws InvalidArgumentException
     */
    public function map($callback){
        
        if(!is_callable($callback)){
            throw new \InvalidArgumentException('map requires a valid callable');
        }
        
        return array_map($callback, $this->asArray());
    }
    
    /**
     * Add a parameter to the query
     * @param type $param
     */
    public function addParameter($param)
    {
        $this->parameters[] = $param;

        return $this;
    }

    public function collectionCallback($callback, $additionalParams = [])
    {
        $params = array_merge([$this], $additionalParams);
        call_user_func_array($callback, $params);

        return $this;
    }
    
    public function join($modelClass, $alias, $sourceField, $targetField, $additionalJoinCondition = null)
    {
        $join = new Join($modelClass, $alias, $sourceField, $targetField);
        $join->setAdditionalJoinCondition($additionalJoinCondition);

        $this->joins[] = $join;
        
        return $this;
    }
    
    public function selectJoin($modelClass, $alias, $sourceField, $targetField)
    {
        $this->joins[] = new Join($modelClass, $alias, $sourceField, $targetField, false, true);
        
        return $this;
    }
    
    public function leftJoin($modelClass, $alias, $sourceField, $targetField)
    {
        $this->joins[] = new Join($modelClass, $alias, $sourceField, $targetField, true);
        
        return $this;
    }
    
    public function selectLeftJoin($modelClass, $alias, $sourceField, $targetField)
    {
        $this->joins[] = new Join($modelClass, $alias, $sourceField, $targetField, true, true);
        
        return $this;
    }
    
    public function groupBy($fields)
    {
        $this->groupBy = new GroupBy($fields);
        return $this;
    }
    
    /**
     * Prepare a statement
     * @param string $query
     * @return mixed
     * @throws InvalidQueryException
     */
    protected function _prepare($query) {
        $i = 0;
        $paramValues = [];
        foreach($this->getParameters() as $parameter){
            $field = 'field' . $i;
            $$field = $parameter['value'];
            $paramValues[] = $$field;
            $i++;
        }
        
        $stmt = Database::connection($this->connection)->prepare($query, $paramValues);
        if(!$stmt){
            throw new InvalidQueryException('Failed to prepare statement: ' . $query);
        }
        
        return $stmt;
    }
    
    protected function getSelectQuery() {
        $model = $this->getModel();
        
        $def = $model->_getDef();
        $alias = $this->getAlias();
        $selectFields = array();
        
        foreach($def as $field) {
            $fieldName = $field['field_name'];
            $selectFields[] = "{$alias}.{$fieldName}";
        }
        
        foreach($this->joins as $join) {
            if(!$join->getSelect()) {
                continue;
            }
            $joinModel = $join->getModel();
            $joinDef = $joinModel->_getDef();
            foreach($joinDef as $joinField) {
                $fieldName = $joinField['field_name'];
                $selectFields[] = "{$join->getAlias()}.{$fieldName}";
            }
        }
        
        return implode(', ', array_map(function($field){
            $compoundedName = str_replace(".", "_", $field);
            return Filter::getFieldName($field) . " AS `{$compoundedName}`";
        }, $selectFields));
    }
    
    protected function returnDataFromJoins($mainModel, $row, $joins) 
    {
        $returnModels = array($mainModel);
        foreach($joins as $join) {
            $modelFields = array();

            foreach($row as $key => $value) {
                $keyData = explode('_', $key);
                if($keyData[0] === $join->getAlias()) {
                    unset($keyData[0]);
                    $newKey = implode('_', $keyData);
                    $modelFields[$newKey] = $value;
                }
            }
            $className = $join->getModelClass();
            $returnModels[] = new $className($modelFields);
        }
        return $returnModels;
    }
    
    public static function escapeField($field) {
        return implode('.', array_map(function($part) {
            return "`{$part}`";
        }, explode('.', $field)));
    }
}