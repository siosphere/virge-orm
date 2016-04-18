<?php
namespace Virge\ORM\Component\Collection;

use Virge\ORM\Component\Collection;

/**
 * 
 */
class GroupBy
{
    protected $fields;
    
    public function __construct($fields)
    {
        $this->fields = $fields;
    }
    
    public function getQuery()
    {
        $sql = "GROUP BY "
        .implode(', ', array_map(Collection::class . '::escapeField', $this->fields));
        
        return $sql;
    }
    
}