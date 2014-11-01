<?php

namespace rock\rbac;


use rock\base\ObjectTrait;
use rock\helpers\ArrayHelper;
use rock\helpers\Helper;
use rock\helpers\Serialize;

abstract class RBAC implements RBACInterface
{
    use ObjectTrait;

    //public $throwException = true;
    protected static $items = [];
    protected static $assignments = [];


    /**
     * @param string $itemName
     * @throws Exception
     * @return Item|null
     */
    public function get($itemName)
    {
        if(!$this->has($itemName)) {
            return null;
        }

        return  $this->processData($itemName);
    }


    /**
     * @inheritdoc
     */
    public function getRole($name)
    {
        if(!$this->has($name)) {
            return null;
        }

        $data = $this->processData($name);

        if ($data instanceof Role) {
            return $data;
        }
        throw new Exception(Exception::CRITICAL, Exception::UNKNOWN_ROLE, ['name' => serialize($data)]);
    }

    /**
     * @inheritdoc
     */
    public function getPermission($name)
    {
        if(!$this->has($name)) {
            return null;
        }

        $data = $this->processData($name);

        if ($data instanceof Permission) {
            return $data;
        }
        throw new Exception(Exception::CRITICAL, Exception::UNKNOWN_PERMISSION, ['name' => serialize($data)]);
    }



    /**
     * Checks whether there is a loop in the authorization item hierarchy.
     *
     * @param Role $role the parent item
     * @param Item $item the child item to be added to the hierarchy
     * @return boolean whether a loop exists
     */
    protected function detect(Role $role, Item $item)
    {
        if ($item->name === $role->name || $this->hasChild($role, $item->name)) {
            return true;
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function hasChild(Role $role, $itemName)
    {
        return !empty(static::$items[$role->name]['items']) &&
               array_key_exists($itemName, array_flip(static::$items[$role->name]['items']));
    }

    /**
     * @inheritdoc
     */
    public function hasChildren(Role $role, array $itemNames)
    {
        foreach ($itemNames as $name) {
            if ($this->hasChild($role, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get item
     *
     * @param string $itemName
     * @return Item|null
     */
    public function __get($itemName)
    {
        return $this->get($itemName);
    }

    /**
     * @inheritdoc
     */
    public function getMulti(array $names)
    {
        $result = [];
        foreach ($names as $name) {
            $result[$name] = $this->get($name) ? : null;
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getAll(array $only = [], array $exclude = [])
    {
        return ArrayHelper::only(static::$items, $only, $exclude);
    }

    /**
     * @inheritdoc
     */
    public function getCount()
    {
        return count(static::$items);
    }

    /**
     * @inheritdoc
     */
    public function has($name)
    {
        return isset(static::$items[$name]);
    }

    /**
     * @param array $only
     * @param array $exclude
     * @return \ArrayIterator
     */
    public function getIterator(array $only = [], array $exclude = [])
    {
        return new \ArrayIterator($this->getAll($only, $exclude));
    }

    /**
     * @inheritdoc
     */
    public function count()
    {
        return $this->getCount();
    }


    /**
     * @inheritdoc
     */
    public function createRole($name)
    {
        $role = new Role;
        $role->name = $name;
        return $role;
    }

    /**
     * @inheritdoc
     */
    public function createPermission($name)
    {
        $permission = new Permission();
        $permission->name = $name;
        return $permission;
    }


    protected static $roles;
    
    public function getRecursiveRoles($roleName)
    {
        if (!$this->has($roleName)) {
            return [];
        }
        if (static::$items[$roleName]['type'] === self::TYPE_PERMISSION) {
            return null;
        } elseif (static::$items[$roleName]['type'] === self::TYPE_ROLE){
            if (isset(static::$items[$roleName]['items'])) {
                if (isset(static::$roles[$roleName])) {
                    return static::$roles[$roleName];
                }
                $roles = [$roleName];
                foreach (static::$items[$roleName]['items'] as $value) {
                    if ($result = $this->getRecursiveRoles($value)) {
                        $roles = array_merge($roles, $result);
                    }
                }
                return static::$roles[$roleName] = array_unique($roles);
            }

            return [$roleName];
        }

        throw new Exception(Exception::CRITICAL, Exception::UNKNOWN_TYPE, [
            'name' => Helper::getValueIsset(
                    static::$items[$roleName]['type']
                )
        ]);
    }


    protected static $permissions;
    public function getRecursivePermissions($roleName)
    {
        if (!$this->has($roleName)) {
            return [];
        }
        if (static::$items[$roleName]['type'] === self::TYPE_PERMISSION) {
            return [$roleName];
        } elseif (static::$items[$roleName]['type'] === self::TYPE_ROLE){
            if (isset(static::$items[$roleName]['items'])) {
                if (isset(static::$permissions[$roleName])) {
                    return static::$permissions[$roleName];
                }
                $permissions = [];
                foreach (static::$items[$roleName]['items'] as $value) {
                    if ($result = $this->getRecursivePermissions($value)) {
                        $permissions = array_merge($permissions, $result);
                    }
                }
                return static::$permissions[$roleName] = array_unique($permissions);
            }

            return null;
        }

        throw new Exception(Exception::CRITICAL, Exception::UNKNOWN_TYPE, [
            'name' => Helper::getValueIsset(
                    static::$items[$roleName]['type']
                )
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function checkRecursive($itemName, array $params = null)
    {
        if (!$this->has($itemName)) {
            return false;
        }

        $item = $this->processData($itemName);

        if ($item instanceof Permission) {
            return true;
            //return $item->execute($params);
        } elseif ($item instanceof Role){
            if (!$item->execute($params)) {
                return false;
            }

            if (isset(static::$items[$itemName]['items'])) {
                foreach (static::$items[$itemName]['items'] as $value) {
                    if (!$this->checkRecursive($value/*, $params*/)) {
                        return false;
                    }
                }
            }

            return true;
        }

        throw new Exception(Exception::CRITICAL, Exception::UNKNOWN_TYPE, [
            'name' => Helper::getValueIsset(
                    static::$items[$itemName]['type']
                )
        ]);
    }

    protected function checkRole($roleName, array $params = null)
    {
        if (!$this->has($roleName)) {
            return false;
        }
        $role = $this->processData($roleName);
        if (!$role instanceof Role) {
            throw new Exception(Exception::CRITICAL, Exception::UNKNOWN_TYPE, ['name' => serialize($role)]);
        }

        return $role->execute($params);
    }

    protected function checkPermission($permissionName, array $params = null)
    {
        if (!$this->has($permissionName)) {
            return false;
        }
        $permission = $this->processData($permissionName);
        if (!$permission instanceof Permission) {
            throw new Exception(Exception::CRITICAL, Exception::UNKNOWN_TYPE, ['name' => serialize($permission)]);
        }

        return $permission->execute($params);
    }


    /**
     * @inheritdoc
     */
    public function check($userId, $itemName, array $params = null)
    {
        if (!$this->has($itemName) || (!$assignments = $this->getAssignments($userId))) {
            return false;
        }

        if (static::$items[$itemName]['type'] === self::TYPE_ROLE) {
            if (in_array($itemName, $assignments, true)) {
                return $this->checkRecursive($itemName, $params);
            }

            foreach ($assignments as $assignment) {
                if (in_array($itemName, $this->getRecursiveRoles($assignment), true)) {
                    return $this->checkRecursive($itemName, $params);
                }
            }

            return false;
        }

        foreach ($assignments as $assignment) {
            if (isset(static::$items[$assignment]['items']) &&
                in_array($itemName, static::$items[$assignment]['items'], true)) {
                if (!$this->checkRecursive($assignment)) {
                    return false;
                }

                return $this->checkPermission($itemName, $params);
            }
        }

        return false;
    }




    /**
     * @inheritdoc
     */
    public function hasAssigned($userId, $roleName)
    {
        $assignments = array_flip($this->getAssignments($userId));
        return isset($assignments[$roleName]);
    }


    /**
     * @param string $itemName
     * @return Item
     * @throws Exception
     */
    protected function processData($itemName)
    {
        if (empty(static::$items[$itemName]['data'])) {
            throw new Exception(Exception::CRITICAL, Exception::NOT_DATA_PARAMS);
        }
        $data = static::$items[$itemName]['data'];
        if (is_string($data)) {
            $data = Serialize::unserialize($data);
        }

        if ($data instanceof Item) {
            $data->name = $itemName;
            return $data;
        }

        throw new Exception(Exception::CRITICAL, Exception::UNKNOWN_TYPE, ['name' => serialize($data)]);
    }

    public function refresh()
    {
        static::$items = [];
        static::$assignments = [];
    }
}