<?php
namespace Kanboard\Plugin\RvSoftware\Validator;

class AuthValidator extends \Kanboard\Validator\AuthValidator
{
    public function validateForm(array $values)
    {
        try {
            $name=isset($values['username']) && is_string($values['username']) ? $values['username'] : '';
            $this->rvSecurityModel->consume('login-ip',$this->request->getIpAddress(),60,900);
            if (! is_string($values['username'] ?? null) || ! is_string($values['password'] ?? null) || strlen($values['password'])>4096 ||
                ! mb_check_encoding($name,'UTF-8') || preg_match('/[\x00-\x1F\x7F]/u',$name) || (isset($values['captcha']) && ! is_string($values['captcha']))) {
                throw new \DomainException('Invalid login input');
            }
            $this->rvSecurityModel->consume('login-account',$name,6,900);
            $result=parent::validateForm($values);
            if ($result[0]) { $this->rvSecurityModel->clear('login-account',$name); }
            if (! $result[0]) { return [false,['login'=>'No fue posible ingresar. Revisa los datos o espera antes de reintentar.']]; }
            return $result;
        } catch (\DomainException $error) {
            return [false,['login'=>'No fue posible ingresar. Revisa los datos o espera antes de reintentar.']];
        }
    }
}
