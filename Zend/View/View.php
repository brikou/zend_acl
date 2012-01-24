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
 * @package    Zend_View
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

namespace Zend\View;

use Zend\EventManager\EventCollection,
    Zend\EventManager\EventManager,
    Zend\Stdlib\RequestDescription as Request,
    Zend\Stdlib\ResponseDescription as Response;

/**
 * @category   Zend
 * @package    Zend_View
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class View
{
    /**
     * @var EventCollection
     */
    protected $events;

    /**
     * @var Renderer[]
     */
    protected $renderers = array();

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * Set MVC request object
     * 
     * @param  Request $request 
     * @return View
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Set MVC response object 
     * 
     * @param  Response $response 
     * @return View
     */
    public function setResponse(Response $response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * Get MVC request object
     * 
     * @return null|Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Get MVC response object
     * 
     * @return null|Response
     */
    public function getResponse()
    {
        return $this->response;
    }

 
    /**
     * Add a renderer
     * 
     * @param  Renderer $renderer 
     * @return View
     */
    public function addRenderer(Renderer $renderer)
    {
        $class = get_class($renderer);
        if (array_key_exists($class, $this->renderers)) {
            throw new Exception\RuntimeException(sprintf(
                'Unable to add renderer of type "%s"; another renderer of that type is already attached',
                $class
            ));
        }
        $this->renderers[$class] = $renderer;
        return $this;
    }

    /**
     * Does a renderer for the given class exist?
     * 
     * @param  string $class 
     * @return bool
     */
    public function hasRenderer($class)
    {
        return array_key_exists($class, $this->renderers);
    }

    /**
     * Retrieve renderer by class
     * 
     * @param  string $class 
     * @return false|Renderer
     */
    public function getRenderer($class)
    {
        if (!$this->hasRenderer($class)) {
            return false;
        }
        return $this->renderers[$class];
    }

    /**
     * Set the event manager instance
     * 
     * @param  EventCollection $events 
     * @return View
     */
    public function setEventManager(EventCollection $events)
    {
        $this->events = $events;
        return $this;
    }

    /**
     * Retrieve the event manager instance
     *
     * Lazy-loads a default instance if none available
     * 
     * @return EventCollection
     */
    public function events()
    {
        if (!$this->events instanceof EventCollection) {
            $this->setEventManager(new EventManager(array(
                __CLASS__,
                get_called_class(),
            )));
        }
        return $this->events;
    }

    /**
     * Add a rendering strategy
     *
     * Expects a callable. Strategies should accept a ViewEvent object, and should
     * return a Renderer instance if the strategy is selected.
     *
     * Internally, the callable provided will be subscribed to the "renderer" 
     * event, at the priority specified.
     * 
     * @param  callable $callable 
     * @param  int $priority 
     * @return View
     */
    public function addRenderingStrategy($callable, $priority = 1)
    {
        $this->events()->attach('renderer', $callable, $priority);
        return $this;
    }

    /**
     * Add a response strategy
     *
     * Expects a callable. Strategies should accept a ViewEvent object. The return
     * value will be ignored.
     *
     * Typical usages for a response strategy are to populate the Response object.
     *
     * Internally, the callable provided will be subscribed to the "response" 
     * event, at the priority specified.
     * 
     * @param  callable $callable 
     * @param  int $priority 
     * @return View
     */
    public function addResponseStrategy($callable, $priority = 1)
    {
        $this->events()->attach('response', $callable, $priority);
        return $this;
    }
     
    /**
     * Render the provided model.
     *
     * Internally, the following workflow is used:
     *
     * - Trigger the "renderer" event to select a renderer.
     * - Call the selected renderer with the provided Model
     * - Trigger the "response" event
     *
     * @triggers renderer(ViewEvent)
     * @triggers response(ViewEvent)
     * @param  Model $model
     * @return void
     */
    public function render(Model $model)
    {
        $event   = $this->getEvent();
        $event->setModel($model);
        $events  = $this->events();
        $results = $events->trigger('renderer', $event, function($result) {
            return ($result instanceof Renderer);
        });
        $renderer = $results->last();
        if (!$renderer) {
            throw new RuntimeException(sprintf(
                '%s: no renderer selected!',
                __METHOD__
            ));
        }
        $event->setRenderer($renderer);

        $rendered = $renderer->render($model);

        $event->setResult($rendered);

        $events->trigger('response', $event);
    }

    /**
     * Create and return ViewEvent used by render()
     * 
     * @return ViewEvent
     */
    protected function getEvent()
    {
        $event = new ViewEvent();
        $event->setTarget($this);
        if (null !== ($request = $this->getRequest())) {
            $event->setRequest($request);
        }
        if (null !== ($response = $this->getResponse())) {
            $event->setResponse($response);
        }
        return $event;
    }
}
