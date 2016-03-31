<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
namespace Mautic\EmailBundle\EventListener;

use Mautic\DashboardBundle\DashboardEvents;
use Mautic\DashboardBundle\Event\WidgetDetailEvent;
use Mautic\DashboardBundle\EventListener\DashboardSubscriber as MainDashboardSubscriber;
use Mautic\CoreBundle\Helper\DateTimeHelper;

/**
 * Class DashboardSubscriber
 *
 * @package Mautic\EmailBundle\EventListener
 */
class DashboardSubscriber extends MainDashboardSubscriber
{
    /**
     * Define the name of the bundle/category of the widget(s)
     *
     * @var string
     */
    protected $bundle = 'email';

    /**
     * Define the widget(s)
     *
     * @var string
     */
    protected $types = array(
        'emails.in.time' => array(),
        'ignored.vs.read.emails' => array(),
        'upcoming.emails' => array(),
        'most.sent.emails' => array(),
        'most.read.emails' => array(),
        'created.emails' => array()
    );

    /**
     * Set a widget detail when needed 
     *
     * @param WidgetDetailEvent $event
     *
     * @return void
     */
    public function onWidgetDetailGenerate(WidgetDetailEvent $event)
    {
        if ($event->getType() == 'emails.in.time') {

            $widget = $event->getWidget();
            $params = $widget->getParams();

            if (!$event->isCached()) {
                $model = $this->factory->getModel('email');

                $event->setTemplateData(array(
                    'chartType'   => 'line',
                    'chartHeight' => $widget->getHeight() - 80,
                    'chartData'   => $model->getEmailsLineChartData(
                        $params['timeUnit'],
                        $params['dateFrom'],
                        $params['dateTo'],
                        $params['dateFormat'],
                        $params['filter']
                    )
                ));
            }

            $event->setTemplate('MauticCoreBundle:Helper:chart.html.php');
            $event->stopPropagation();
        }

        if ($event->getType() == 'ignored.vs.read.emails') {
            $model = $this->factory->getModel('email');
            $widget = $event->getWidget();
            $params = $widget->getParams();

            if (!$event->isCached()) {
                $event->setTemplateData(array(
                    'chartType'   => 'pie',
                    'chartHeight' => $widget->getHeight() - 80,
                    'chartData'   => $model->getIgnoredVsReadPieChartData($params['dateFrom'], $params['dateTo'])
                ));
            }

            $event->setTemplate('MauticCoreBundle:Helper:chart.html.php');
            $event->stopPropagation();
        }

        if ($event->getType() == 'upcoming.emails') {
            $widget = $event->getWidget();
            $params = $widget->getParams();
            $height = $widget->getHeight();
            $limit  = round(($height - 80) / 60);

            /** @var \Mautic\CampaignBundle\Entity\LeadEventLogRepository $leadEventLogRepository */
            $leadEventLogRepository = $this->factory->getEntityManager()->getRepository('MauticCampaignBundle:LeadEventLog');
            $upcomingEmails = $leadEventLogRepository->getUpcomingEvents(array('type' => 'email.send', 'scheduled' => 1, 'eventType' => 'action', 'limit' => $limit));

            $leadModel = $this->factory->getModel('lead.lead');

            $event->setTemplate('MauticDashboardBundle:Dashboard:upcomingemails.html.php');
            $event->setTemplateData(array('upcomingEmails' => $upcomingEmails));
            
            $event->stopPropagation();
        }

        if ($event->getType() == 'most.sent.emails') {
            if (!$event->isCached()) {
                $model  = $this->factory->getModel('email');
                $params = $event->getWidget()->getParams();

                if (empty($params['limit'])) {
                    // Count the emails limit from the widget height
                    $limit = round((($event->getWidget()->getHeight() - 80) / 35) - 1);
                } else {
                    $limit = $params['limit'];
                }

                $emails = $model->getEmailStatList($limit, $params['dateFrom'], $params['dateTo'], array(), array('groupBy' => 'sends'));
                $items = array();

                // Build table rows with links
                if ($emails) {
                    foreach ($emails as &$email) {
                        $emailUrl = $this->factory->getRouter()->generate('mautic_email_action', array('objectAction' => 'view', 'objectId' => $email['id']));
                        $row = array(
                            array(
                                'value' => $email['name'],
                                'type' => 'link',
                                'link' => $emailUrl
                            ),
                            array(
                                'value' => $email['count']
                            )
                        );
                        $items[] = $row;
                    }
                }

                $event->setTemplateData(array(
                    'headItems'   => array(
                        $event->getTranslator()->trans('mautic.dashboard.label.title'),
                        $event->getTranslator()->trans('mautic.email.label.sends')
                    ),
                    'bodyItems'   => $items,
                    'raw'         => $emails
                ));
            }
            
            $event->setTemplate('MauticCoreBundle:Helper:table.html.php');
            $event->stopPropagation();
        }

        if ($event->getType() == 'most.read.emails') {
            if (!$event->isCached()) {
                $model  = $this->factory->getModel('email');
                $params = $event->getWidget()->getParams();

                if (empty($params['limit'])) {
                    // Count the emails limit from the widget height
                    $limit = round((($event->getWidget()->getHeight() - 80) / 35) - 1);
                } else {
                    $limit = $params['limit'];
                }

                $emails = $model->getEmailStatList($limit, $params['dateFrom'], $params['dateTo'], array(), array('groupBy' => 'reads'));
                $items = array();

                // Build table rows with links
                if ($emails) {
                    foreach ($emails as &$email) {
                        $emailUrl = $this->factory->getRouter()->generate('mautic_email_action', array('objectAction' => 'view', 'objectId' => $email['id']));
                        $row = array(
                            array(
                                'value' => $email['name'],
                                'type' => 'link',
                                'link' => $emailUrl
                            ),
                            array(
                                'value' => $email['count']
                            )
                        );
                        $items[] = $row;
                    }
                }

                $event->setTemplateData(array(
                    'headItems'   => array(
                        $event->getTranslator()->trans('mautic.dashboard.label.title'),
                        $event->getTranslator()->trans('mautic.email.label.reads')
                    ),
                    'bodyItems'   => $items,
                    'raw'         => $emails
                ));
            }
            
            $event->setTemplate('MauticCoreBundle:Helper:table.html.php');
            $event->stopPropagation();
        }

        if ($event->getType() == 'created.emails') {
            if (!$event->isCached()) {
                $model  = $this->factory->getModel('email');
                $params = $event->getWidget()->getParams();

                if (empty($params['limit'])) {
                    // Count the emails limit from the widget height
                    $limit = round((($event->getWidget()->getHeight() - 80) / 35) - 1);
                } else {
                    $limit = $params['limit'];
                }

                $emails = $model->getEmailList($limit, $params['dateFrom'], $params['dateTo'], array(), array('groupBy' => 'creations'));
                $items = array();

                // Build table rows with links
                if ($emails) {
                    foreach ($emails as &$email) {
                        $emailUrl = $this->factory->getRouter()->generate(
                            'mautic_email_action', 
                            array(
                                'objectAction' => 'view',
                                'objectId' => $email['id']
                            )
                        );
                        $row = array(
                            array(
                                'value' => $email['name'],
                                'type' => 'link',
                                'link' => $emailUrl
                            )
                        );
                        $items[] = $row;
                    }
                }

                $event->setTemplateData(array(
                    'headItems'   => array(
                        $event->getTranslator()->trans('mautic.dashboard.label.title')
                    ),
                    'bodyItems'   => $items,
                    'raw'         => $emails
                ));
            }
            
            $event->setTemplate('MauticCoreBundle:Helper:table.html.php');
            $event->stopPropagation();
        }
    }
}
