<?php
namespace rock\db;

/**
 * ActiveRelationTrait implements the common methods and properties for active record relational queries.
 *
 * @method ActiveRecordInterface one()
 * @method ActiveRecordInterface[] all()
 * @property ActiveRecord $modelClass
 */
trait ActiveRelationTrait
{
    /**
     * @var boolean whether this query represents a relation to more than one record.
     * This property is only used in relational context. If true, this relation will
     * populate all query results into AR instances using {@see \rock\db\Query::all()}.
     * If false, only the first row of the results will be retrieved using {@see \rock\db\Query::one()}.
     */
    public $multiple;
    /**
     * @var ActiveRecord the primary model of a relational query.
     * This is used only in lazy loading with dynamic query options.
     */
    public $primaryModel;
    /**
     * @var array the columns of the primary and foreign tables that establish a relation.
     * The array keys must be columns of the table for this relation, and the array values
     * must be the corresponding columns from the primary table.
     * Do not prefix or quote the column names as this will be done automatically by Rock.
     * This property is only used in relational context.
     */
    public $link;
    /**
     * @var array|object the query associated with the pivot table.
     * Please call {@see \rock\db\ActiveRelationTrait::via()}
     * to set this property instead of directly setting it.
     * This property is only used in relational context.
     * @see via()
     */
    public $via;
    /**
     * @var string the name of the relation that is the inverse of this relation.
     * For example, an order has a customer, which means the inverse of the "customer" relation
     * is the "orders", and the inverse of the "orders" relation is the "customer".
     * If this property is set, the primary record(s) will be referenced through the specified relation.
     * For example, `$customer->orders[0]->customer` and `$customer` will be the same object,
     * and accessing the customer of an order will not trigger new DB query.
     * This property is only used in relational context.
     * @see inverseOf()
     */
    public $inverseOf;

    /**
     * @var \Closure
     */
    public $viaCallback;

    /**
     * Clones internal objects.
     */
    public function __clone()
    {
        //parent::__clone();
        // make a clone of "via" object so that the same query object can be reused multiple times
        if (is_object($this->via)) {
            $this->via = clone $this->via;
        } elseif (is_array($this->via)) {
            $this->via = [$this->via[0], clone $this->via[1]];
        }
    }

    /**
     * Specifies the relation associated with the pivot table.
     *
     * Use this method to specify a pivot record/table when declaring a relation in the {@see \rock\db\ActiveRecord} class:
     *
     * ```php
     * public function getOrders()
     * {
     *     return $this->hasOne(Order::className(), ['id' => 'order_id']);
     * }
     *
     * public function getOrderItems()
     * {
     *     return $this->hasMany(Item::className(), ['id' => 'item_id'])
     *                 ->via('orders');
     * }
     * ```
     *
     * @param string $relationName the relation name. This refers to a relation declared in {@see \rock\db\ActiveRelationTrait::$primaryModel}.
     * @param callable $callable a PHP callback for customizing the relation associated with the pivot table.
     * Its signature should be `function($query)`, where `$query` is the query to be customized.
     * @return static the relation object itself.
     */
    public function via($relationName, callable $callable = null)
    {
        $relation = $this->primaryModel->getRelation($relationName);
        $this->via = [$relationName, $relation];
        if ($callable !== null) {
            call_user_func($callable, $relation);
        }

        return $this;
    }

    /**
     * Sets the name of the relation that is the inverse of this relation.
     * For example, an order has a customer, which means the inverse of the "customer" relation
     * is the "orders", and the inverse of the "orders" relation is the "customer".
     * If this property is set, the primary record(s) will be referenced through the specified relation.
     * For example, `$customer->orders[0]->customer` and `$customer` will be the same object,
     * and accessing the customer of an order will not trigger a new DB query.
     *
     * Use this method when declaring a relation in the {@see \rock\db\ActiveRecord} class:
     *
     * ```php
     * public function getOrders()
     * {
     *     return $this->hasMany(Order::className(), ['customer_id' => 'id'])->inverseOf('customer');
     * }
     * ```
     *
     * @param string $relationName the name of the relation that is the inverse of this relation.
     * @return static the relation object itself.
     */
    public function inverseOf($relationName)
    {
        $this->inverseOf = $relationName;

        return $this;
    }

    /**
     * Finds the related records for the specified primary record.
     * This method is invoked when a relation of an ActiveRecord is being accessed in a lazy fashion.
     *
*@param string $name the relation name
     * @param ActiveRecordInterface|BaseActiveRecord $model the primary model
     * @return mixed the related record(s)
     * @throws DbException if the relation is invalid
     */
    public function findFor($name, $model)
    {
        if (method_exists($model, 'get' . $name)) {
            $method = new \ReflectionMethod($model, 'get' . $name);
            $realName = lcfirst(substr($method->getName(), 3));
            if ($realName !== $name) {
                throw new DbException('Relation names are case sensitive. ' . get_class($model) .
                                                         " has a relation named '{$realName}' instead of '{$name}'.");
            }
        }

        $related = $this->multiple ? $this->all() : $this->one();

        if ($this->inverseOf === null || empty($related)) {
            return $related;
        }

        $inverseRelation = (new $this->modelClass)->getRelation($this->inverseOf);

        if ($this->multiple) {
            foreach ($related as $i => $relatedModel) {
                if ($relatedModel instanceof ActiveRecordInterface) {
                    $relatedModel->populateRelation($this->inverseOf, $inverseRelation->multiple ? [$model] : $model);
                } else {
                    $related[$i][$this->inverseOf] = $inverseRelation->multiple ? [$model] : $model;
                }
            }
        } else {
            if ($related instanceof ActiveRecordInterface) {
                $related->populateRelation($this->inverseOf, $inverseRelation->multiple ? [$model] : $model);
            } else {
                $related[$this->inverseOf] = $inverseRelation->multiple ? [$model] : $model;
            }
        }

        return $related;
    }

    /**
     * Finds the related records and populates them into the primary models.
     *
*@param string $name the relation name
     * @param array $primaryModels primary models
     * @return array the related models
     * @throws DbException if {@see \rock\db\ActiveRelationTrait::$link} is invalid
     */
    public function populateRelation($name, &$primaryModels)
    {
        /** @var \rock\db\ActiveQuery $this */

        if (!is_array($this->link)) {
            throw new DbException('Invalid link: it must be an array of key-value pairs.');
        }

        if ($this->via instanceof self) {
            // via pivot table
            /* @var $viaQuery ActiveRelationTrait */
            $viaQuery = $this->via;
            $viaModels = $viaQuery->findPivotRows($primaryModels);
            $this->filterByModels($viaModels);
        } elseif (is_array($this->via)) {
            // via relation
            /* @var $viaQuery ActiveRelationTrait|ActiveQueryTrait */
            list($viaName, $viaQuery) = $this->via;
            if ($viaQuery->asArray === null) {
                // inherit asArray from primary query
                $viaQuery->asArray($this->asArray);
            }
            $viaQuery->primaryModel = null;
            $viaModels = $viaQuery->populateRelation($viaName, $primaryModels);
            $this->filterByModels($viaModels);
        } else {
            $this->filterByModels($primaryModels);
        }

        if (count($primaryModels) === 1 && !$this->multiple) {
            $model = $this->one();
            foreach ($primaryModels as $i => $primaryModel) {
                if ($primaryModel instanceof ActiveRecordInterface) {
                    $primaryModel->populateRelation($name, $model);
                } else {
                    $primaryModels[$i][$name] = $model;
                }
                if ($this->inverseOf !== null) {
                    $this->populateInverseRelation($primaryModels, [$model], $name, $this->inverseOf);
                }
            }

            return [$model];
        } else {
            // https://github.com/yiisoft/yii2/issues/3197
            // delay indexing related models after buckets are built
            $indexBy = $this->indexBy;
            $this->indexBy = null;
            $models = $this->all();

            if (isset($viaModels, $viaQuery)) {
                $buckets = $this->buildBuckets($models, $this->link, $viaModels, $viaQuery->link);
            } else {
                $buckets = $this->buildBuckets($models, $this->link);
            }

            $this->indexBy = $indexBy;
            if ($this->indexBy !== null && $this->multiple) {
                $buckets = $this->indexBuckets($buckets, $this->indexBy);
            }

            $link = array_values(isset($viaQuery) ? $viaQuery->link : $this->link);
            foreach ($primaryModels as $i => $primaryModel) {
                if ($this->multiple && count($link) == 1 && is_array($keys = $primaryModel[reset($link)])) {
                    $value = [];
                    foreach ($keys as $key) {
                        if (!is_scalar($key)) {
                            $key = serialize($key);
                        }
                        if (isset($buckets[$key])) {
                            if ($this->indexBy !== null) {
                                // if indexBy is set, array_merge will cause renumbering of numeric array
                                foreach($buckets[$key] as $bucketKey => $bucketValue) {
                                    $value[$bucketKey] = $bucketValue;
                                }
                            } else {
                                $value = array_merge($value, $buckets[$key]);
                            }
                        }
                    }
                } else {
                    $key = $this->getModelKey($primaryModel, $link);
                    $value = isset($buckets[$key]) ? $buckets[$key] : ($this->multiple ? [] : null);
                }
                if ($primaryModel instanceof ActiveRecordInterface) {
                    $primaryModel->populateRelation($name, $value);
                } else {
                    $primaryModels[$i][$name] = $value;
                }
            }
            if ($this->inverseOf !== null) {
                $this->populateInverseRelation($primaryModels, $models, $name, $this->inverseOf);
            }

            return $models;
        }
    }

    /**
     * @param ActiveRecordInterface[] $primaryModels primary models
     * @param ActiveRecordInterface[] $models models
     * @param string $primaryName the primary relation name
     * @param string $name the relation name
     */
    private function populateInverseRelation(&$primaryModels, $models, $primaryName, $name)
    {
        if (empty($models) || empty($primaryModels)) {
            return;
        }
        $model = reset($models);
        /** @var ActiveQueryInterface|ActiveQuery $relation */
        $relation = $model instanceof ActiveRecordInterface ? $model->getRelation($name) : (new $this->modelClass)->getRelation($name);

        if ($relation->multiple) {
            $buckets = $this->buildBuckets($primaryModels, $relation->link, null, null, false);
            if ($model instanceof ActiveRecordInterface) {
                foreach ($models as $model) {
                    $key = $this->getModelKey($model, $relation->link);
                    $model->populateRelation($name, isset($buckets[$key]) ? $buckets[$key] : []);
                }
            } else {
                foreach ($primaryModels as $i => $primaryModel) {
                    if ($this->multiple) {
                        foreach ($primaryModel as $j => $m) {
                            $key = $this->getModelKey($m, $relation->link);
                            $primaryModels[$i][$j][$name] = isset($buckets[$key]) ? $buckets[$key] : [];
                        }
                    } elseif (!empty($primaryModel[$primaryName])) {
                        $key = $this->getModelKey($primaryModel[$primaryName], $relation->link);
                        $primaryModels[$i][$primaryName][$name] = isset($buckets[$key]) ? $buckets[$key] : [];
                    }
                }
            }
        } else {
            if ($this->multiple) {
                foreach ($primaryModels as $i => $primaryModel) {
                    foreach ($primaryModel[$primaryName] as $j => $m) {
                        if ($m instanceof ActiveRecordInterface) {
                            $m->populateRelation($name, $primaryModel);
                        } else {
                            $primaryModels[$i][$primaryName][$j][$name] = $primaryModel;
                        }
                    }
                }
            } else {
                foreach ($primaryModels as $i => $primaryModel) {
                    if ($primaryModels[$i][$primaryName] instanceof ActiveRecordInterface) {
                        $primaryModels[$i][$primaryName]->populateRelation($name, $primaryModel);
                    } elseif (!empty($primaryModels[$i][$primaryName])) {
                        $primaryModels[$i][$primaryName][$name] = $primaryModel;
                    }
                }
            }
        }
    }

    /**
     * @param array $models
     * @param array $link
     * @param array $viaModels
     * @param array $viaLink
     * @param boolean $checkMultiple
     * @return array
     */
    private function buildBuckets($models, $link, $viaModels = null, $viaLink = null, $checkMultiple = true)
    {
        if ($viaModels !== null) {
            $map = [];
            $viaLinkKeys = array_keys($viaLink);
            $linkValues = array_values($link);
            foreach ($viaModels as $viaModel) {
                $key1 = $this->getModelKey($viaModel, $viaLinkKeys);
                $key2 = $this->getModelKey($viaModel, $linkValues);
                $map[$key2][$key1] = true;
            }
        }

        $buckets = [];
        $linkKeys = array_keys($link);

        if (isset($map)) {
            foreach ($models as $i => $model) {
                $key = $this->getModelKey($model, $linkKeys);
                if (isset($map[$key])) {
                    foreach (array_keys($map[$key]) as $key2) {
                        $buckets[$key2][] = $model;
                    }
                }
            }
        } else {
            foreach ($models as $i => $model) {
                $key = $this->getModelKey($model, $linkKeys);
                $buckets[$key][] = $model;
            }
        }

        if ($checkMultiple && !$this->multiple) {
            foreach ($buckets as $i => $bucket) {
                $buckets[$i] = reset($bucket);
            }
        }

        return $buckets;
    }


    /**
     * Indexes buckets by column name.
     *
     * @param array $buckets
     * @var string|callable $column the name of the column by which the query results should be indexed by.
     * This can also be a callable (e.g. anonymous function) that returns the index value based on the given row data.
     * @return array
     */
    private function indexBuckets($buckets, $indexBy)
    {
        $result = [];
        foreach ($buckets as $key => $models) {
            $result[$key] = [];
            foreach ($models as $model) {
                $index = is_string($indexBy) ? $model[$indexBy] : call_user_func($indexBy, $model);
                $result[$key][$index] = $model;
            }
        }
        return $result;
    }

    /**
     * @param array $attributes the attributes to prefix
     * @return array
     */
    private function prefixKeyColumns($attributes)
    {
        if ($this instanceof ActiveQuery && (!empty($this->join) || !empty($this->joinWith))) {
            if (empty($this->from)) {
                /** @var ActiveRecord $modelClass */
                $modelClass = $this->modelClass;
                $alias = $modelClass::tableName();
            } else {
                foreach ($this->from as $alias => $table) {
                    if (!is_string($alias)) {
                        $alias = $table;
                    }
                    break;
                }
            }
            if (isset($alias)) {
                foreach ($attributes as $i => $attribute) {
                    $attributes[$i] = "$alias.$attribute";
                }
            }
        }
        return $attributes;
    }

    /**
     * @param callable $callback
     * @return $this
     */
    public function viaCallback(callable $callback)
    {
        $this->viaCallback = $callback;
        return $this;
    }

    /**
     * @param array $models
     * @throws DbException
     */
    private function filterByModels($models)
    {
        /** @var \rock\db\ActiveQuery $this */

        $attributes = array_keys($this->link);
        $attributes = $this->prefixKeyColumns($attributes);
        $values = [];
        if (count($attributes) === 1) {
            // single key
            $attribute = reset($this->link);
            foreach ($models as $model) {
                if (empty($this->link) && !isset($model[$attribute])) {
                    throw new DbException("Field '{$attribute}' not found.");
                }
                if (($value = $model[$attribute]) !== null) {
                    if (is_array($value)) {
                        $values = array_merge($values, $value);
                    } else {
                        $values[] = $value;
                    }
                }
            }
        } else {
            // composite keys
            foreach ($models as $model) {
                $v = [];
                foreach ($this->link as $attribute => $link) {
                    $v[$attribute] = $model[$link];
                }
                $values[] = $v;
            }
        }
        $values = array_unique($values, SORT_REGULAR);
        $this->andWhere(['in', $attributes, $values]);

        if (isset($this->viaCallback)/* && is_scalar(current($values))*/) {
            call_user_func($this->viaCallback, $this, $attributes, $values);
        }
    }

    /**
     * @param ActiveRecord|array $model
     * @param array $attributes
     * @return string
     */
    private function getModelKey($model, $attributes)
    {
        if (count($attributes) > 1) {
            $key = [];
            foreach ($attributes as $attribute) {
                $key[] = $model[$attribute];
            }

            return serialize($key);
        } else {
            $attribute = reset($attributes);
            $key = $model[$attribute];

            return is_scalar($key) ? $key : serialize($key);
        }
    }

    /**
     * @param array $primaryModels either array of AR instances or arrays
     * @return array
     */
    private function findPivotRows($primaryModels)
    {
        /** @var \rock\db\ActiveQuery $this */

        if (empty($primaryModels)) {
            return [];
        }
        $this->filterByModels($primaryModels);
        /** @var ActiveRecord $primaryModel */
        $primaryModel = reset($primaryModels);
        if (!$primaryModel instanceof ActiveRecordInterface) {
            // when primaryModels are array of arrays (asArray case)
            $primaryModel = new $this->modelClass;
        }

        return $this->asArray()->all($primaryModel->getConnection());
    }
}