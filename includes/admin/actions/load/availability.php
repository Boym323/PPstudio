<?php

$availabilityQuery = $connection->query('SELECT id, start_at, end_at, poznamka FROM dostupnost WHERE end_at >= NOW() ORDER BY start_at ASC LIMIT 400');
if ($availabilityQuery instanceof mysqli_result) {
    while ($row = $availabilityQuery->fetch_assoc()) {
        $availabilityRows[] = $row;
    }
    $availabilityQuery->free();
}
