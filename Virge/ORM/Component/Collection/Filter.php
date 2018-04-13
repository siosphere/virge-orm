<?php
namespace Virge\ORM\Component\Collection;

use Virge\Core\Model;

/**
 * 
 * @author Michael Kramer
 */
class Filter extends Model{
    protected static $filter_group = NULL;
    protected static $filters = array();
    protected static $filter_groups = 1;
    public static $collection = NULL;
    protected static $operator = 'AND';
    
    /**
     * Our current operator
     * @param string $operator
     */
    public static function operator($operator){
        self::$operator = $operator;
    }
    
    /**
     * Start a group of filters
     * @param string $operator
     * @param string $group_operator
     */
    public static function start($operator = 'AND', $group_operator = 'AND'){
        if(self::$filter_groups > 0){
            self::$collection->query .= ' ' . $group_operator;
        }
        self::$operator = $operator;
        self::$collection->query .= ' (';
        self::$filter_groups++;
        self::$filter_group = self::$filter_groups;
        self::$filters[self::$filter_group] = 0;
    }
    
    /**
     * 
     */
    protected static function before(){
        if(isset(self::$filters[self::$filter_group]) && self::$filters[self::$filter_group] > 0){
            self::$collection->query .= ' '.self::$operator;
        }
    }
    
    /**
     * 
     */
    protected static function after(){
        if(!isset(self::$filters[self::$filter_group])) {
            self::$filters[self::$filter_group] = 0;
        }
        self::$filters[self::$filter_group]++;
    }
    
    /**
     * @param string $field_name
     */
    public static function isNull($field_name){
        self::before();
        $safeField = self::getFieldName($field_name);
        self::$collection->query .= " {$safeField} IS NULL";
        self::after();
    }
    
    /**
     * @param string $field_name
     */
    public static function notNull($field_name){
        self::before();
        $safeField = self::getFieldName($field_name);
        self::$collection->query .= " {$safeField} IS NOT NULL";
        self::after();
    }
    
    /**
     * @param string $field_name
     * @param mixed $value
     */
    public static function like($field_name, $value, $raw = false){
        self::before();
        $safeField = self::getFieldName($field_name);
        if($raw) {
            $safeValue = self::getFieldName($value);
            self::$collection->query .= " {$safeField} LIKE {$safeValue}";
            self::after();
            return;
        }
        self::$collection->query .= " {$safeField} LIKE ?";
        self::param('s', $value);
        self::after();
    }
    
    /**
     * @param string $field_name
     * @param mixed $value
     */
    public static function notLike($field_name, $value, $raw = false){
        self::before();
        $safeField = self::getFieldName($field_name);
        if($raw) {
            $safeValue = self::getFieldName($value);
            self::$collection->query .= " {$safeField} NOT LIKE {$safeValue}";
            self::after();
            return;
        }
        self::$collection->query .= " {$safeField} NOT LIKE ?";
        self::param('s', $value);
        self::after();
    }
    
    /**
     * @param string $field_name
     * @param mixed $value
     */
    public static function eq($field_name, $value, $raw = false){
        self::before();
        $safeField = self::getFieldName($field_name);
        if($raw) {
            $safeValue = self::getFieldName($value);
            self::$collection->query .= " {$safeField} = {$safeValue}";
            self::after();
            return;
        }

        self::$collection->query .= " {$safeField} = ?";
        if(is_numeric($value)){
            self::param('i', $value);
        } else {
            self::param('s', $value);
        }
        self::after();
    }
    
    /**
     * @param string $field_name
     * @param mixed $value
     */
    public static function neq($field_name, $value, $raw = false){
        self::before();
        $safeField = self::getFieldName($field_name);
        if($raw) {
            $safeValue = self::getFieldName($value);
            self::$collection->query .= " {$safeField} != {$safeValue}";
            self::after();
            return;
        }
        self::$collection->query .= " {$safeField} != ?";
        if(is_numeric($value)){
            self::param('i', $value);
        } else {
            self::param('s', $value);
        }
        self::after();
    }
    
    /**
     * @param string $field_name
     * @param mixed $value
     */
    public static function gte($field_name, $value, $raw = false){
        self::before();
        $safeField = self::getFieldName($field_name);
        if($raw) {
            $safeValue = self::getFieldName($value);
            self::$collection->query .= " {$safeField} >= {$safeValue}";
            self::after();
            return;
        }
        self::$collection->query .= " {$safeField} >= ?";
        if(is_numeric($value)){
            self::param('i', $value);
        } else {
            self::param('s', $value);
        }
        self::after();
    }
    
    /**
     * @param string $field_name
     * @param mixed $value
     */
    public static function gt($field_name, $value, $raw = false){
        self::before();
        $safeField = self::getFieldName($field_name);
        if($raw) {
            $safeValue = self::getFieldName($value);
            self::$collection->query .= " {$safeField} > {$safeValue}";
            self::after();
            return;
        }
        self::$collection->query .= " {$safeField} > ?";
        if(is_numeric($value)){
            self::param('i', $value);
        } else {
            self::param('s', $value);
        }
        self::after();
    }
    
    /**
     * @param string $field_name
     * @param mixed $value
     */
    public static function lte($field_name, $value, $raw = false){
        self::before();
        $safeField = self::getFieldName($field_name);
        if($raw) {
            $safeValue = self::getFieldName($value);
            self::$collection->query .= " {$safeField} <= {$safeValue}";
            self::after();
            return;
        }
        self::$collection->query .= " {$safeField} <= ?";
        if(is_numeric($value)){
            self::param('i', $value);
        } else {
            self::param('s', $value);
        }
        self::after();
    }
    
    /**
     * @param string $field_name
     * @param mixed $value
     */
    public static function lt($field_name, $value, $raw = false){
        self::before();
        $safeField = self::getFieldName($field_name);
        if($raw) {
            $safeValue = self::getFieldName($value);
            self::$collection->query .= " {$safeField} < {$safeValue}";
            self::after();
            return;
        }
        self::$collection->query .= " {$safeField} < ?";
        if(is_numeric($value)){
            self::param('i', $value);
        } else {
            self::param('s', $value);
        }
        self::after();
    }
    
    /**
     * @param string $field_name
     * @param mixed $value
     */
    public static function in($field_name, $value){
        self::before();
        if(is_array($value)){

            foreach($value as $key => $paramValue) {
                self::param(is_numeric($paramValue) ? 'i' : 's', $paramValue);
            }
            
            $value = implode(',', array_fill(0, count($value), '?'));
        }
        $safeField = self::getFieldName($field_name);
        self::$collection->query .= " {$safeField} IN($value)";
        self::after();
    }
    
    /**
     * @param string $field_name
     * @param mixed $value
     */
    public static function notIn($field_name, $value){
        self::before();
        if(is_array($value)){
            $paramValues = implode(',', $value);
            self::param('s', $paramValues);
            $value = '?';
        }
        
        $safeField = self::getFieldName($field_name);
        
        self::$collection->query .= " {$safeField} NOT IN($value)";
        self::after();
    }
    
    /**
     * @param string $type
     * @param mixed $value
     */
    public static function param($type, $value){
        self::$collection->addParameter(array(
            'type'      =>      $type,
            'value'     =>      $value
        ));
    }
    
    /**
     * 
     */
    public static function end(){
        self::$collection->query .= ' ) ';
    }
    
    /**
     * Reset the filter object for the next round of filters
     */
    public static function reset(){
        self::$filter_group = NULL;
        self::$filters = array();
        self::$filter_groups = 1;
        self::$collection = NULL;
        self::$operator = 'AND';
    }
    
    public static function getFieldName($field_name) {
        $parts = explode('.', $field_name);
        
        return implode('.', array_map(function($part){
            return "`{$part}`";
        }, $parts));
    }
}