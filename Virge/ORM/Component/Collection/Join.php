<?php
namespace Virge\ORM\Component\Collection;

use Virge\Core\Model;

/**
 * Join a model onto another when grabbing a collection
 * @author Michael Kramer
 */
class Join extends Model{
    
    /**
     * @var string
     */
    protected $modelClass;
    
    /**
     * @var string
     */
    protected $alias;
    
    /**
     * @var string
     */
    protected $sourceField;
    
    /**
     * @var string
     */
    protected $targetField;
    
    /**
     * @var bool
     */
    protected $leftJoin;
    
    /**
     * @var mixed
     */
    protected $model;
    
    /**
     * @var bool 
     */
    protected $select;
    
    /**
     * @param string $modelClass
     * @param string $alias
     * @param string $sourceField
     * @param string $targetField
     */
    public function __construct($modelClass, $alias, $sourceField, $targetField, $leftJoin = false, $select = false)
    {
        $this->modelClass = $modelClass;
        $this->alias = $alias;
        $this->sourceField = $sourceField;
        $this->targetField = $targetField;
        $this->leftJoin = $leftJoin;
        $this->model = new $modelClass;
        $this->select = $select;
    }
    
    /**
     * @return string
     */
    public function getModelClass() {
        return $this->modelClass;
    }

    /**
     * @return string
     */
    public function getAlias() {
        return $this->alias;
    }

    /**
     * @return string
     */
    public function getSourceField() {
        return $this->sourceField;
    }

    /**
     * @return string
     */
    public function getTargetField() {
        return $this->targetField;
    }

    /**
     * @param string $modelClass
     * @return \Virge\ORM\Component\Collection\Join
     */
    public function setModelClass($modelClass) {
        $this->modelClass = $modelClass;
        return $this;
    }

    /**
     * @param string $alias
     * @return \Virge\ORM\Component\Collection\Join
     */
    public function setAlias($alias) {
        $this->alias = $alias;
        return $this;
    }

    /**
     * @param string $sourceField
     * @return \Virge\ORM\Component\Collection\Join
     */
    public function setSourceField($sourceField) {
        $this->sourceField = $sourceField;
        return $this;
    }

    /**
     * @param string $targetField
     * @return \Virge\ORM\Component\Collection\Join
     */
    public function setTargetField($targetField) {
        $this->targetField = $targetField;
        return $this;
    }

    /**
     * @return bool
     */
    public function getLeftJoin() {
        return $this->leftJoin;
    }

    /**
     * @param bool $leftJoin
     * @return \Virge\ORM\Component\Collection\Join
     */
    public function setLeftJoin($leftJoin) {
        $this->leftJoin = $leftJoin;
        return $this;
    }
    
    public function buildQuery($mainTable) {
        $sql = " ";
        
        if($this->leftJoin) {
            $sql .= "LEFT ";
        }
        
        $model = new $this->modelClass;
        $table = $model->getSqlTable();
        
        $sql .= "JOIN `{$table}` AS `{$this->alias}` ON `{$mainTable}`.`{$this->sourceField}` =`{$this->alias}`.`{$this->targetField}` ";
        
        return $sql;
    }
    
    /**
     * @return mixed
     */
    public function getModel() {
        return $this->model;
    }

    /**
     * @param mixed $model
     * @return \Virge\ORM\Component\Collection\Join
     */
    public function setModel($model) {
        $this->model = $model;
        return $this;
    }

    /**
     * @return bool
     */
    public function getSelect() {
        return $this->select;
    }

    /**
     * @param bool $select
     * @return \Virge\ORM\Component\Collection\Join
     */
    public function setSelect($select) {
        $this->select = $select;
        return $this;
    }



}