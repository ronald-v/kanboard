<?php
namespace Kanboard\Plugin\RvSoftware\Model;

class RvLanguageModel extends \Kanboard\Model\LanguageModel
{
    public function getCurrentLanguage() { return 'es_ES'; }
    public function getLanguages($prepend = false) { return ['es_ES'=>'Español']; }
}
