<?php

namespace KimaiPlugin\OvertimeCalcBundle\Widget;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Widget\Type\SimpleWidget;
use App\Widget\Type\UserWidget;

use KimaiPlugin\OvertimeCalcBundle\Repository\WerkSheetRepository;


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
        $user = $options['user'];
        
        // get worked time and expected worked time from settings
        $worked_alltime_s = $this->werksheetrep->getSecondsWorked($user);
        $worked_alltime_h = round($worked_alltime_s / 3600, 2);
        $weekly_expect_h = round($this->getUserWorktimeWeek($user) / 3600, 2);
        $daily_expect_h = round($weekly_expect_h / 7, 5);
        
        // check the days worked to base the calculation on
        $working_days = $user->getRegisteredAt()->diff(date_create('now'))->days;
        $working_years = round($working_days / 365.25, 5);

        // finally, based on the time worked, calculate the current expectation vs actual
        // TODO this breaks if the user switches between half and full time throughout employment history
        // simple (manual) fix: create a new worker profile and create an entry on the first day containing the previous over/undertime
        $yearly_expect_h = round($daily_expect_h * 365.25, 2);
        $expected_time_h = round($yearly_expect_h * $working_years, 2);
        $time_difference_h = round($worked_alltime_h - $expected_time_h, 2);

        // split float hours to hours + minutes and keep sign
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

        // Display minutes with leading 0 if smaller than 10
        if($time_diff_minutes < 10)
        {
            $time_diff_minutes = "0" . strval($time_diff_minutes);
        }
        
        // map values to twig-widget for display to the user
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
            'time_difference_h' => $time_difference_h
        ];
    }

    public function getTemplateName(): string
    {
        return '@OvertimeCalc/overtimewidget.html.twig';
    }
}
