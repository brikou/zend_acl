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
 * @package    Zend_EventManager
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

namespace Zend\EventManager;

use Zend\Stdlib\CallbackHandler,
    Traversable,
    ArrayObject;

/**
 * Interface for messengers
 *
 * @category   Zend
 * @package    Zend_EventManager
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
interface EventCollection extends SharedEventCollectionAware
{
    /**
     * Trigger an event
     *
     * Should allow handling the following scenarios:
     * - Passing Event object only
     * - Passing event name and Event object only
     * - Passing event name, target, and Event object
     * - Passing event name, target, and array|ArrayAccess of arguments
     *
     * Can emulate triggerUntil() if the last argument provided is a callback.
     * 
     * @param  string $event 
     * @param  object|string $target 
     * @param  array|object $argv 
     * @param  null|callback $callback 
     * @return ResponseCollection
     */
    public function trigger($event, $target = null, $argv = array(), $callback = null);

    /**
     * Trigger an event until the given callback returns a boolean false
     *
     * Should allow handling the following scenarios:
     * - Passing Event object and callback only
     * - Passing event name, Event object, and callback only
     * - Passing event name, target, Event object, and callback
     * - Passing event name, target, array|ArrayAccess of arguments, and callback
     * 
     * @param  string $event 
     * @param  object|string $target 
     * @param  array|object $argv 
     * @param  callback $callback 
     * @return ResponseCollection
     */
    public function triggerUntil($event, $target, $argv = null, $callback = null);

    /**
     * Attach a listener to an event
     * 
     * @param  string $event 
     * @param  callback $callback
     * @param  int $priority Priority at which to register listener
     * @return CallbackHandler
     */
    public function attach($event, $callback = null, $priority = 1);

    /**
     * Detach an event listener
     * 
     * @param  CallbackHandler|ListenerAggregate $listener 
     * @return void
     */
    public function detach($listener);

    /**
     * Get a list of events for which this collection has listeners
     * 
     * @return array
     */
    public function getEvents();

    /**
     * Retrieve a list of listeners registered to a given event
     * 
     * @param  string $event 
     * @return array|object
     */
    public function getListeners($event);

    /**
     * Clear all listeners for a given event
     * 
     * @param  string $event 
     * @return void
     */
    public function clearListeners($event);

    /**
     * Set the event class to utilize
     *
     * @param  string $class
     * @return EventCollection
     */
    public function setEventClass($class);

    /**
     * Get the identifier(s) for this EventManager
     *
     * @return array
     */
    public function getIdentifiers();

    /**
     * Set the identifiers (overrides any currently set identifiers)
     *
     * @param string|int|array|Traversable $identifiers
     * @return EventCollection
     */
    public function setIdentifiers($identifiers);

    /**
     * Add some identifier(s) (appends to any currently set identifiers)
     *
     * @param string|int|array|Traversable $identifiers
     * @return EventCollection
     */
    public function addIdentifiers($identifiers);

    /**
     * Attach a listener aggregate
     *
     * @param  ListenerAggregate $aggregate
     * @param  int $priority If provided, a suggested priority for the aggregate to use
     * @return mixed return value of {@link ListenerAggregate::attach()}
     */
    public function attachAggregate(ListenerAggregate $aggregate, $priority = 1);

    /**
     * Detach a listener aggregate
     *
     * @param  ListenerAggregate $aggregate
     * @return mixed return value of {@link ListenerAggregate::detach()}
     */
    public function detachAggregate(ListenerAggregate $aggregate);

    /**
     * Prepare arguments
     *
     * @param  array $args
     * @return ArrayObject
     */
    public function prepareArgs(array $args);




}
