<?php
declare(strict_types=1);

use PPStudio\Service\AdminAvailabilityReadService;

$availabilityRows = (new AdminAvailabilityReadService($connection))->loadAvailabilityRows();
