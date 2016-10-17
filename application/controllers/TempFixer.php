<?php defined("BASEPATH") OR exit("No direct script access allowed");


/**
 * Class TempFixer
 *
 * List of Errors:
 *    "ERRORS_COUNT_TODAY":     number of errors on that day
 *    "MISSING_DATA":           no temp data
 *    "TOO_HOT":                tempf > 108(F)
 *    "TOO_COLD":               temp < -20(F), OR tempf < 36(F) between Jun 15 and Aug 9
 *    "TOO_WARM_AT_NIGHT":      between 10pm and 10 am
 *    "TOO_HOT_EARLY":          early in the season (temp > 95(F) before May 15)
 *    "TOO_HOT_LATE":           late in the season (temp > 95(F) after Sep 17)
 *    "NO_CHANGE":              no variation for 3 consecutive hours
 *    "TOO_SMALL_VARIATION":    too small variation between 9:00am to 5:00pm from Apr15 to Sep1
 *    "TOO_MUCH_VARIATION":     too much change in temp in 60min
 *
 */


class TempFixer extends CI_Controller {

    private $inputTable = [];
    private $outputTable = [];


    public function __construct($allowed_error = 9.9) {
        parent::__construct();

        define("ALLOWED_ERROR", $allowed_error);

//        $this->inputTable = $this->getInputTable("./TempData/kelowna2010_input.tsv", "Kelowna Airport", "49.02999878", "-119.4649963");
//        $this->inputTable = $this->getInputTable("./TempData/kelowna2010_input.tsv");

    }


    public function index() {

        // todo: move it here with params
        // $this->inputTable = $this->getInputTable("./TempData/kelowna2010_input.tsv", "Kelowna Airport", "49.02999878", "-119.4649963");

        $this->scanData("./TempData/kelowna2010_input.tsv", 3, '2010-02-21');

//        var_dump($this->outputTable['2010-12-31']);
        var_dump($this->outputTable);
//        echo JSON_encode($this->outputTable);

    }

    /**
     * // todo: check temp if F or C or both
     * Creates an array containing the data from the specified file, stored in $this->inputTable
     * @param $fileLocation - string: path to file
     * @return array - Example: [
     *                    'station' => 'Penticton Airport'
     *                    'latitude' => '49.47000122'
     *                    'longitude' => '-119.6050034'
     *                    'year' => '2010'
     *                    '2010-01-01' => [
     *                        '00' => [
     *                            'tempf' => 'Penticton Airport'
     *                            'tempc' => 'Penticton Airport'
     *                            'dwpt' => '-1.700000048'
     *                            'rh' => '86'
     *                            'wdir' => '18'
     *                            'wspd' => '24'
     *                            'stnpresskpa' => '97.41000366'
     *                        ],
     *                        '01' => [ ... ],
     *                         ...,
     *                        '23' => [ ... ],
     *                    ],
     *                    ...
     *                    '2010-12-31' => [ ... ]
     *                 ];
     */
    public function getInputTable($fileLocation, $startDate = null, $station = null, $lat = null, $lng = null) {
        $fileLines = file($fileLocation, FILE_IGNORE_NEW_LINES);
        // convert each (string) line into array
        foreach ($fileLines as &$line) {
            $line = explode("\t", $line);
        }

        // return position of the column
        function position($name, $fileLines) {
            return array_search($name, $fileLines[0]);
        }

        if (isset($startDate)) {
            $year = date('Y', strtotime($startDate));
        } else {
            $year = $fileLines[1][position('year', $fileLines)];
        }

        // create default array with station info
        // todo: change header reference for the correct file
        $inputTempTable = [
            'station' => ($station) ? $station : $fileLines[1][position('loc', $fileLines)],
            'latitude' => ($lat) ? $lat : $fileLines[1][position('latitude', $fileLines)],
            'longitude' => ($lng) ? $lng : $fileLines[1][position('longitude', $fileLines)],
            'year' => $year
        ];

//        $inputTempTable = [
//            'station' => ($station) ? $station : $fileLines[1][position('station', $fileLines)],
//            'latitude' => ($lat) ? $lat : $fileLines[1][position('latitude', $fileLines)],
//            'longitude' => ($lng) ? $lng : $fileLines[1][position('longitude', $fileLines)],
//            'year' => $fileLines[1][position('Year', $fileLines)]
//        ];

        // fill input table array
        for ($l = 1; $l < count($fileLines); $l++) {

            $line = $fileLines[$l];

            // create date array
            $date = date('Y-m-d', strtotime($line[position('date', $fileLines)]));
            if (!isset($inputTempTable[$date])) {
                $inputTempTable[$date] = [];
            }
            // create time array
            $time = date('H', strtotime($line[position('time', $fileLines)]));
            if (!isset($inputTempTable[$date][$time])) {
                $inputTempTable[$date][$time] = [];
            }

            // create hourly data
            // todo: change header reference for the correct file
            //            $datarow = [
//                'tempc' => $line[position('Temp (Â°C)', $fileLines)],
//                'dwpt' => $line[position('Dew Point Temp (Â°C)', $fileLines)],
//                'rh' => $line[position('Rel Hum (%)', $fileLines)],
//                'wdir' => $line[position('Wind Dir (10s deg)', $fileLines)],
//                'wspd' => $line[position('Wind Spd (km/h)', $fileLines)],
//                'stnpresskpa' => $line[position('Stn Press (kPa)', $fileLines)]
//                ];
            // todo: improve value check
            // if the data is missin gset the array value to null
            $datarow = [
                'tempc' => (position('tempc', $fileLines) < count($line))
                    ? $line[position('tempc', $fileLines)]
                    : null,
                'dwpt' => (position('dwpt', $fileLines) < count($line))
                    ? $line[position('dwpt', $fileLines)]
                    : null,
                'rh' => (position('rh', $fileLines) < count($line))
                    ? $line[position('rh', $fileLines)]
                    : null,
                'wdir' => (position('wdir', $fileLines) < count($line))
                    ? $line[position('wdir', $fileLines)]
                    : null,
                'wspd' => (position('wspd', $fileLines) < count($line))
                    ? $line[position('wspd', $fileLines)]
                    : null,
                'stnpresskpa' => (position('stnpresskpa', $fileLines) < count($line))
                    ? $line[position('stnpresskpa', $fileLines)]
                    : null
            ];
            // load data to time row
            $inputTempTable[$date][$time] = $datarow;
        }

//        var_dump($inputTempTable);

        return $inputTempTable;
    }


    /**
     * Creates an array containing the final output, stored into $this->outputTable
     * todo: move ERRORS_COUNT_TODAY up to day's data
     * @return array - Example: [
     *                    'station' => 'Penticton Airport'
     *                    'latitude' => '49.47000122'
     *                    'longitude' => '-119.6050034'
     *                    'year' => '2010'
     *                    '2010-01-01' => [
     *                        '00' => [
     *                            'tempf' => 'Penticton Airport'
     *                            'tempc' => 'Penticton Airport'
     *                            'dwpt' => '-1.700000048'
     *                            'rh' => '86'
     *                            'wdir' => '18'
     *                            'wspd' => '24'
     *                            'stnpresskpa' => '97.41000366'
     *                            'ERRORS_COUNT_TODAY' => null
     *                            'MISSING_DATA' => null
     *                            'TOO_HOT' => null
     *                            'TOO_COLD' => TRUE
     *                            'TOO_WARM_AT_NIGHT' => null
     *                            'TOO_HOT_EARLY' => null
     *                            'TOO_HOT_LATE' => null
     *                            'NO_CHANGE' => null
     *                            'TOO_SMALL_VARIATION' => TRUE
     *                            'TOO_MUCH_VARIATION' => null
     *                            'Del-1' => 0.654
     *                            'Del+1' => 0.156
     *                            'Del+2' => 0.61234
     *                            'Del+3' => 0.367
     *                        ],
     *                        '01' => [ ... ],
     *                         ...,
     *                        '23' => [ ... ],
     *                    ],
     *                    ...
     *                    '2010-12-31' => [ ... ]
     *                 ];
     */
    public function scanData($fileLocation, $numDays = 365, $startDate) {

        if (empty($this->inputTable)) {
            $this->inputTable = $this->getInputTable($fileLocation, $startDate);
        }

        $year = date('Y', strtotime($startDate));
        $startDay = date('z', strtotime($startDate)) + 1;

        $inputTable = $this->inputTable;

        $outputTable = [
            'station' => $inputTable['station'],
            'latitude' => $inputTable['latitude'],
            'longitude' => $inputTable['longitude'],
            'year' => $inputTable['year']
        ];


        for ($d = $startDay; $d <= $startDay + $numDays; $d++) {

            $date = $this->dayofyear2date($d, $year); // 3 --> 2010-01-03

            $outputTable[$date] = [];

            // if the date is missing just create a full day of empty values and set MISSING_DATA error
            if (!isset($inputTable[$date])) {
                for ($h = 0; $h < 24; $h++) {
                    $time = (strlen($h) == 2) ? $h : '0'.$h; // double digit time
                    $outputTable[$date][$time] = $this->missingHourlyData();
                }
                continue;
            }

            $dailyData = [];
            for ($h = 0; $h < 24; $h++) {
                $time = (strlen($h) == 2) ? $h : '0' . $h; // double digit time
                $dailyData[$time] = $this->checkHourlyData($d, $time, $date);
            }
            $outputTable[$date] = $dailyData;
        }
        $this->outputTable = $outputTable;


        //$this->completeErrorChecks($numDays, $startDate);
        for ($d = $startDay; $d <= $startDay + $numDays; $d++) {

            $date = $this->dayofyear2date($d, $year);

            $errorCount = 0;
            for ($h = 0; $h < 24; $h++) {
                $time = (strlen($h) == 2) ? $h : '0' . $h; // always double digit

                /* ****************
                 * set deltas
                 * ****************/
                $this->setDelta('-1', $date, $time);
                $this->setDelta('+1', $date, $time);
                $this->setDelta('+2', $date, $time);
                $this->setDelta('+3', $date, $time);

                $this->checkRelativeErrors($d, $h, $date, $time);

                $errorCount = $errorCount + $this->countErrors($date, $time);
            }

            $this->outputTable[$date]["ERRORS_COUNT_TODAY"] = $errorCount;
        }


    }


    private function checkHourlyData($d, $time, $date) {
        $inputTable = $this->inputTable;
        // if hour values are missing set empty line and set MISSING_DATA error
        if (!isset($inputTable[$date][$time])) {
            return $this->missingHourlyData();
        } else {
            $tempc = $inputTable[$date][$time]['tempc'];

            $datarow = [
                    'tempc' => $tempc,
                    'dwpt' => $inputTable[$date][$time]['dwpt'],
                    'rh' => $inputTable[$date][$time]['rh'],
                    'wdir' => $inputTable[$date][$time]['wdir'],
                    'wspd' => $inputTable[$date][$time]['wspd'],
                    'stnpresskpa' => $inputTable[$date][$time]['stnpresskpa']
                ] + $this->noErrors();

            if (!is_null($tempc)) {
                $tempf = $tempc * 1.8 + 32;
                $datarow['tempf'] = $tempf;
            } else {
                // if temp is missing flag missing data
                $datarow['tempf'] = null;
                $datarow['MISSING_DATA'] = TRUE;
            }

            return $this->checkAbsoluteErrors($datarow, $d, $time);
        }

    }

    /**
     * Checks and sets TOO_HOT, TOO_WARM_AT_NIGHT, TOO_COLD, TOO_HOT_EARLY, and TOO_HOT_LATE errors
     * for the (hourly) datarow passed
     * @param $datarow
     * @param $dayOfYear
     * @param $time
     * @return $datarow with errors, if any found
     */
    private function checkAbsoluteErrors($datarow, $dayOfYear, $time) {
        $temp = $datarow['tempf'];
        if ($temp > 108) {
            $datarow["TOO_HOT"] = TRUE;
        }
        if ($temp > 95 && ($time > 22 || $time < 10)) {
            $datarow["TOO_WARM_AT_NIGHT"] = TRUE;
        }
        if ($temp < 36 && $this->momentBetween($dayOfYear, 165, 220) || // between Jun 15 and Aug 9
            $temp < -20 ) {
            $datarow["TOO_COLD"] = TRUE;
        }
        if ($temp > 95 && $dayOfYear < 134) { // before May 15
            $datarow["TOO_HOT_EARLY"] = TRUE;
        }
        if ($temp > 95 && $dayOfYear > 259) { // after Sep 17
            $datarow["TOO_HOT_LATE"] = TRUE;
        }

        return $datarow;
    }


    /**
     * todo: modify doc here
     * Set Del-1, Del+1, Del+2, Del+3 into $this->outputTable,
     * i.e. absolute temperature variation from previous hour or next 1, 2, 3, or 4 hours
     */
    private function completeErrorChecks() {


        for ($d = 1; $d < 366; $d++) {

            $date = $this->dayofyear2date($d, 2010);

            $errorCount = 0;
            for ($h = 0; $h < 24; $h++) {
                $time = (strlen($h) == 2) ? $h : '0' . $h; // always double digit

                /* ****************
                 * set deltas
                 * ****************/
                $this->setDelta('-1', $date, $time);
                $this->setDelta('+1', $date, $time);
                $this->setDelta('+2', $date, $time);
                $this->setDelta('+3', $date, $time);
//                $this->setDelta('+4', $date, $time); // todo: check if this is actually used

                $this->checkRelativeErrors($d, $h, $date, $time);

                $errorCount = $errorCount + $this->countErrors($date, $time);
            }

            $this->outputTable[$date]["ERRORS_COUNT_TODAY"] = $errorCount;
        }

    }



    private function checkRelativeErrors($d, $h, $date, $time) {



        $currentRowData = $this->outputTable[$date][$time];
        if (!is_null($currentRowData['Del+1']) && !is_null($currentRowData['Del+2']) &&
            !is_null($currentRowData['Del+3']) // && !is_null($currentRowData['tempf'])
        ) {

            if ($currentRowData['Del+1'] == 0 &&
                $currentRowData['Del+2'] == 0 &&
                $currentRowData['Del+3'] == 0
            ) {
                $this->outputTable[$date][$time]['NO_CHANGE'] = TRUE;
            }

            if ($currentRowData['Del+1'] < 0.3 && // too small variations
                $currentRowData['Del+2'] < 1 &&
                $currentRowData['Del+3'] < 1 &&
                // between Apr 16 and Sep 1, from 9am to 5pm
                ($this->momentBetween($d, 105, 243) && $this->momentBetween($h, 9, 17))
            ) {
                $this->outputTable[$date][$time]['TOO_SMALL_VARIATION'] = TRUE;
            }

        }


        if (!is_null($currentRowData['Del-1']) && $currentRowData['Del-1'] > ALLOWED_ERROR) {
            $this->outputTable[$date][$time]['TOO_MUCH_VARIATION'] = TRUE;
        }

        /* *********************
         * now avoid marking two consecutive errors for a single spiking errors
         * (delta has absolute value --> A-B = B-C)
         *            B
         *            .
         *           / \
         *          /   \
         *      __./     \.___
         *        A       C
         * *********************/

        $dateTime = $date . "T" . $time . ":00"; // "2010-01-01T00:00"

        // get previous hour reference
        $t1 = date('G', strtotime($dateTime . " -1 hour")); // previous hour
        // previous/next hour might be in a different day
        $diffT1 = (strlen($t1) == 2) ? $t1 : '0' . $t1; // always with two characters
        $diffD1 = date('Y-m-d', strtotime($dateTime . " -1 hour"));

        // get 2 hours ago reference
        $t2 = date('G', strtotime($dateTime . " -2 hour")); // 2 hours ago
        $diffT2 = (strlen($t2) == 2) ? $t2 : '0' . $t2; // two characters
        $diffD2 = date('Y-m-d', strtotime($dateTime . " -2 hour"));

        if (isset($this->outputTable[$diffD1][$diffT1]) && isset($this->outputTable[$diffD2][$diffT2])) {

            $prev1RowData = $this->outputTable[$diffD1][$diffT1]; // prev hour data
            $prev2RowData = $this->outputTable[$diffD2][$diffT2]; // 2 hours ago data
            if (//if prev hour data has TOO_MUCH_VARIATION
                $prev1RowData['TOO_MUCH_VARIATION'] == TRUE &&
                // but current value is actually within ALLOWED_ERROR from two hous ago
                $prev2RowData['Del+2'] < ALLOWED_ERROR
            ) {
                // clear error for current data
                $this->outputTable[$date][$time]['TOO_MUCH_VARIATION'] = null;
            }
        }

    }

    private function countErrors($date, $time) {
        // todo: create array of errors so num errors is its length

        $currentRowData = $this->outputTable[$date][$time];
        $errorCount = 0;
        if ($currentRowData["TOO_HOT"] == TRUE) {
            $errorCount++;
        }
        if ($currentRowData["TOO_COLD"] == TRUE) {
            $errorCount++;
        }
        if ($currentRowData["TOO_WARM_AT_NIGHT"] == TRUE) {
            $errorCount++;
        }
        if ($currentRowData["TOO_HOT_EARLY"] == TRUE) {
            $errorCount++;
        }
        if ($currentRowData["TOO_HOT_LATE"] == TRUE) {
            $errorCount++;
        }
        if ($currentRowData["NO_CHANGE"] == TRUE) {
            $errorCount++;
        }
        if ($currentRowData["TOO_SMALL_VARIATION"] == TRUE) {
            $errorCount++;
        }
        if ($currentRowData["TOO_MUCH_VARIATION"] == TRUE) {
            $errorCount++;
        }

        return $errorCount;

    }

    /**
     * Calculate and set the temperature variation between current temp value and $diff hour(s)
     * @param $diff - time difference
     * @param $date - date of the current measure
     * @param $time - time of the current measure
     */
    private function setDelta($diff, $date, $time) {
        $dateTime = $date . "T" . $time . ":00"; // "2010-01-01T00:00"
        $t = date('G', strtotime($dateTime . " " . $diff . "hour")); // previous/next hour
        $diffT = (strlen($t) == 2) ? $t : '0' . $t; // differential time always with two characters ("01" or "15")
        // previous/next hour might be in a different day
        $diffD = date('Y-m-d', strtotime($dateTime . " " . $diff . "hour"));

        $this->outputTable[$date][$time]['Del' . $diff] = null;
        // if differential previous/next temp data is available set Delta
        if (isset($this->outputTable[$diffD][$diffT]['tempc']) && isset($this->outputTable[$date][$time]['tempc'])) {
            $diffDataRow = $this->outputTable[$diffD][$diffT];
            $this->outputTable[$date][$time]['Del' . $diff] = abs($this->outputTable[$date][$time]['tempf'] - $diffDataRow['tempf']);
        }

    }


    #####################################
    # UTILITY FUNCTIONS
    #####################################
    /**
     * returns a date string correspondent to the day of the (optional year:
     * @param $day - int between 1 and 365
     * @param string $format - date format (optional); default "Y-m-d"
     * @param string|int $year - date year (optional); default current year
     *
     * @return false|string
     *
     * example:
     *    dayofyear2date(2) -> "2016-01-02"
     */
    private function dayofyear2date($day, $year = null, $format = "Y-m-d") {
        if (gettype($day) == "string" || $day > 365 || $day < 0) {
            return false;
        }

        $offset = intval(intval($day) * 24 * 60 * 60) - 1; // adjust 0 counting
        $year = (!is_null($year))
            ? $year
            : date("Y");
        $str = date($format, strtotime("Jan 1, " . $year) + $offset);

        return ($str);

    }

    // util function, checks if time or day is between limits
    private function momentBetween($moment, $bottom, $top) {
        return ($bottom <= $moment && $moment <= $top);
    }

    // util function, returns empty line
    private function missingHourlyData() {
        return [
            'tempc' => null,
            'tempf' => null,
            'dwpt' => null,
            'rh' => null,
            'wdir' => null,
            'wspd' => null,
            'stnpresskpa' => null,
            "MISSING_DATA" => TRUE, // flagged
            "TOO_HOT" => null,
            "TOO_COLD" => null,
            "TOO_WARM_AT_NIGHT" => null,
            "TOO_HOT_EARLY" => null,
            "TOO_HOT_LATE" => null,
            "NO_CHANGE" => null,
            "TOO_SMALL_VARIATION" => null,
            "TOO_MUCH_VARIATION" => null
        ];
    }

    // util function, returns clean error list
    private function noErrors() {
        return [
            "MISSING_DATA" => null,
            "TOO_HOT" => null,
            "TOO_COLD" => null,
            "TOO_WARM_AT_NIGHT" => null,
            "TOO_HOT_EARLY" => null,
            "TOO_HOT_LATE" => null,
            "NO_CHANGE" => null,
            "TOO_SMALL_VARIATION" => null,
            "TOO_MUCH_VARIATION" => null
        ];
    }

}