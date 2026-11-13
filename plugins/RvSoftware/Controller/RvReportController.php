<?php
namespace Kanboard\Plugin\RvSoftware\Controller;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Kanboard\Controller\BaseController;

class RvReportController extends BaseController
{
    public function show()
    {
        $today = new DateTimeImmutable('now',new DateTimeZone('America/Bogota'));
        $values = array('from'=>$this->request->getStringParam('from',$today->format('Y-m-01')),'until'=>$this->request->getStringParam('until',$today->format('Y-m-d')));
        $choices = $this->rvReportModel->options();
        foreach (array('project_id','user_id','status','billable','bag_id','client_id') as $filter) { $values[$filter] = $this->request->getStringParam($filter); }
        try {
            $report = $this->rvReportModel->report($values['from'],$values['until'],$values);
            if ($this->request->getStringParam('format') === 'csv') {
                $this->response->withFileDownload('horas-'.$values['from'].'-'.$values['until'].'.csv');
                $this->response->csv($this->rvReportModel->csv($report)); return;
            }
            $this->response->html($this->helper->layout->app('RvSoftware:report/index',array('title'=>'Reporte de horas','values'=>$values,'choices'=>$choices,'report'=>$report,'error'=>'')));
        } catch (DomainException $e) {
            $this->response->withStatusCode(400);
            $this->response->html($this->helper->layout->app('RvSoftware:report/index',array('title'=>'Reporte de horas','values'=>$values,'choices'=>$choices,'report'=>null,'error'=>$e->getMessage())),400);
        }
    }
}
