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
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

/**
 * @namespace
 */
namespace Zend\EventManager;

use Zend\Stdlib\CallbackHandler,
    Zend\Stdlib\Exception\InvalidCallbackException,
    Zend\Stdlib\PriorityQueue,
    ArrayObject;

/**
 * Event manager: notification system
 *
 * Use the EventManager when you want to create a per-instance notification
 * system for your objects.
 *
 * @category   Zend
 * @package    Zend_EventManager
 * @copyright  Copyright (c) 2005-2011 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class EventManager implements EventCollection
{
    /**
     * Subscribed events and their listeners
     * @var array Array of PriorityQueue objects
     */
    protected $events = array();

    /**
     * @var string Class representing the event being emitted
     */
    protected $eventClass = 'Zend\EventManager\Event';

    /**
     * Identifier, used to pull static signals from StaticEventManager
     * @var null|string
     */
    protected $identifier;

    /**
     * Static connections
     * @var false|null|StaticEventCollection
     */
    protected $staticConnections = null;

    /**
     * Constructor
     *
     * Allows optionally specifying an identifier to use to pull signals from a
     * StaticEventManager.
     *
     * @param  null|string|int $identifier
     * @return void
     */
    public function __construct($identifier = null)
    {
        $this->identifier = $identifier;
    }

    /**
     * Set the event class to utilize
     *
     * @param  string $class
     * @return EventManager
     */
    public function setEventClass($class)
    {
        $this->eventClass = $class;
        return $this;
    }

    /**
     * Set static connections container
     *
     * @param  null|StaticEventCollection $connections
     * @return void
     */
    public function setStaticConnections(StaticEventCollection $connections = null)
    {
        if (null === $connections) {
            $this->staticConnections = false;
        } else {
            $this->staticConnections = $connections;
        }
        return $this;
    }

    /**
     * Get static connections container
     *
     * @return false|StaticEventCollection
     */
    public function getStaticConnections()
    {
        if (null === $this->staticConnections) {
            $this->setStaticConnections(StaticEventManager::getInstance());
        }
        return $this->staticConnections;
    }

    /**
     * Trigger all listeners for a given event
     *
     * Can emulate triggerUntil() if the last argument provided is a callback.
     *
     * @param  string $event
     * @param  string|object $target Object calling emit, or symbol describing target (such as static method name)
     * @param  array|ArrayAccess $argv Array of arguments; typically, should be associative
     * @param  null|callback $callback 
     * @return ResponseCollection All listener return values
     */
    public function trigger($event, $target = null, $argv = array(), $callback = null)
    {
        if ($event instanceof EventDescription) {
            $e        = $event;
            $event    = $e->getName();
            $callback = $target;
        } elseif ($target instanceof EventDescription) {
            $e = $target;
            $e->setName($event);
            $callback = $argv;
        } elseif ($argv instanceof EventDescription) {
            $e = $argv;
            $e->setName($event);
            $e->setTarget($target);
        } else {
            $e = new $this->eventClass();
            $e->setName($event);
            $e->setTarget($target);
            $e->setParams($argv);
        }

        if (!$callback) {
            $callback = function() {
                return false;
            };
        }

        return $this->triggerListeners($event, $e, $callback);
    }

    /**
     * Trigger listeners until return value of one causes a callback to
     * evaluate to true
     *
     * Triggers listeners until the provided callback evaluates the return
     * value of one as true, or until all listeners have been executed.
     *
     * @param  string $event
     * @param  string|object $target Object calling emit, or symbol describing target (such as static method name)
     * @param  array|ArrayAccess $argv Array of arguments; typically, should be associative
     * @param  Callable $callback
     * @throws InvalidCallbackException if invalid callback provided
     */
    public function triggerUntil($event, $target, $argv = null, $callback = null)
    {
        if ($event instanceof EventDescription) {
            $e        = $event;
            $event    = $e->getName();
            $callback = $target;
        } elseif ($target instanceof EventDescription) {
            $e = $target;
            $e->setName($event);
            $callback = $argv;
        } elseif ($argv instanceof EventDescription) {
            $e = $argv;
            $e->setName($event);
            $e->setTarget($target);
        } else {
            $e = new $this->eventClass();
            $e->setName($event);
            $e->setTarget($target);
            $e->setParams($argv);
        }

        if (!is_callable($callback)) {
            throw new InvalidCallbackException('Invalid callback provided');
        }

        return $this->triggerListeners($event, $e, $callback);
    }

    /**
     * Attach a listener to an event
     *
     * The first argument is the event, and the next argument describes a
     * callback that will respond to that event. A CallbackHandler instance
     * describing the event listener combination will be returned.
     *
     * The last argument indicates a priority at which the event should be
     * executed. By default, this value is 1; however, you may set it for any
     * integer value. Higher values have higher priority (i.e., execute first).
     *
     * @param  string $event
     * @param  callback $callback PHP callback
     * @param  int $priority If provided, the priority at which to register the callback
     * @return ListenerAggregate (to allow later unsubscribe)
     */
    public function attach($event, $callback, $priority = 1)
    {
        if (empty($this->events[$event])) {
            $this->events[$event] = new PriorityQueue();
        }
        $listener = new CallbackHandler($event, $callback, array('priority' => $priority));
        $this->events[$event]->insert($listener, $priority);
        return $listener;
    }

    /**
     * Attach a listener aggregate
     *
     * Listener aggregates accept an EventCollection instance, and call attach()
     * one or more times, typically to attach to multiple events using local
     * methods.
     *
     * @param  ListenerAggregate $aggregate
     * @return mixed return value of {@link ListenerAggregate::attach()}
     */
    public function attachAggregate(ListenerAggregate $aggregate)
    {
        return $aggregate->attach($this);
    }

    /**
     * Unsubscribe a listener from an event
     *
     * @param  CallbackHandler $listener
     * @return bool Returns true if event and listener found, and unsubscribed; returns false if either event or listener not found
     */
    public function detach(CallbackHandler $listener)
    {
        $event = $listener->getEvent();
        if (empty($this->events[$event])) {
            return false;
        }
        $return = $this->events[$event]->remove($listener);
        if (!$return) {
            return false;
        }
        if (!count($this->events[$event])) {
            unset($this->events[$event]);
        }
        return true;
    }

    /**
     * Detach a listener aggregate
     *
     * Listener aggregates accept an EventCollection instance, and call detach()
     * of all previously attached listeners.
     *
     * @param  ListenerAggregate $aggregate
     * @return mixed return value of {@link ListenerAggregate::detach()}
     */
    public function detachAggregate(ListenerAggregate $aggregate)
    {
        return $aggregate->detach($this);
    }

    /**
     * Retrieve all registered events
     *
     * @return array
     */
    public function getEvents()
    {
        return array_keys($this->events);
    }

    /**
     * Retrieve all listeners for a given event
     *
     * @param  string $event
     * @return PriorityQueue
     */
    public function getListeners($event)
    {
        if (!array_key_exists($event, $this->events)) {
            return new PriorityQueue();
        }
        return $this->events[$event];
    }

    /**
     * Clear all listeners for a given event
     *
     * @param  string $event
     * @return void
     */
    public function clearListeners($event)
    {
        if (!empty($this->events[$event])) {
            unset($this->events[$event]);
        }
    }

    /**
     * Prepare arguments
     *
     * Use this method if you want to be able to modify arguments from within a
     * listener. It returns an ArrayObject of the arguments, which may then be
     * passed to trigger() or triggerUntil().
     *
     * @param  array $args
     * @return ArrayObject
     */
    public function prepareArgs(array $args)
    {
        return new ArrayObject($args);
    }

    /**
     * Trigger listeners
     *
     * Actual functionality for triggering listeners, to which both trigger() and triggerUntil() 
     * delegate.
     * 
     * @param  string $event Event name
     * @param  EventDescription $e 
     * @param  callback $callback 
     * @return ResponseCollection
     */
    protected function triggerListeners($event, EventDescription $e, $callback)
    {
        $responses = new ResponseCollection;

        $listeners = $this->getListeners($event);
        if ($listeners->isEmpty()) {
            return $this->triggerStaticListeners($callback, $e, $responses);
        }

        foreach ($listeners as $listener) {
            $responses->push(call_user_func($listener->getCallback(), $e));
            if ($e->propagationIsStopped()) {
                $responses->setStopped(true);
                break;
            }
            if (call_user_func($callback, $responses->last())) {
                $responses->setStopped(true);
                break;
            }
        }

        if (!$responses->stopped()) {
            $this->triggerStaticListeners($callback, $e, $responses);
        }
        return $responses;
    }

    /**
     * Emit listeners matching the current identifier found in the static listener
     *
     * @param  callback $callback
     * @param  Event $event
     * @param  ResponseCollection $responses
     * @return ResponseCollection
     */
    protected function triggerStaticListeners($callback, Event $event, ResponseCollection $responses)
    {
        if (!$staticConnections = $this->getStaticConnections()) {
            return $responses;
        }

        $identifiers = (array) $this->identifier;

        foreach ($identifiers as $id) {
            if (!$listeners = $staticConnections->getListeners($id, $event->getName())) {
                continue;
            }
            foreach ($listeners as $listener) {
                $responses->push(call_user_func($listener->getCallback(), $event));
                if ($event->propagationIsStopped()) {
                    $responses->setStopped(true);
                    break;
                }
                if (call_user_func($callback, $responses->last())) {
                    $responses->setStopped(true);
                    break;
                }
            }
        }

        return $responses;
    }
}
