<?php

/*
 * This file is part of the DemoBundle for Kimai 2.
 * All rights reserved by Kevin Papst (www.kevinpapst.de).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\OvertimeCalcBundle\Widget;

use App\Entity\User;
use App\Repository\Query\UserQuery;
use App\Repository\Query\TimesheetQuery;
use App\Repository\UserRepository;
use App\Repository\TimesheetRepository;
use App\Widget\Type\SimpleWidget;
use App\Widget\Type\UserWidget;

use KimaiPlugin\OvertimeCalcBundle\Repository\WerkSheetRepository;
use phpDocumentor\Reflection\Types\Integer;
use phpDocumentor\Reflection\Types\Null_;

class OvertimeCalcWidget extends SimpleWidget implements UserWidget
{
    /**
     * @var UserRepository
     */
    private $userrep;
    /**
     * @var WerkSheetRepository
     */
    private $werksheetrep;

    //public function __construct(UserRepository $userrepository, TimesheetRepository $sheetrep, WerkSheetRepository $werkrep)
    public function __construct(WerkSheetRepository $werkrep, UserRepository $userrepository)
    {
        $this->userrep = $userrepository;
        //$this->sheetrepo = $sheetrep;
        $this->werksheetrep = $werkrep;

        $this->setId('OvertimeCalcWidget');
        $this->setTitle('overtime display widget');
        $this->setOptions([
            'user' => null,
            'id' => '',
        ]);
    }

    public function setUser(User $user): void
    {
        $this->setOption('user', $user);
    }

    public function getOptions(array $options = []): array
    {
        $options = parent::getOptions($options);

        if (empty($options['id'])) {
            $options['id'] = 'OvertimeCalcWidget';
        }

        return $options;
    }

    public function calculateVacationAvailable($workingweeks, $worked, $vacation)
    {
        $employee_vac_seconds = 192 * 3600; // 691200;
        //$student_avg_week_s = $worked / $workingweeks;
        $employee_work_week_s = 40 * 3600; //144000;
        
        $vacperyear = $employee_vac_seconds * ($worked / $workingweeks) / $employee_work_week_s;
        $formustring = "(employee_vacation_seconds x student_avg_week_s / employee_work_week_s) - vacation_taken_s = vacation_available_seconds";
        $calcstring = sprintf("%u x (%u / %.1f) / %u = %u seconds per year -> %.2f hours per year", $employee_vac_seconds, $worked, $workingweeks, $employee_work_week_s, $vacperyear, $vacperyear / 3600);
        $vacavail = $vacperyear - $vacation;
        $calcstring2 = sprintf("seconds_per_year - vacation_already_taken = vacation_available -> %u - %u = %u", $vacperyear, $vacation, $vacavail);
        return [$vacavail, $formustring, $calcstring, $calcstring2];
    }

    public function capVacationByMonth($workingdays, $vacavail)
    {
        if($workingdays < 180)
        {
            $vacavail = ($vacavail / 12) * floor($workingdays / 30);
        }
        return $vacavail;
    }

    public function getUserWorktimeWeek($user)
    {
        $res = 0;
        $res += $user->getPreferenceValue("daily_working_time_monday", 0);
        $res += $user->getPreferenceValue("daily_working_time_tuesday", 0);
        $res += $user->getPreferenceValue("daily_working_time_wednesday", 0);
        $res += $user->getPreferenceValue("daily_working_time_thursday", 0);
        $res += $user->getPreferenceValue("daily_working_time_friday", 0);
        $res += $user->getPreferenceValue("daily_working_time_saturday", 0);
        $res += $user->getPreferenceValue("daily_working_time_sunday", 0);
        return $res;
    }

    public function getData(array $options = [])
    {
        $options = $this->getOptions($options);
        /** @var User $user */
        $user = $options['user'];
        
        $worked_alltime_s = $this->werksheetrep->getSecondsWorked($user);
        $worked_alltime_h = round($worked_alltime_s / 3600, 2);
        $weekly_expect_h = round($this->getUserWorktimeWeek($user) / 3600, 2);
        
        $working_days = $user->getRegisteredAt()->diff(date_create('now'))->days;
        $working_years = round($working_days / 365.25, 5);
        
        $daily_expect_h = round($weekly_expect_h / 7, 5);

        $yearly_expect_h = round($daily_expect_h * 365.25, 2);
        $expected_time_h = round($yearly_expect_h * $working_years, 2);
        $time_difference_h = round($worked_alltime_h - $expected_time_h, 2);

        if($time_difference_h > 0)
        {
            $time_diff_hours = floor($time_difference_h);
            $time_diff_minutes = floor(($time_difference_h - $time_diff_hours) * 60);
        } else {
            $time_difference_h = -$time_difference_h;
            $time_diff_hours = floor($time_difference_h);
            $time_diff_minutes = floor(($time_difference_h - $time_diff_hours) * 60);
            $time_diff_hours = -$time_diff_hours;
        }

        if($time_diff_minutes < 10)
        {
            $time_diff_minutes = "0" . strval($time_diff_minutes);
        }
        

        $detailedcalc = "Bla Bla Bla";
        return [
            'time_diff_hours' => $time_diff_hours,
            'time_diff_minutes' => $time_diff_minutes,
            'worked_alltime_s' => $worked_alltime_s,
            'worked_alltime_h' => $worked_alltime_h,
            'weekly_expect_h' => $weekly_expect_h,
            'working_days' => $working_days,
            'working_years' => $working_years,
            'daily_expect_h' => $daily_expect_h,
            'yearly_expect_h' => $yearly_expect_h,
            'expected_time_h' => $expected_time_h,
            'time_difference_h' => $time_difference_h,
            'detailedcalc' => $detailedcalc
        ];
    }

    public function getTemplateName(): string
    {
        return '@OvertimeCalc/overtimewidget.html.twig';
    }
}
