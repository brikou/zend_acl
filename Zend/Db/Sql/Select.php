<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Db
 * @subpackage Sql
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

namespace Zend\Db\Sql;

use Zend\Db\Adapter\Adapter,
    Zend\Db\Adapter\Driver\StatementInterface,
    Zend\Db\Adapter\Platform\PlatformInterface,
    Zend\Db\Adapter\Platform\Sql92 as AdapterSql92Platform,
    Zend\Db\Adapter\ParameterContainer;

/**
 * @category   Zend
 * @package    Zend_Db
 * @subpackage Sql
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 *
 * @property Where $where
 */
class Select extends AbstractSql implements SqlInterface, PreparableSqlInterface
{
    /**#@+
     * Constant
     * @const
     */
    const SPECIFICATION_SELECT = 'select';
    const SPECIFICATION_JOIN = 'join';
    const SPECIFICATION_WHERE = 'where';
    const SPECIFICATION_GROUP = 'group';
    const SPECIFICATION_HAVING = 'having';
    const SPECIFICATION_ORDER = 'order';
    const SPECIFICATION_LIMIT = 'limit';
    const SPECIFICATION_OFFSET = 'offset';
    const JOIN_INNER = 'inner';
    const JOIN_OUTER = 'outer';
    const JOIN_LEFT = 'left';
    const JOIN_RIGHT = 'right';
    const SQL_STAR = '*';
    const ORDER_ASCENDING = 'ASC';
    const ORDER_DESENDING = 'DESC';
    /**#@-*/

    /**
     * @var array Specifications
     */
    protected $specifications = array(
        self::SPECIFICATION_SELECT => array(
            'SELECT %1$s FROM %2$s' => array(
                array(1 => '%1$s', 2 => '%1$s AS %2$s', 'combinedby' => ', '),
                null
            )
        ),
        self::SPECIFICATION_JOIN   => array(
            '%1$s' => array(
                array(3 => '%1$s JOIN %2$s ON %3$s', 'combinedby' => ' ')
            )
        ),
        self::SPECIFICATION_WHERE  => 'WHERE %1$s',
        self::SPECIFICATION_GROUP  => array(
            'GROUP BY %1$s' => array(
                array(1 => '%1$s', 'combinedby' => ', ')
            )
        ),
        self::SPECIFICATION_HAVING => 'HAVING %1$s',
        self::SPECIFICATION_ORDER  => array(
            'ORDER BY %1$s' => array(
                array(2 => '%1$s %2$s', 'combinedby' => ', ')
            )
        ),
        self::SPECIFICATION_LIMIT  => 'LIMIT %1$s',
        self::SPECIFICATION_OFFSET => 'OFFSET %1$s'
    );

    /**
     * @var bool
     */
    protected $tableReadOnly = false;

    /**
     * @var bool
     */
    protected $prefixColumnsWithTable = true;

    /**
     * @var string|TableIdentifier
     */
    protected $table = null;

    /**
     * @var array
     */
    protected $columns = array(self::SQL_STAR);

    /**
     * @var array
     */
    protected $joins = array();

    /**
     * @var Where
     */
    protected $where = null;

    /**
     * @var null|string
     */
    protected $order = array();

    /**
     * @var null|array
     */
    protected $group = null;

    /**
     * @var null|string|array
     */
    protected $having = null;

    /**
     * @var int|null
     */
    protected $limit = null;

    /**
     * @var int|null
     */
    protected $offset = null;


    /**
     * Constructor
     * 
     * @param  null|string $table 
     * @param  null|string $schema
     * @return void
     */
    public function __construct($table = null)
    {
        if ($table) {
            $this->from($table);
            $this->tableReadOnly = true;
        }

        $this->where = new Where;
        $this->having = new Having;
    }

    /**
     * Create from clause
     * 
     * @param  string|TableIdentifier $table
     * @param  null|string $schema
     * @return Select
     */
    public function from($table)
    {
        if ($this->tableReadOnly) {
            throw new \InvalidArgumentException('Since this object was created with a table and/or schema in the constructor, it is read only.');
        }

        if (!is_string($table) && !$table instanceof TableIdentifier) {
            throw new Exception\InvalidArgumentException('$table must be a string or an instance of TableIdentifier');
        }

        $this->table = $table;
        return $this;
    }

    /**
     * Specify columns from which to select
     *
     * Possible valid states:
     *
     *   array(*)
     *
     *   array(value, ...)
     *     value can be strings or Expression objects
     *
     *   array(string => value, ...)
     *     key string will be use as alias,
     *     value can be string or Expression objects
     *
     * @param  array $columns
     * @return Select
     */
    public function columns(array $columns, $prefixColumnsWithTable = true)
    {
        $this->columns = $columns;
        $this->prefixColumnsWithTable = (bool) $prefixColumnsWithTable;
        return $this;
    }

    /**
     * Create join clause
     * 
     * @param  string $name 
     * @param  string $on 
     * @param  string|array $columns 
     * @param  string $type one of the JOIN_* constants
     * @return Select
     */
    public function join($name, $on, $columns = self::SQL_STAR, $type = self::JOIN_INNER)
    {
        if (!is_array($columns)) {
            $columns = array($columns);
        }
        $this->joins[] = array(
            'name'    => $name,
            'on'      => $on,
            'columns' => $columns,
            'type'    => $type
        );
        return $this;
    }

    /**
     * Create where clause
     * 
     * @param  Where|\Closure|string|array $predicate 
     * @param  string $combination One of the OP_* constants from Predicate\PredicateSet
     * @return Select
     */
    public function where($predicate, $combination = Predicate\PredicateSet::OP_AND)
    {
        if ($predicate instanceof Where) {
            $this->where = $predicate;
        } elseif ($predicate instanceof \Closure) {
            $predicate($this->where);
        } else {
            if (is_string($predicate)) {
                $predicate = new Predicate\Expression($predicate);
                $this->where->addPredicate($predicate, $combination);
            } elseif (is_array($predicate)) {
                foreach ($predicate as $pkey => $pvalue) {
                    if (is_string($pkey) && strpos($pkey, '?') !== false) {
                        $predicate = new Predicate\Expression($pkey, $pvalue);
                    } elseif (is_string($pkey)) {
                        $predicate = new Predicate\Operator($pkey, Predicate\Operator::OP_EQ, $pvalue);
                    } else {
                        $predicate = new Predicate\Expression($pvalue);
                    }
                    $this->where->addPredicate($predicate, $combination);
                }
            }
        }
        return $this;
    }

    public function group($group)
    {
        if (is_array($group)) {
            foreach ($group as $o) {
                $this->group[] = $o;
            }
        } else {
            $this->group[] = $group;
        }
        return $this;
    }

    /**
     * Create where clause
     *
     * @param  Where|\Closure|string|array $predicate
     * @param  string $combination One of the OP_* constants from Predicate\PredicateSet
     * @return Select
     */
    public function having($predicate, $combination = Predicate\PredicateSet::OP_AND)
    {
        if ($predicate instanceof Having) {
            $this->having = $predicate;
        } elseif ($predicate instanceof \Closure) {
            $predicate($this->having);
        } else {
            if (is_string($predicate)) {
                $predicate = new Predicate\Expression($predicate);
                $this->having->addPredicate($predicate, $combination);
            } elseif (is_array($predicate)) {
                foreach ($predicate as $pkey => $pvalue) {
                    if (is_string($pkey) && strpos($pkey, '?') !== false) {
                        $predicate = new Predicate\Expression($pkey, $pvalue);
                    } elseif (is_string($pkey)) {
                        $predicate = new Predicate\Operator($pkey, Predicate\Operator::OP_EQ, $pvalue);
                    } else {
                        $predicate = new Predicate\Expression($pvalue);
                    }
                    $this->having->addPredicate($predicate, $combination);
                }
            }
        }
        return $this;
    }

    /**
     * @param string|array $order
     * @return Select
     */
    public function order($order)
    {
        if (is_string($order)) {
            if (strpos($order, ',') !== false) {
                $order = preg_split('#,\s+#', $order);
            } else {
                $order = (array) $order;
            }
        }
        foreach ($order as $k => $v) {
            if (is_string($k)) {
                $this->order[$k] = $v;
            } else {
                $this->order[] = $v;
            }
        }
        return $this;
    }

    public function limit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    public function setSpecification($index, $specification)
    {
        if (!method_exists($this, 'process' . $index)) {
            throw new Exception\InvalidArgumentException('Not a valid specification name.');
        }
        $this->specifications[$index] = $specification;
        return $this;
    }

    public function getRawState($key = null)
    {
        $rawState = array(
            'columns' => $this->columns,
            'table' => $this->table,
            'joins' => $this->joins,
            'where' => $this->where,
            'order' => $this->order,
            'limit' => $this->limit,
            'offset' => $this->offset
        );
        return (isset($key) && array_key_exists($key, $rawState)) ? $rawState[$key] : $rawState;
    }

    /**
     * Prepare statement
     *
     * @param \Zend\Db\Adapter\Adapter $adapter
     * @param \Zend\Db\Adapter\Driver\StatementInterface $statement
     * @return void
     */
    public function prepareStatement(Adapter $adapter, StatementInterface $statement)
    {
        // ensure statement has a ParameterContainer
        $parameterContainer = $statement->getParameterContainer();
        if (!$parameterContainer instanceof ParameterContainer) {
            $parameterContainer = new ParameterContainer();
            $statement->setParameterContainer($parameterContainer);
        }

        $sqls = array();
        $parameters = array();
        $platform = $adapter->getPlatform();

        foreach ($this->specifications as $name => $specification) {
             $parameters[$name] = $this->{'process' . $name}($platform, $adapter, $parameterContainer, $sqls, $parameters);
             if ($specification && is_array($parameters[$name])) {
                 $sqls[$name] = $this->createSqlFromSpecificationAndParameters($specification, $parameters[$name]);
             }
        }

        $sql = implode(' ', $sqls);
        
        $statement->setSql($sql);
        return;
    }


    /**
     * Get SQL string for statement
     *
     * @param  null|PlatformInterface $adapterPlatform If null, defaults to Sql92
     * @return string
     */
    public function getSqlString(PlatformInterface $adapterPlatform = null)
    {
        // get platform, or create default
        $adapterPlatform = ($adapterPlatform) ?: new AdapterSql92Platform;

        $sqls = array();
        $parameters = array();

        foreach ($this->specifications as $name => $specification) {
             $parameters[$name] = $this->{'process' . $name}($adapterPlatform, null, null, $sqls, $parameters);
             if ($specification && is_array($parameters[$name])) {
                 $sqls[$name] = $this->createSqlFromSpecificationAndParameters($specification, $parameters[$name]);
             }
        }

        $sql = implode(' ', $sqls);
        return $sql;
    }



    protected function processSelect(PlatformInterface $platform, Adapter $adapter = null, ParameterContainer $parameterContainer = null)
    {
        $expr = 1;
        
        if (!$this->table) {
            return null;
        }
        // create quoted table name to use in columns processing
        if ($this->table instanceof TableIdentifier) {
            list($table, $schema) = $this->table->getTableAndSchema();
            $table = $platform->quoteIdentifier($table);
            if ($schema) {
                $table = $platform->quoteIdentifier($schema) . $platform->getIdentifierSeparator() . $table;
            }
        } else {
            $table = $platform->quoteIdentifier($this->table);
        }
        $quotedTable = ($this->prefixColumnsWithTable)
            ? $table . $platform->getIdentifierSeparator()
            : '';

        // process table columns
        $columns = array();
        foreach ($this->columns as $columnIndexOrAs => $column) {

            $columnName = '';
            if ($column === self::SQL_STAR) {
                $columns[] = array($quotedTable . self::SQL_STAR);
                continue;
            } 
            
            if ($column instanceof Expression) {
                $columnParts = $this->processExpression(
                    $column,
                    $platform,
                    ($adapter) ? $adapter->getDriver() : null,
                    (is_string($columnIndexOrAs)) ? $columnIndexOrAs : 'column'
                );
                if (count($columnParts['parameters']) > 0) {
                    $parameterContainer->merge($columnParts['parameters']);
                }
                $columnName .= $columnParts['sql'];
            } else {
                $columnName .= $quotedTable . $platform->quoteIdentifier($column);
            }
            
            // process As portion
            if (is_string($columnIndexOrAs)) {
                $columnAs = $platform->quoteIdentifier($columnIndexOrAs);
            } elseif (stripos($columnName, ' as ') === false) {
                $columnAs = (is_string($column)) ? $platform->quoteIdentifier($column) : 'Expression' . $expr++;
            }
            $columns[] = (isset($columnAs)) ? array($columnName, $columnAs) : array($columnName);
        }

        $separator = $platform->getIdentifierSeparator();

        // process join columns
        foreach ($this->joins as $j => $join) {
            foreach ($join['columns'] as $jKey => $jColumn) {
                $jColumns = array();
                $jColumns[] = $platform->quoteIdentifier($join['name']) . $separator . $platform->quoteIdentifierInFragment($jColumn);
                if (is_string($jKey)) {
                    $jColumns[] = $platform->quoteIdentifier($jKey);
                } elseif ($jColumn !== self::SQL_STAR) {
                    $jColumns[] = $platform->quoteIdentifier($jColumn);
                }
                $columns[] = $jColumns;
            }
        }

        return array($columns, $table);
    }
    
    protected function processJoin(PlatformInterface $platform, Adapter $adapter = null, ParameterContainer $parameterContainer = null)
    {
        if (!$this->joins) {
            return null;
        }
        
        // process joins
        $joinSpecArgArray = array();
        foreach ($this->joins as $j => $join) {
            $joinSpecArgArray[$j] = array();
            $joinSpecArgArray[$j][] = strtoupper($join['type']); // type
            $joinSpecArgArray[$j][] = $platform->quoteIdentifier($join['name']); // table
            $joinSpecArgArray[$j][] = $platform->quoteIdentifierInFragment($join['on'], array('=', 'AND', 'OR', '(', ')')); // on
        }

        return array($joinSpecArgArray);
    }

    protected function processWhere(PlatformInterface $platform, Adapter $adapter = null, ParameterContainer $parameterContainer = null)
    {
        if ($this->where->count() == 0) {
            return null;
        }
        $whereParts = $this->processExpression($this->where, $platform, ($adapter) ? $adapter->getDriver() : null, 'where');
        if (count($whereParts['parameters']) > 0) {
            $parameterContainer->merge($whereParts['parameters']);
        }
        return array($whereParts['sql']);
    }

    protected function processGroup(PlatformInterface $platform, Adapter $adapter = null, ParameterContainer $parameterContainer = null)
    {
        if ($this->group === null) {
            return null;
        }
        // process table columns
        $groups = array();
        foreach ($this->group as $k => $column) {
            $columnSql = '';
            if ($column instanceof Expression) {
                $columnParts = $this->processExpression($column, $platform, ($adapter) ? $adapter->getDriver() : null, 'group');
                if (count($columnParts['parameters']) > 0) {
                    $parameterContainer->merge($columnParts['parameters']);
                }
                $columnSql .= $columnParts['sql'];
            } else {
                $columnSql .= $platform->quoteIdentifier($column);
            }
            $groups[] = $columnSql;
        }
        return array($groups);
    }

    protected function processHaving(PlatformInterface $platform, Adapter $adapter = null, ParameterContainer $parameterContainer = null)
    {
        if ($this->having->count() == 0) {
            return null;
        }
        $whereParts = $this->processExpression($this->having, $platform, ($adapter) ? $adapter->getDriver() : null, 'having');
        if (count($whereParts['parameters']) > 0) {
            $parameterContainer->merge($whereParts['parameters']);
        }
        return array($whereParts['sql']);
    }

    protected function processOrder(PlatformInterface $platform, Adapter $adapter = null, ParameterContainer $parameterContainer = null)
    {
        if (empty($this->order)) {
            return null;
        }
        $orders = array();
        foreach ($this->order as $k => $v) {
            if (is_int($k)) {
                if (strpos($v, ' ') !== false) {
                    list($k, $v) = preg_split('# #', $v, 2);
                } else {
                    $k = $v;
                    $v = self::ORDER_ASCENDING;
                }
            }
            if (strtoupper($v) == self::ORDER_DESENDING) {
                $orders[] = array($platform->quoteIdentifier($k), self::ORDER_DESENDING);
            } else {
                $orders[] = array($platform->quoteIdentifier($k), self::ORDER_ASCENDING);
            }
        }
        return array($orders);
    }

    protected function processLimit(PlatformInterface $platform, Adapter $adapter = null, ParameterContainer $parameterContainer = null)
    {
        if ($this->limit === null) {
            return null;
        }
        if ($adapter) {
            $driver = $adapter->getDriver();
            $sql = $driver->formatParameterName('limit');
            $parameterContainer->offsetSet('limit', $this->limit);
        } else {
            $sql = $platform->quoteValue($this->limit);
        }

        return array($sql);
    }

    protected function processOffset(PlatformInterface $platform, Adapter $adapter = null, ParameterContainer $parameterContainer = null)
    {
        if ($this->offset === null) {
            return null;
        }
        if ($adapter) {
            $parameterContainer->offsetSet('offset', $this->offset);
            return array($adapter->getDriver()->formatParameterName('offset'));
        } else {
            return array($platform->quoteValue($this->offset));
        }
    }

    /**
     * Variable overloading
     *
     * Proxies to "where" only
     * 
     * @param  string $name 
     * @return mixed
     */
    public function __get($name)
    {
        switch (strtolower($name)) {
            case 'where':
                return $this->where;
            default:
                throw new Exception\InvalidArgumentException('Not a valid magic property for this object');
        }
    }

    /**
     * __clone 
     *
     * Resets the where object each time the Select is cloned.
     * 
     * @return void
     */
    public function __clone() {
        $this->where = clone $this->where;
    }
}
