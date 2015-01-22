<?php

namespace rockunit\core\rbac;

use rock\rbac\DBManager;
use rock\rbac\RBAC;

/**
 * @group rbac
 * @group db
 */
class RBACWithDbTest extends RBACTest
{
    /**
     * @return RBAC
     */
    protected function getRBAC()
    {
        $rbac = new DBManager(['connection' => $this->getConnection()]);
        //$rbac->refresh();
        return $rbac;
    }
}
 