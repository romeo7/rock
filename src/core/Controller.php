<?php
namespace rock\core;

use rock\base\Alias;
use rock\components\ComponentsInterface;
use rock\components\ComponentsTrait;
use rock\helpers\ArrayHelper;
use rock\helpers\FileHelper;
use rock\helpers\Instance;
use rock\helpers\StringHelper;
use rock\i18n\i18n;
use rock\response\Response;
use rock\Rock;
use rock\template\Template;

abstract class Controller implements ComponentsInterface
{
    use ComponentsTrait {
        ComponentsTrait::__set as parentSet;
        ComponentsTrait::__get as parentGet;
        ComponentsTrait::init as parentInit;
    }

    /**
     * @event ActionEvent an event raised right before executing a controller action.
     * You may set {@see \rock\core\ActionEvent::$isValid} to be false to cancel the action execution.
     */
    const EVENT_BEFORE_ACTION = 'beforeAction';
    /**
     * @event ActionEvent an event raised right after executing a controller action.
     */
    const EVENT_AFTER_ACTION = 'afterAction';

    /** @var  Response */
    public $response;
    /** @var  Template|string|array */
    public $template = 'template';

    public function init()
    {
        $this->parentInit();
        $this->template = Instance::ensure($this->template);
        $this->template->context = $this;
        if (!$this->template->existsConst(['res', 'context'])) {
            Rock::$app->controller = $this;
            $this->template->addConst('res', static::defaultData(), false, true);
        }
    }

    /**
     * Array data by context
     *
     * @return array
     */
    public static function defaultData()
    {
        return [];
    }

    /**
     * Renders a view with a layout.
     *
     *
     * @param string $layout       name of the view to be rendered.
     * @param array  $placeholders
     * @param string $defaultPathLayout
     * @return string the rendering result. Null if the rendering result is not required.
     */
    public function render($layout, array $placeholders = [],$defaultPathLayout = '@views')
    {

        $layout = FileHelper::normalizePath(Alias::getAlias($layout));
        if (!strstr($layout, DS)) {
            $class = explode('\\', get_class($this));
            $layout = Alias::getAlias($defaultPathLayout). DS . 'layouts' . DS .
                      strtolower(str_replace('Controller', '', array_pop($class))) . DS .
                      $layout;
        }

        return $this->template->render($layout, $placeholders, $this);
    }

    public static function context(array $keys = [])
    {
        $keys = array_merge(['context'], $keys);
        return ArrayHelper::getValue(static::defaultData(), $keys);
    }

    /**
     * Display notPage layout
     *
     * @param string|null $layout
     * @return string|void
     */
    public function notPage($layout = null)
    {
        if (isset($this->response)) {
            $this->response->status404();
        }
        $this->template->title = StringHelper::upperFirst(i18n::t('notPage'));
        if (!isset($layout)) {
            $layout = '@common.views/layouts/notPage';
        }
        return $this->render($layout);
    }

    /**
     * This method is invoked right before an action is executed.
     *
     * The method will trigger the {@see \rock\core\Controller::EVENT_BEFORE_ACTION} event. The return value of the method
     * will determine whether the action should continue to run.
     *
     * If you override this method, your code should look like the following:
     *
     * ```php
     * public function beforeAction($action)
     * {
     *     if (parent::beforeAction($action)) {
     *         // your custom code here
     *         return true;  // or false if needed
     *     } else {
     *         return false;
     *     }
     * }
     * ```
     *
     * @param string $action the action to be executed.
     * @return boolean whether the action should continue to run.
     */
    public function beforeAction($action)
    {
        $event = new ActionEvent($action);
        $this->trigger(self::EVENT_BEFORE_ACTION, $event);
        return $event->isValid;
    }

    /**
     * This method is invoked right after an action is executed.
     *
     * The method will trigger the {@see \rock\core\Controller::EVENT_AFTER_ACTION} event. The return value of the method
     * will be used as the action return value.
     *
     * If you override this method, your code should look like the following:
     *
     * ```php
     * public function afterAction($action, $result)
     * {
     *     $result = parent::afterAction($action, $result);
     *     // your custom code here
     *     return $result;
     * }
     * ```
     *
     * @param string $action the action just executed.
     * @param mixed $result the action return result.
     * @return mixed the processed action result.
     */
    public function afterAction($action, $result)
    {
        $event = new ActionEvent($action);
        $event->result = $result;
        $this->trigger(self::EVENT_AFTER_ACTION, $event);
        return $event->result;
    }

    /**
     * Get method
     *
     * @param string $actionName name of method
     * @return mixed
     * @throws ControllerException
     */
    public function method($actionName)
    {
        $args = array_slice(func_get_args(), 1) ? : [];
        if (!method_exists($this, $actionName)) {
            $this->detachBehaviors();
            throw new ControllerException(ControllerException::UNKNOWN_METHOD, [
                'method' => get_class($this) . '::' . $actionName
            ]);
        }
        if ($this->beforeAction($actionName) === false) {
            return null;
        }
        $result = call_user_func_array([$this, $actionName], $args);//$this->$actionName($route);
        return $this->afterAction($actionName, $result);
    }

    public static function findUrlById($resource)
    {
        return null;
    }
}