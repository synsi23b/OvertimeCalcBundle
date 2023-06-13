<?php

/*
 * This file is part of the DemoBundle for Kimai 2.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\OvertimeCalcBundle\EventSubscriber;

use App\Event\DashboardEvent;
use App\Widget\Type\CompoundRow;
use KimaiPlugin\OvertimeCalcBundle\Widget\OvertimeCalcWidget;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Security;

class DashboardSubscriber implements EventSubscriberInterface
{
    private $widget;

    /** @var AuthorizationCheckerInterface */
    private $auth;
    
    /** @var Security */
    private $security;

    public function __construct(OvertimeCalcWidget $widget, AuthorizationCheckerInterface $auth, Security $security)
    {
        $this->widget = $widget;
        $this->auth = $auth;
        $this->security = $security;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DashboardEvent::class => ['onDashboardEvent', 90],
        ];
    }

    public function onDashboardEvent(DashboardEvent $event): void
    {
        if ($this->security->isGranted('see_overtime_calculation'))
        {
            $section = new CompoundRow();
            $section->setOrder(5);
            $this->widget->setUser($event->getUser());
            $section->addWidget($this->widget);
            $event->addSection($section);
        }
    }
}
