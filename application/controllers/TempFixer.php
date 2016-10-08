<?php defined('BASEPATH') OR exit('No direct script access allowed');

class TempFixer extends CI_Controller {


    private $tempTable = [];


    public function __construct() {
        parent::__construct();

        define('ERRORS_COUNT_TODAY', 0);
        define('MISSING_DATA', 1);
        define('TOO_HOT', 2);
        define('TOO_COLD', 8);
        define('TOO_WARM_AT_NIGHT', 3);
        define('TOO_HOT_EARLY', 4); // early in the season
        define('TOO_HOT_LATE', 5); // late in the season
        define('NO_CHANGE', 6);
        define('LITTLE_VARIATION', 7); // too little variation between 9:00am to 5:00pm from Apr15 to Sep1
        define('TOO_MUCH_VARIATION', 9); // too much change in temp in 60min

        // define file data header, i.e. the colums names
        define('station', 0);
        define('latitude', 1);
        define('longitude', 2);
        define('year', 3);
        define('date', 4);
        define('time', 5);
        define('temp', 6);
        define('dwpt', 7);
        define('rh', 8);
        define('wdir', 9);
        define('wspd', 10);
        define('stnpresskpa', 11);

//        $this->createOutputTable();

    }


    public function index() {

        $this->getInputTable();
//        $this->sanitizeData();
//        $this->setMaxMin();

    }


    public function getInputTable() {
        $fileLines = file('./TempData/2010input.tsv', FILE_IGNORE_NEW_LINES);

        foreach ($fileLines as &$line) {
            $line = explode("\t", $line);
        }

        $inputTempTable = [];
        $columns = $fileLines[0];
        $row = [];
        for ($line = 1; $line < count($fileLines); $line++) {
            for ($c = 0; $c < count($columns); $c++) {
                $row[$columns[$c]] = $fileLines[$line][$c];
            }
            $inputTempTable[] = $row;
        }

        var_dump($inputTempTable);

        return $fileLines;
    }




    public function createOutputTable() {
        $tempC = [];
        $tempF = [];

        $inputTempTable = $this->getInputTable();
        $row = [];



        for ($d = 0; $d < 365; $d++) {
            $row['date'] = $this->dayofyear2date($d);
            for ($h = 0; $h < 24; $h++) {
                $row = [];

            }
            $tempTable[] = $row;
        }

    }

    public function fillTemp() {

        $data = [];
        srand(10); // seed random values

        for ($d = 0; $d < 365; $d++) {

            $data['date'] = $this->dayofyear2date($d);

            $this->db->insert('temp', $data);

        }

    }

    private function checkError() {

    }

    private function setMaxMin() {

    }

    /**
     * returns a date string correspondent to the day of the (current) year:
     * @param $day - int between 0 and 365
     * @param string $format - string (optional)
     * @return false|string
     *
     * example:
     *    dayofyear2date(2) -> '2016-01-03'
     */
    private function dayofyear2date($day, $format = 'Y-m-d') {
        if (gettype($day) == 'string' || $day > 365 || $day < 0) {
            return false;
        }

        $offset = intval(intval($day) * 24 * 60 * 60);
        $str = date($format, strtotime('Jan 1, ' . date('Y')) + $offset);

        return ($str);

    }

    public function sanitizeData() {

        foreach ($this->tempTable as $row) {
            $this->checkError();
        }

        $this->setMaxMin();

    }

}