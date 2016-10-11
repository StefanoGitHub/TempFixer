<?php defined("BASEPATH") OR exit("No direct script access allowed");

class TempFixer extends CI_Controller {


    private $inputTable = [];
    private $outputTable = [];


    public function __construct() {
        parent::__construct();

        $this->inputTable = $this->getInputTable();

//        define("ERRORS_COUNT_TODAY", 0);
//        define("MISSING_DATA", 1);
//        define("TOO_HOT", 2);
//        define("TOO_COLD", 8);
//        define("TOO_WARM_AT_NIGHT", 3);
//        define("TOO_HOT_EARLY", 4); // early in the season
//        define("TOO_HOT_LATE", 5); // late in the season
//        define("NO_CHANGE", 6); // no variation for three consecutive hours
//        define("TOO_SMALL_VARIATION", 7); // too small variation between 9:00am to 5:00pm from Apr15 to Sep1
//        define("TOO_MUCH_VARIATION", 9); // too much change in temp in 60min

        // define file data header, i.e. the colums names
//        define("station", 0);
//        define("latitude", 1);
//        define("longitude", 2);
//        define("year", 3);
//        define("date", 4);
//        define("time", 5);
//        define("temp", 6);
//        define("dwpt", 7);
//        define("rh", 8);
//        define("wdir", 9);
//        define("wspd", 10);
        define("ALLOWED_ERROR", 9.9);

//        $this->createOutputTable();

    }


    public function index() {

        $this->createOutputTable();
        $this->calculateDeltas();
        $this->checkRelativeErrors();

        var_dump($this->outputTable['2010-12-31']);
//        $this->sanitizeData();
//        $this->setMaxMin();

    }


//    public function getInputTable() {
//        $fileLines = file("./TempData/2010input.tsv", FILE_IGNORE_NEW_LINES);
//
//        foreach ($fileLines as &$line) {
//            $line = explode("\t", $line);
//        }
//
//        $inputTempTable = [];
//        $columns = $fileLines[0];
//        $row = [];
//        for ($line = 1; $line < count($fileLines); $line++) {
//            for ($c = 0; $c < count($columns); $c++) {
//                $row[$columns[$c]] = $fileLines[$line][$c];
//            }
//            $row = array_merge($row, [
//                    "ERRORS_COUNT_TODAY" => FALSE,
//                    "MISSING_DATA" => FALSE,
//                    "TOO_HOT" => FALSE,
//                    "TOO_COLD" => FALSE,
//                    "TOO_WARM_AT_NIGHT" => FALSE,
//                    "TOO_HOT_EARLY" => FALSE,
//                    "TOO_HOT_LATE" => FALSE,
//                    "NO_CHANGE" => FALSE,
//                    "TOO_SMALL_VARIATION" => FALSE,
//                    "TOO_MUCH_VARIATION" => FALSE
//                ]);
//            $inputTempTable[] = $row;
//        }
//
//        var_dump($inputTempTable);
//
//        return $inputTempTable;
//    }
    public function getInputTable() {
        $fileLines = file("./TempData/2010input.tsv", FILE_IGNORE_NEW_LINES);
        // convert each (string) line into array
        foreach ($fileLines as &$line) {
            $line = explode("\t", $line);
        }

        // return position of the column
        function position($name, $fileLines) {
            return array_search($name, $fileLines[0]);
        }

        // create default array with station info
        $inputTempTable = [
            'station' => $fileLines[1][position('station', $fileLines)],
            'latitude' => $fileLines[1][position('latitude', $fileLines)],
            'longitude' => $fileLines[1][position('longitude', $fileLines)]
        ];

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
            $datarow = [
                'temp' => $line[position('temp', $fileLines)],
                'dwpt' => $line[position('dwpt', $fileLines)],
                'rh' => $line[position('rh', $fileLines)],
                'wdir' => $line[position('wdir', $fileLines)],
                'wspd' => $line[position('wspd', $fileLines)],
                'stnpresskpa' => $line[position('stnpresskpa', $fileLines)]
                ];
            // load data to time row
            $inputTempTable[$date][$time] = $datarow;
        }

//        var_dump($inputTempTable);

        return $inputTempTable;
    }



    public function createOutputTable() {

        $inputTable = $this->inputTable;

        $outputTable = [
            'station' => $inputTable['station'],
            'latitude' => $inputTable['latitude'],
            'longitude' => $inputTable['longitude']
        ];

        function missingHourlyData() {
            return [
                'temp' => null,
                'dwpt' => null,
                'rh' => null,
                'wdir' => null,
                'wspd' => null,
                'stnpresskpa' => null,
                "ERRORS_COUNT_TODAY" => null,
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

        function noErrors() {
            return [
                "ERRORS_COUNT_TODAY" => null,
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

        for ($d = 1; $d < 366; $d++) {

            $date = $this->dayofyear2date($d, 2010);

            $outputTable[$date] = [];

            if (!isset($inputTable[$date])) {
                for ($h = 0; $h < 24; $h++) {
                    $time = (strlen($h) == 2)
                        ? $h
                        : '0'.$h;
                    $outputTable[$date][$time] = missingHourlyData();
                }
                continue;
            }

            $dailyData = [];
            for ($h = 0; $h < 24; $h++) {
                $time = (strlen($h) == 2)
                    ? $h
                    : '0' . $h;
//                $datarow = (isset($inputTable[$date][$time]))
//                    ? [
//                        'temp' => $inputTable[$date][$time]['temp'],
//                        'dwpt' => $inputTable[$date][$time]['dwpt'],
//                        'rh' => $inputTable[$date][$time]['rh'],
//                        'wdir' => $inputTable[$date][$time]['wdir'],
//                        'wspd' => $inputTable[$date][$time]['wspd'],
//                        'stnpresskpa' => $inputTable[$date][$time]['stnpresskpa']
//                    ] + noErrors()
//                    : missingHourlyData();

                if (!isset($inputTable[$date][$time])) {
                    $datarow = missingHourlyData();
                    $dailyData[$time] = $datarow;
                    continue;
                } else {

                    // todo: convert C to F

                    $datarow = [
                        'temp' => $inputTable[$date][$time]['temp'],
                        'dwpt' => $inputTable[$date][$time]['dwpt'],
                        'rh' => $inputTable[$date][$time]['rh'],
                        'wdir' => $inputTable[$date][$time]['wdir'],
                        'wspd' => $inputTable[$date][$time]['wspd'],
                        'stnpresskpa' => $inputTable[$date][$time]['stnpresskpa']
                    ] + noErrors();



//                    $dailyData[$time] = $datarow;
                    $dailyData[$time] = $this->checkAbsoluteErrors($datarow, $d, $time);;
                }
            }
            $outputTable[$date] = $dailyData;
        }


/*        // todo: check for relative errors
        // if available pass data before and after
        // $this->checkAbsoluteErrors($datarow, $dayBeforeData, $dayAfterData);
        for ($d = 1; $d < 366; $d++) {
            $dayBefore = $this->dayofyear2date($d - 1, 2010);
            $date = $this->dayofyear2date($d, 2010);
            $dayAfter = $this->dayofyear2date($d + 1, 2010);


            $dataDayBefore = [];
//            if (date("Y", $dayBefore)) {
//                $dataDayBefore = [
//                    '21' => $outputTable[$dayBefore]['21'],
//                    '22' => $outputTable[$dayBefore]['22'],
//                    '23' => $outputTable[$dayBefore]['23']
//                ];
//            }
//            $dataDayAfter = [
//                '00' => $outputTable[$dayAfter]['00'],
//                '01' => $outputTable[$dayAfter]['01'],
//                '02' => $outputTable[$dayAfter]['02']
//            ];

            for ($h = 0; $h < 24; $h++) {
                $time = (strlen($h) == 2)
                    ? $h
                    : '0' . $h;
                $outputTable[$date][$time] = $this->checkRelativeErrors($outputTable[$date][$time], $dataDayBefore, $dataDayAfter);
            }
        }

        // todo: set number of errors
*/

        $this->outputTable = $outputTable;

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
        $temp = $datarow['temp'];
        if ($temp > 108) {
            $datarow["TOO_HOT"] = TRUE;
        }
        if ($temp > 95 && ($time > 22 || $time < 10)) {
            $datarow["TOO_WARM_AT_NIGHT"] = TRUE;
        }
        if ($temp < 36 && (165 < $dayOfYear && $dayOfYear < 220) ||
            $temp < -20 ) {
            $datarow["TOO_COLD"] = TRUE;
        }
        if ($temp > 95 && $dayOfYear < 134) {
            $datarow["TOO_HOT_EARLY"] = TRUE;
        }
        if ($temp > 95 && $dayOfYear > 259) {
            $datarow["TOO_HOT_LATE"] = TRUE;
        }

        return $datarow;
    }

    private function checkRelativeErrors() {
        for ($d = 1; $d < 366; $d++) {
            $date = $this->dayofyear2date($d, 2010);
            for ($h = 0; $h < 24; $h++) {
                $time = (strlen($h) == 2) ? $h : '0' . $h; // always double digit

                $this->outputTable[$date][$time]['NO_CHANGE'] = null;
                $this->outputTable[$date][$time]['TOO_SMALL_VARIATION'] = null;
                $this->outputTable[$date][$time]['TOO_MUCH_VARIATION'] = null;

                function momentBetween($moment, $bottom, $top) {
                    return ($bottom <= $moment && $moment <= $top);
                }

//                function allNotNull($items) {
//                    foreach ($items as $item) {
//                        if ($item == null) {
//                            return FALSE;
//                        }
//                    }
//                    return TRUE;
//                }

                $currentRowData = $this->outputTable[$date][$time];
                if ($currentRowData['Del+1'] != null &&
                    $currentRowData['Del+2'] != null &&
                    $currentRowData['Del+3'] != null &&
                    $currentRowData['temp'] != null) {
//                if ( allNotNull($currentRowData['Del+1'], $currentRowData['Del+2'],
//                                $currentRowData['Del+3'], $currentRowData['temp'])
//                    ) {

                    if ($currentRowData['Del+1'] == 0 &&
                        $currentRowData['Del+2'] == 0 &&
                        $currentRowData['Del+3'] == 0
                    ) {
                        $this->outputTable[$date][$time]['NO_CHANGE'] = TRUE;
                    }
                    if ($currentRowData['Del+1'] < 0.3 &&
                        $currentRowData['Del+2'] < 1 &&
                        $currentRowData['Del+3'] < 1 &&
                        (momentBetween($d, 105, 243) && momentBetween($h, 9, 17))
                    ) {
                        $this->outputTable[$date][$time]['TOO_SMALL_VARIATION'] = TRUE;
                    }

                }



                // todo: check this ERROR TOO_MUCH_VARIATION
                if ($currentRowData['Del-1'] != null &&
                    $currentRowData['Del+1'] != null &&
                    $currentRowData['temp'] != null &&
                    (momentBetween($d, 105, 243) && momentBetween($h, 9, 17))
                ) {
                    $this->outputTable[$date][$time]['TOO_MUCH_VARIATION'] = TRUE;
                }


            }
        }
        ALLOWED_ERROR

    }

    /**
     * Set Del-1, Del+1, Del+2, Del+3, Del+4 into $this->outputTable,
     * i.e. absolute temperature variation from previous hour or next 1, 2, 3, or 4 hours
     */
    private function calculateDeltas() {

        for ($d = 1; $d < 366; $d++) {
            $date = $this->dayofyear2date($d, 2010);
            for ($h = 0; $h < 24; $h++) {
                $time = (strlen($h) == 2) ? $h : '0' . $h; // always double digit

                $this->setDelta('-1', $date, $time);
                $this->setDelta('+1', $date, $time);
                $this->setDelta('+2', $date, $time);
                $this->setDelta('+3', $date, $time);
                $this->setDelta('+4', $date, $time);
            }
        }



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
        if (isset($this->outputTable[$diffD][$diffT])) {
            $diffDataRow = $this->outputTable[$diffD][$diffT];
            $this->outputTable[$date][$time]['Del' . $diff] = abs($this->outputTable[$date][$time]['temp'] - $diffDataRow['temp']);
        }

    }

    private function setMaxMin() {

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
        $year = ($year != null)
            ? $year
            : date("Y");
        $str = date($format, strtotime("Jan 1, " . $year) + $offset);

        return ($str);

    }





    private function getPreviousHour($date, $time) {

        if ($time == 0) {

        }
    }

    private function getNextHour($date, $time) {

    }

}