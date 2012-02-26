<?php

namespace Zend\Db\Adapter\Driver\Mysqli;

use Zend\Db\Adapter\Driver\ResultInterface;

class Result implements \Iterator, ResultInterface
{
    const MODE_STATEMENT = 'statement';
    const MODE_RESULT = 'result';
    
    protected $mode = null;

    protected $isQueryResult = true;

    /**
     * @var \mysqli_result|\mysqli_stmt
     */
    protected $resource = null;

    /**
     * Cursor position
     * @var int
     */
    protected $position = 0;

    /**
     * Number of known rows
     * @var int
     */
    protected $numberOfRows = -1;

    /**
     * Is the current() operation already complete for this pointer position?
     * @var bool
     */
    protected $currentComplete = false;

    /**
     *
     * @var bool
     */
    protected $nextComplete = false;
    
    protected $currentData = false;
    
    protected $statementBindValues = array('keys' => null, 'values' => array());

    public function initialize($resource)
    {
        if (!$resource instanceof \mysqli && !$resource instanceof \mysqli_result && !$resource instanceof \mysqli_stmt) {
            throw new \InvalidArgumentException('Invalid resource provided.');
        }

        $this->isQueryResult = (!$resource instanceof \mysqli);

        $this->resource = $resource;
        $this->mode = ($this->resource instanceof \mysqli_stmt) ? self::MODE_STATEMENT : self::MODE_RESULT;
        return $this;
    }
    
    public function getResource()
    {
        return $this->resource;
    }

    public function setIsQueryResult($isQueryResult)
    {
        $this->isQueryResult = $isQueryResult;
    }

    public function isQueryResult()
    {
        return $this->isQueryResult;
    }

    public function getAffectedRows()
    {
        if ($this->resource instanceof \mysqli || $this->resource instanceof \mysqli_stmt) {
            return $this->resource->affected_rows;
        } else {
            return $this->resource->num_rows;
        }
    }

    public function current()
    {
        if ($this->currentComplete) {
            return $this->currentData;
        }
        
        if ($this->mode == self::MODE_STATEMENT) {
            $this->loadDataFromMysqliStatement();
            return $this->currentData;
        } else {
            $this->loadFromMysqliResult();
            return $this->currentData;
        }
    }
    
    /**
     * Mysqli's binding and returning of statement values
     * 
     * Mysqli requires you to bind variables to the extension in order to 
     * get data out.  These values have to be references:
     * @see http://php.net/manual/en/mysqli-stmt.bind-result.php
     * 
     * @throws \RuntimeException
     * @return bool
     */
    protected function loadDataFromMysqliStatement()
    {
        $data = null;
        // build the default reference based bind strutcure, if it does not already exist
        if ($this->statementBindValues['keys'] === null) {
            $this->statementBindValues['keys'] = array();
            $resultResource = $this->resource->result_metadata();
            foreach ($resultResource->fetch_fields() as $col) {
                $this->statementBindValues['keys'][] = $col->name;
            }
            $this->statementBindValues['values'] = array_fill(0, count($this->statementBindValues['keys']), null);
            $refs = array();
            foreach ($this->statementBindValues['values'] as $i => &$f) {
                $refs[$i] = &$f;
            }
            call_user_func_array(array($this->resource, 'bind_result'), $this->statementBindValues['values']);
        }
        
        if (($r = $this->resource->fetch()) === null) {
            return false;
        } elseif ($r === false) {
            throw new \RuntimeException($this->resource->error);
        }

        // derefernece
        for ($i = 0; $i < count($this->statementBindValues['keys']); $i++) {
            $this->currentData[$this->statementBindValues['keys'][$i]] = $this->statementBindValues['values'][$i];
        }
        $this->currentComplete = true;
        $this->nextComplete = true;
        $this->position++;
        return true;
    }
    
    protected function loadFromMysqliResult()
    {
        $this->currentData = null;
        
        if (($data = $this->resource->fetch_assoc()) === null) {
            return false;
        }
        
        $this->position++;
        $this->currentData = $data;
        $this->currentComplete = true;
        $this->nextComplete = true;
        $this->position++;
        return true;
    }
    
    public function next()
    {
        $this->currentComplete = false;
        
        if ($this->nextComplete == false) {
            $this->position++;
        }
        
        $this->nextComplete = false;
    }
    
    public function key()
    {
        return $this->position;
    }
    
    public function rewind()
    {
        $this->currentComplete = false;
        $this->position = 0;
        if ($this->resource instanceof \mysqli_stmt) {
            //$this->resource->reset();
        } else {
            $this->resource->data_seek(0); // works for both mysqli_result & mysqli_stmt
        }
    }
    
    public function valid()
    {
        if ($this->currentComplete) {
            return true;
        }
        
        if ($this->mode == self::MODE_STATEMENT) {
            return $this->loadDataFromMysqliStatement();
        } else {
            return $this->loadFromMysqliResult();
        }
    }
    
    public function count()
    {
        return $this->resource->num_rows;
    }
    
}
