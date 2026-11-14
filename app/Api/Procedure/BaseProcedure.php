<?php

namespace Kanboard\Api\Procedure;

use Kanboard\Api\Authorization\ProcedureAuthorization;
use Kanboard\Api\Authorization\UserAuthorization;
use Kanboard\Core\Base;
use ReflectionClass;

/**
 * Base class
 *
 * @package  Kanboard\Api\Procedure
 * @author   Frederic Guillot
 */
abstract class BaseProcedure extends Base
{
    public function beforeProcedure($procedure)
    {
        if (isset($this->container['rvApiModel'])) {
            $method=new \ReflectionMethod($this,$procedure);
            if (! $method->isPublic() || in_array($method->getDeclaringClass()->getName(),[self::class,\Kanboard\Core\Base::class],true)) {
                throw new \JsonRPC\Exception\AccessDeniedException('Method is not an API procedure');
            }
            $this->rvApiModel->authorizeMethod($procedure);
        }
        ProcedureAuthorization::getInstance($this->container)->check($procedure);
        UserAuthorization::getInstance($this->container)->check($this->getClassName(), $procedure);
    }

    protected function filterValues(array $values)
    {
        foreach ($values as $key => $value) {
            if (is_null($value)) {
                unset($values[$key]);
            }
        }

        return $values;
    }

    protected function filterUser($user)
    {
        return is_array($user) ? $this->userModel->removePrivateColumns($user) : $user;
    }

    protected function filterUsers($users)
    {
        return is_array($users) ? array_map(array($this, 'filterUser'), $users) : $users;
    }

    protected function getClassName()
    {
        $reflection = new ReflectionClass(get_called_class());
        return $reflection->getShortName();
    }
}
