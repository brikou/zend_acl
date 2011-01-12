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
 * @package    Zend_SignalSlot
 * @copyright  Copyright (c) 2010-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

/**
 * @namespace
 */
namespace Zend\SignalSlot;

use Zend\Stdlib\CallbackHandler,
    Zend\Stdlib\Exception\InvalidCallbackException;

/**
 * FilterChain: intercepting filter manager
 *
 * @category   Zend
 * @package    Zend_SignalSlot
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class FilterChain implements Filter
{
    /**
     * @var Filter\FilterIterator All filters
     */
    protected $filters;

    /**
     * Constructor
     *
     * Initializes Filter\FilterIterator in which filters will be aggregated
     * 
     * @return void
     */
    public function __construct()
    {
        $this->filters = new Filter\FilterIterator();
    }

    /**
     * Apply the filters
     *
     * Begins iteration of the filters.
     * 
     * @param  mixed $context Object under observation
     * @param  mixed $argv Associative array of arguments
     * @return mixed
     */
    public function run($context, array $argv = array())
    {
        $chain = clone $this->getFilters();

        if ($chain->isEmpty()) {
            return;
        }

        $next = $chain->extract();
        if (!$next instanceof CallbackHandler) {
            return;
        }

        return call_user_func($next->getCallback(), $context, $argv, $chain);
    }

    /**
     * Connect a filter to the chain
     * 
     * @param  callback $callback PHP Callback
     * @param  int $priority Priority in the queue at which to execute; defaults to 1000 (higher numbers == higher priority)
     * @return CallbackHandler (to allow later unsubscribe)
     */
    public function connect($callback, $priority = 1000)
    {
        if (empty($callback)) {
            throw new InvalidCallbackException('No callback provided');
        }
        $filter = new CallbackHandler(null, $callback, array('priority' => $priority));
        $this->filters->insert($filter, $priority);
        return $filter;
    }

    /**
     * Unsubscribe a filter
     * 
     * @param  CallbackHandler $filter 
     * @return bool Returns true if filter found and unsubscribed; returns false otherwise
     */
    public function detach(CallbackHandler $filter)
    {
        return $this->filters->remove($filter);
    }

    /**
     * Retrieve all filters
     * 
     * @return FilterIterator
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * Clear all filters
     * 
     * @return void
     */
    public function clearFilters()
    {
        $this->filters = new Filter\FilterIterator();
    }

    /**
     * Return current responses
     *
     * Only available while the chain is still being iterated. Returns the 
     * current ResponseCollection.
     * 
     * @return null|ResponseCollection
     */
    public function getResponses()
    {
        return $this->responses;
    }
}
