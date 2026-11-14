<?php
namespace Kanboard\Plugin\RvSoftware\Mail;

class Smtp extends \Kanboard\Core\Mail\Transport\Smtp
{
    protected function getTransport()
    {
        if (! in_array(MAIL_SMTP_ENCRYPTION,['ssl','tls'],true)) { throw new \Swift_TransportException('SMTP requiere TLS.'); }
        $transport=parent::getTransport();
        $transport->setTimeout(5);
        $transport->setStreamOptions(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false]]);
        return $transport;
    }
}
