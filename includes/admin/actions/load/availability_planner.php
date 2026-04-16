<?php
declare(strict_types=1);

use PPStudio\Service\AdminAvailabilityReadService;

$plannerData = (new AdminAvailabilityReadService($connection))->loadPlannerData($plannerWeekOffset, $plannerDayRange);
$plannerWeekLabel = (string) ($plannerData['plannerWeekLabel'] ?? '');
$plannerDays = is_array($plannerData['plannerDays'] ?? null) ? $plannerData['plannerDays'] : [];
$plannerEditableDays = is_array($plannerData['plannerEditableDays'] ?? null) ? $plannerData['plannerEditableDays'] : [];
$plannerDayMeta = is_array($plannerData['plannerDayMeta'] ?? null) ? $plannerData['plannerDayMeta'] : [];
$plannerBookedWindows = is_array($plannerData['plannerBookedWindows'] ?? null) ? $plannerData['plannerBookedWindows'] : [];
$plannerSlots = is_array($plannerData['plannerSlots'] ?? null) ? $plannerData['plannerSlots'] : [];
$plannerInitialWindows = is_array($plannerData['plannerInitialWindows'] ?? null) ? $plannerData['plannerInitialWindows'] : [];
