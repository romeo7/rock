<?php

namespace rock\route;


use rock\event\Event;

class RouteEvent extends Event
{
    /**
     * @var boolean whether to continue running the action. Event handlers of
     * {@see \rock\base\Controller::EVENT_BEFORE_ACTION} may set this property to decide whether
     * to continue running the current action.
     */
    public $isValid = true;
    public $errors = 0;
} 