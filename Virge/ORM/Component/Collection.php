<?php
namespace Virge\ORM\Component;

use Virge\Database;
use Virge\Database\Exception\InvalidQueryException;
use Virge\ORM\Component\Collection\Filter;

/**
 * 
 * @author Michael Kramer
 */
class Collection extends \Virge\Core\Model {
    protected $model = NULL;
    protected $filters = array();
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
    
    /**
     * Start a collection from a model class
     * @param string $model
     * @return \Virge\ORM\Component\Collection\Collection
     */
    public static function model($model){
        $collection = new Collection();
        $collection->setModel(new $model);
        
        $table = $collection->getModel()->getSqlTable();
        $collection->setTable($table);
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
        if(empty($this->filters)){
            $this->query .= ' WHERE';
        }
        Filter::$collection = $this;
        $closure($this);
        Filter::reset();
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
        $query = "SELECT COUNT(*) AS `count` FROM `{$table}`" . $this->query;

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
    public function get(){
        if($this->order){
            $this->query .= " ORDER BY";
            if(is_array($this->order)){
                $i = 0;
                foreach($this->order as $field => $dir){
                    if($i > 0){
                        $this->query .= ",";
                    }
                    $this->query .= " `{$field}` {$dir}";
                    $i++;
                }
            } else {
                $this->query .= " `{$this->order}` {$this->dir}";
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
            $sql .= "*";
        }
        
        $this->query = $sql . " FROM `{$table}`" . $this->query;
        
        if($this->getDebug()){
            echo $this->query . PHP_EOL;
        }
        
        //prepared statements
        $stmt = $this->_prepare($sql);
        
        $this->stmt = $stmt;
        
        $this->stmt->execute();
        
        return $this;
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

            $model = new $className($row);
            
            return $model;
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
    public function addParameter($param){
        $this->parameters[] = $param;
    }
    
    /**
     * Prepare a statement
     * @param string $query
     * @return mixed
     * @throws InvalidQueryException
     */
    protected function _prepare($query) {
        $i = 0;
        foreach($this->getParameters() as $parameter){
            $field = 'field' . $i;
            $$field = $parameter['value'];
            $paramValues[] = $$field;
            $i++;
        }
        
        $stmt = Database::prepare($query, $paramValues);
        if(!$stmt){
            throw new InvalidQueryException('Failed to prepare statement: ' . $query);
        }
        
        return $stmt;
    }
}