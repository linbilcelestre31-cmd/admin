<?php
/**
 * Protocol Handler Utility
 * Manages local overrides (quarantine, edits) for integrated external records
 */

class ProtocolHandler
{
    private static $storageFile = __DIR__ . '/protocols.json';

    private static function load()
    {
        if (!file_exists(self::$storageFile))
            return ['quarantined' => [], 'edits' => []];
        $data = json_decode(file_get_contents(self::$storageFile), true);
        return $data ?: ['quarantined' => [], 'edits' => []];
    }

    private static function save($data)
    {
        file_put_contents(self::$storageFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    public static function quarantine($sector, $id)
    {
        $data = self::load();
        $key = $sector . '_' . $id;
        if (!in_array($key, $data['quarantined'])) {
            $data['quarantined'][] = $key;
            self::save($data);
        }
        return true;
    }

    public static function restore($sector, $id)
    {
        $data = self::load();
        $key = $sector . '_' . $id;
        if (($idx = array_search($key, $data['quarantined'])) !== false) {
            unset($data['quarantined'][$idx]);
            $data['quarantined'] = array_values($data['quarantined']);
            self::save($data);
        }
        return true;
    }

    public static function isQuarantined($sector, $id)
    {
        $data = self::load();
        return in_array($sector . '_' . $id, $data['quarantined']);
    }

    public static function filter($sector, $records, $idField = 'id')
    {
        $data = self::load();
        return array_filter($records, function ($item) use ($sector, $idField, $data) {
            $id = $item[$idField] ?? null;
            return !in_array($sector . '_' . $id, $data['quarantined']);
        });
    }

    public static function filterOnlyQuarantined($sector, $records, $idField = 'id')
    {
        $data = self::load();
        return array_filter($records, function ($item) use ($sector, $idField, $data) {
            $id = $item[$idField] ?? null;
            return in_array($sector . '_' . $id, $data['quarantined']);
        });
    }

    public static function countQuarantined($sector = null)
    {
        $data = self::load();
        if ($sector) {
            return count(array_filter($data['quarantined'], function ($key) use ($sector) {
                return strpos($key, $sector . '_') === 0;
            }));
        }
        return count($data['quarantined']);
    }

    public static function getQuarantinedBySector($sector)
    {
        $data = self::load();
        return array_filter($data['quarantined'], function ($key) use ($sector) {
            return strpos($key, $sector . '_') === 0;
        });
    }
}
?>