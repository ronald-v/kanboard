<?php
namespace Kanboard\Plugin\RvSoftware\Api;
class MeProcedure extends \Kanboard\Api\Procedure\MeProcedure
{
    public function getMe()
    {
        $user=$this->userModel->removePrivateColumns($this->userSession->getAll());
        unset($user['credentials_fingerprint']);
        return $user;
    }
}
