<?php
namespace rock;


use League\Flysystem\Util;
use rock\base\ClassName;
use rock\base\Config;
use rock\base\Controller;
use rock\di\Container;
use rock\event\Event;
use rock\exception\BaseException;
use rock\helpers\ObjectHelper;
use rock\helpers\StringHelper;
use rock\helpers\Trace;
use rock\i18n\i18nInterface;
use rock\log\Log;

class Rock
{
    use ClassName;
    /**
     *  @event AppEvent an event that is triggered at the beginning
     */
    const EVENT_BEGIN_APP = 'beginApp';
    /**
     *  @event AppEvent an event that is triggered at the end
     */
    const EVENT_END_APP = 'endApp';

    /**
     * @var RockInterface
     */
    public static $app;
    /**
     * @var string the charset currently used for the application.
     */
    public $charset = 'UTF-8';
    /**
     * @var string the language that is meant to be used for end users.
     * @see sourceLanguage
     */
    public $language = i18nInterface::EN;
    /**
     * A list of the languages supported by the application.
     * @var array
     */
    public $allowLanguages = [];
    /**
     * Current controller
     * @var Controller
     */
    public $controller;


    /**
     * Bootstrap
     *
     * @param array $configs
     * @throws BaseException
     */
    public static function bootstrap(array $configs)
    {
        \rock\helpers\Trace::beginProfile(\rock\helpers\Trace::APP, \rock\helpers\Trace::TOKEN_APP_RUNTIME);
        try {
            Config::set($configs);
            Container::addMulti($configs['_components']);

            Event::on(
                static::className(),
                self::EVENT_END_APP,
                function(){
                    Rock::$app->response->send();
                }
            );
            // Triggered at the beginning
            Event::trigger(static::className(), self::EVENT_BEGIN_APP);

            // Routing
            Rock::$app->route->run();

        } catch (\Exception $e) {
            throw new BaseException($e->getMessage(), [], $e);
        }
        //var_dump(Trace::getTime(Trace::APP_TIME));
        \rock\helpers\Trace::endProfile(\rock\helpers\Trace::APP, \rock\helpers\Trace::TOKEN_APP_RUNTIME);
        //var_dump(Trace::get('db.query'), Trace::get(\rock\helpers\Trace::APP));

        // Triggered at the end
        Event::trigger(static::className(), self::EVENT_END_APP);
    }


    /**
     * Creates a new object using the given configuration.
     *
     * The configuration can be either a string or an array.
     * If a string, it is treated as the *object class*; if an array,
     * it must contain a `class` element specifying the *object class*, and
     * the rest of the name-value pairs in the array will be used to initialize
     * the corresponding object properties.
     *
     * Below are some usage examples:
     *
     * ```php
     * $object = Rock::factory('\rock\db\Connection');
     * $object = Rock::factory(\rock\db\Connection::className());
     * $object = Rock::factory([
     *     'class' => '\rock\db\Connection',
     *     'dsn' => $dsn,
     *     'username' => $username,
     *     'password' => $password,
     * ]);
     * $object = Rock::factory($arg1, $arg2, [
     *     'class' => 'apps\frontend\FooController',
     *     'test' => 'test',
     * ]);
     * ```
     *
     *
     * This method can be used to create any object as long as the object's constructor is
     * defined like the following:
     *
     * ```php
     * public function __construct(..., $config = []) {
     * }
     * ```
     *
     * The method will pass the given configuration as the last parameter of the constructor,
     * and any additional parameters to this method will be passed as the rest of the constructor parameters.
     *
     * @param string|array $configs the configuration. It can be either a string representing the class name
     *                             or an array representing the object configuration.
     * @param mixed ...,$configs arguments for object
     * @return object|null the created object
     */
    public static function factory(/*$args...*/$configs)
    {
        return call_user_func_array([Container::className(), 'load'], func_get_args());
    }


    /**
     * Get instance of model
     *
     * @param string $class name of class
     * @return null|object
     */
    public function __get($class)
    {
        return self::factory($class);
    }


    /**
     * @var array registered path aliases
     * @see getAlias()
     * @see setAlias()
     */
    public static $aliases = ['@rock' => __DIR__];

    /**
     * Translates a path alias into an actual path.
     *
     * The translation is done according to the following procedure:
     *
     * 1. If the given alias does not start with '@', it is returned back without change;
     * 2. Otherwise, look for the longest registered alias that matches the beginning part
     *    of the given alias. If it exists, replace the matching part of the given alias with
     *    the corresponding registered path.
     * 3. Throw an exception or return false, depending on the `$throwException` parameter.
     *
     * For example, by default '@rock' is registered as the alias to the Rock framework directory,
     * say '/path/to/rock'. The alias '@rock/web' would then be translated into '/path/to/rock/web'.
     *
     * If you have registered two aliases '@foo' and '@foo/bar'. Then translating '@foo/bar/config'
     * would replace the part '@foo/bar' (instead of '@foo') with the corresponding registered path.
     * This is because the longest alias takes precedence.
     *
     * However, if the alias to be translated is '@foo/barbar/config', then '@foo' will be replaced
     * instead of '@foo/bar', because '/' serves as the boundary character.
     *
     * Note, this method does not check if the returned path exists or not.
     *
     * @param string  $alias          the alias to be translated.
     * @param array   $placeholders
     * @param boolean $throwException whether to throw an exception if the given alias is invalid.
     *                                If this is false and an invalid alias is given, false will be returned by this method.
     * @throws \Exception if the alias is invalid while $throwException is true.
     * @return string|boolean the path corresponding to the alias, false if the root alias is not previously registered.
     * @see setAlias()
     */
    public static function getAlias($alias, array $placeholders = [],  $throwException = true)
    {
        $alias = StringHelper::replace($alias, $placeholders);
        if (strncmp($alias, '@', 1)) {
            // not an alias
            return $alias;
        }

        $delimiter = ObjectHelper::isNamespace($alias) ? '\\' : '/';

        $pos = strpos($alias, $delimiter);
        $root = $pos === false ? $alias : substr($alias, 0, $pos);

        if (isset(static::$aliases[$root])) {
            if (is_string(static::$aliases[$root])) {
                return $pos === false ? static::$aliases[$root] : static::$aliases[$root] . substr($alias, $pos);
            } else {
                foreach (static::$aliases[$root] as $name => $path) {
                    if (strpos($alias . $delimiter, $name . $delimiter) === 0) {
                        return $path . substr($alias, strlen($name));
                    }
                }
            }
        }

        if ($throwException) {
            throw new \Exception("Invalid path alias: $alias");
        } else {
            return false;
        }
    }

    /**
     * Registers a path alias.
     *
     * A path alias is a short name representing a long path (a file path, a URL, etc.)
     * For example, we use '@rock' as the alias of the path to the Rock framework directory.
     *
     * A path alias must start with the character '@' so that it can be easily differentiated
     * from non-alias paths.
     *
     * Note that this method does not check if the given path exists or not. All it does is
     * to associate the alias with the path.
     *
     * Any trailing '/' and '\' characters in the given path will be trimmed.
     *
     * @param string $alias the alias name (e.g. "@rock"). It must start with a '@' character.
     * It may contain the forward slash '/' which serves as boundary character when performing
     * alias translation by [[getAlias()]].
     * @param string $path the path corresponding to the alias. Trailing '/' and '\' characters
     * will be trimmed. This can be
     *
     * - a directory or a file path (e.g. `/tmp`, `/tmp/main.txt`)
     * - a URL (e.g. `http://www.site.com`)
     * - a path alias (e.g. `@rock/base`). In this case, the path alias will be converted into the
     *   actual path first by calling [[getAlias()]].
     *
     * @throws \Exception if $path is an invalid alias.
     * @see getAlias()
     */
    public static function setAlias($alias, $path)
    {
        if (strncmp($alias, '@', 1)) {
            $alias = '@' . $alias;
        }
        $delimiter = ObjectHelper::isNamespace($alias) ? '\\' : '/';

        $pos = strpos($alias, $delimiter);
        $root = $pos === false ? $alias : substr($alias, 0, $pos);
        if ($path !== null) {
            $path = strncmp($path, '@', 1) ? rtrim($path, '\\/') : static::getAlias($path);
            if (!isset(static::$aliases[$root])) {
                if ($pos === false) {
                    static::$aliases[$root] = $path;
                } else {
                    static::$aliases[$root] = [$alias => $path];
                }
            } elseif (is_string(static::$aliases[$root])) {
                if ($pos === false) {
                    static::$aliases[$root] = $path;
                } else {
                    static::$aliases[$root] = [
                        $alias => $path,
                        $root => static::$aliases[$root],
                    ];
                }
            } else {
                static::$aliases[$root][$alias] = $path;
                krsort(static::$aliases[$root]);
            }
        } elseif (isset(static::$aliases[$root])) {
            if (is_array(static::$aliases[$root])) {
                unset(static::$aliases[$root][$alias]);
            } elseif ($pos === false) {
                unset(static::$aliases[$root]);
            }
        }
    }

    /**
     * Defines path aliases.
     * This method calls @see Rock::setAlias() to register the path aliases.
     * This method is provided so that you can define path aliases when configuring a module.
     * @property array list of path aliases to be defined. The array keys are alias names
     * (must start with '@') and the array values are the corresponding paths or aliases.
     * See @see Rock::setAliases() for an example.
     * @param array $aliases list of path aliases to be defined. The array keys are alias names
     * (must start with '@') and the array values are the corresponding paths or aliases.
     * For example,
     *
     * ```php
     * [
     *     '@models' => '@app/models', // an existing alias
     *     '@backend' => __DIR__ . '/../backend',  // a directory
     * ]
     * ```
     */
    public static function setAliases($aliases)
    {
        foreach ($aliases as $name => $alias) {
            static::setAlias($name, $alias);
        }
    }

    /**
     * Translate
     *
     * @param string|array  $keys
     * @param array $placeholders
     * @param string|null  $category
     * @param string $locale
     * @return null|string.
     */
    public static function t($keys, array $placeholders = [], $category = null, $locale = null)
    {
        return static::$app->i18n
            ->locale($locale ? : static::$app->language)
            ->category($category)
            ->removeBraces(true)
            ->translate($keys, $placeholders);
    }

    /**
     * Trace
     *
     * @param string $category
     * @param mixed  $token
     * @param null   $data
     *
     * ```php
     * Rock::trace('db', ['dsn' => ..., 'query' => ...]);
     *
     * Rock::trace(__METHOD__, 'text');
     * ```
     */
    public static function trace($category, $token, $data = null)
    {
        Trace::trace($category, $token, $data);
    }

    public static function beginProfile($category, $token)
    {
        Trace::beginProfile($category, $token);
    }

    public static function endProfile($category, $token)
    {
        Trace::endProfile($category, $token);
    }

    /**
     * Logging as `INFO`
     *
     * @param string $message
     * @param array  $placeholders placeholders for replacement
     * @param array  $traces
     */
    public static function info($message, array $placeholders = [], array $traces = [])
    {
        static::log(Log::INFO, $message, $placeholders, $traces);
    }

    /**
     * Logging as `DEBUG`
     *
     * @param string $message
     * @param array  $placeholders placeholders for replacement
     * @param array  $traces
     */
    public static function debug($message, array $placeholders = [], array $traces = [])
    {
        static::log(Log::DEBUG, $message, $placeholders, $traces);
    }

    /**
     * Logging as `WARNING`
     *
     * @param string $message
     * @param array  $placeholders placeholders for replacement
     * @param array  $traces
     */
    public static function warning($message, array $placeholders = [], array $traces = [])
    {
        static::log(Log::WARNING, $message, $placeholders, $traces);
    }

    /**
     * Logging as `ERROR`
     *
     * @param string $message
     * @param array  $placeholders placeholders for replacement
     * @param array  $traces
     */
    public static function error($message, array $placeholders = [], array $traces = [])
    {
        static::log(Log::ERROR, $message, $placeholders, $traces);
    }

    /**
     * Logging as `CRITICAL`
     *
     * @param string $message
     * @param array  $placeholders placeholders for replacement
     * @param array  $traces
     */
    public static function crit($message, array $placeholders = [], array $traces = [])
    {
        static::log(Log::CRITICAL, $message, $placeholders, $traces);
    }

    public static function log($level, $message, array $placeholders = [], array $traces = [])
    {
        if (empty($traces)) {
            $traces = BaseException::getTraces(3);
        }
        $message = BaseException::replace($message, $traces, BaseException::$asStack);
        static::$app->log->log($level, $message, $placeholders);
    }
}