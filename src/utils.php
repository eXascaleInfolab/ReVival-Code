<?php
namespace ReVival;


use \InvalidArgumentException;
use \stdObject;

/**
 * Utility methods.
 */
class Utils {
	/**
	 * Determines table name based time stamps range.
	 */
	static function getTableName($start, $end) {
		// set some utility variables
        $range = $end - $start;
        $start_time = gmstrftime('%Y-%m-%d %H:%M:%S', $start / 1000);
        $end_time = gmstrftime('%Y-%m-%d %H:%M:%S', $end / 1000);

        $table = 'weekly';
        // find the right table
        // up to 8 month range loads hourly data
        if ($range < 8 * 31 * 24 * 3600 * 1000) {
            $table = 'hourly';
            // up to 6 years range loads daily data
        } elseif ($range < 6 * 12 * 31 * 24 * 3600 * 1000) {
            $table = 'daily';
            // greater range loads weekls data
        }
        return $table;
    }

	static function partition($startIndex, $endIndex, $parts) {
		if ($parts > ($endIndex - $startIndex)) {
			throw new InvalidArgumentException('parts exceed index range');
		}
		if ($parts === 0) {
			return array($startIndex, $endIndex);
		}
		$shift = ($endIndex - $startIndex) / $parts;
		$result = array();
		for($i = $startIndex; $i < $endIndex;) {
			array_push($result, intval(floor($i)));
			$i += $shift;
		}
		array_push($result, $endIndex);
		return $result;
	}

}

?>