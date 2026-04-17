<?php
declare(strict_types=1);

namespace PPStudio\Http\Controller\Admin;

final class AdminStateSubset
{
    /**
     * @param array<string, mixed> $scope
     * @param array<int, string> $allowedKeys
     * @return array<string, mixed>
     */
    public static function subset(array $scope, array $allowedKeys): array
    {
        $state = [];
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $scope)) {
                $state[$key] = $scope[$key];
            }
        }

        return $state;
    }
}
