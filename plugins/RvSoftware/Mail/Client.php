<?php
namespace Kanboard\Plugin\RvSoftware\Mail;

class Client extends \Kanboard\Core\Mail\Client
{
    public function ready()
    {
        return MAIL_TRANSPORT==='smtp' && MAIL_SMTP_HOSTNAME!=='' && filter_var(MAIL_FROM,FILTER_VALIDATE_EMAIL) &&
            in_array(MAIL_SMTP_ENCRYPTION,['tls','ssl'],true);
    }

    public function send($recipientEmail, $recipientName, $subject, $html, $authorName = null, $authorEmail = null)
    {
        if (! $this->ready()) { return $this; }
        return parent::send($recipientEmail,$recipientName,$subject,$html,$authorName,$authorEmail);
    }

    public function getAuthorName()
    {
        return $this->userSession->isLogged() ? $this->helper->user->getFullname().' · Projecta' : 'Projecta';
    }
}
