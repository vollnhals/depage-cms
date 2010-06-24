<?php
/**
 * @file    lib_import_csv.php
 *
 * copyright (c) 2007-2010 Frank Hellenkamp [jonas@depagecms.net]
 *
 * @author    Frank Hellenkamp [jonas@depagecms.net]
 */

    /* {{{ splitCSVLine() */
    function splitCSVLine($line, $returnEmptyLines = true) {
        global $divider;

        if (!$returnEmptyLines) {
            $pattern = "/^[$divider]*\s*$/";
            if (preg_match($pattern, $line) > 0) {
                return false;
            }
        }

        // replace empty elements by a space
        while (strpos($line, "$divider$divider") !== false) {
            $line = str_replace("$divider$divider", "$divider $divider", $line);
        }
        if ($line[0] == "$divider") {
            $line = " " . $line;
        }
        // search for thing betweens commas or between \"
        $pattern = "/(\\\\\")([^\\\"]*)\\1|[^$divider]*/";
        preg_match_all($pattern, $line, $matches, PREG_PATTERN_ORDER);

        $tData = array();
        for ($i = 0; $i < count($matches[0]); $i = $i + 2) {
            if ($matches[2][$i] != "") {
                // add matches in \"
                $tData[$i / 2] = $matches[2][$i];
            } else {
                // add other matches
                $tData[$i / 2] = $matches[0][$i];
            }
        }
        return $tData;
    }
    /* }}} */
    /* {{{ parseDataDate() */
    function parseDataDate($date) {
        if (trim($date) == "") {
            return false;
        }
        $month = array(
            "Jan" => array("January"),
            "Feb" => array("February"),
            "Mar" => array("March", "Mrz"),
            "Apr" => array("April"),
            "May" => array("Mai"),
            "Jun" => array("June"),
            "Jul" => array("July"),
            "Aug" => array("August"),
            "Sep" => array("September"),
            "Oct" => array("October", "Oktober", "Okt"),
            "Nov" => array("November"),
            "Dec" => array("December", "Dezember", "Dez"),
            );
        foreach ($month as $name => $value) {
            $date = str_replace($value, $name, $date);
        }
        $date = str_replace(array("/ ", ". ", "/", "." , " "), "-", $date);

        $pattern = "/\\d{1,2}-(" . implode("|", array_keys($month)). ")-(\\d{2}){1,2}/";
        if (preg_match($pattern, $date)) {
            return strtotime($date);
        } else {
            return false;
        }
    }
    /* }}} */
    /* {{{ parseDataNumber() */
    function parseDataNumber($number, $decimalDivider = "\\.", $thousandDivider = ",") {
        $pattern = "/^(\\d*)+($thousandDivider\\d{3})*($decimalDivider\\d*)?$/";
        if (preg_match($pattern, $number, $matches)) {
            return (float) str_replace($thousandDivider, "", $matches[0]);
        } else {
            return null;
        }
    }
    /* }}} */
    /* {{{ createTemporaryTable() */
    function createTemporaryTable($tablename) {
        global $fields;

        $tablename = mysql_real_escape_string($tablename);

        $sql = "CREATE TABLE `$tablename` ( dte date NOT NULL default '0000-00-00',";
        foreach ($fields as $field) {
            $sql .= " $field decimal(14," . getFieldDecimals($field) . ") default NULL,";
        }
        $sql .= " PRIMARY KEY (dte) ) ENGINE=MyISAM DEFAULT CHARSET=latin1";

        $result = db_query($sql);
    }
    /* }}} */
    /* {{{ removeTemporaryTable() */
    function removeTemporaryTable($tablename) {
        $tablename = mysql_real_escape_string($tablename);

        $result = db_query("
            DROP TABLE `$tablename`
        ");
    }
    /* }}} */
    /* {{{ updateRowInDatabase() */
    function updateRowInDatabase($table, $mdate, $dataset) {
        global $fields;

        $date = "'" . mysql_real_escape_string(strftime("%Y-%m-%d", $mdate)) ."'";
        $sql = "REPLACE INTO $table SET
            dte=$date,";

        for ($i = 1; $i <= count($fields); $i++) {
            $value = $dataset[$i] === null ? "NULL" : "'" . mysql_real_escape_string(str_replace(",", ".", $dataset[$i])) . "'";
            $sql .= " {$fields[$i - 1]} = {$value}";
            if ($i < count($fields)) {
                $sql .= ",";
            }
        }

        $sql .= ";";

        db_query($sql);
    }
    /* }}} */

/* {{{ file_put_contents() */
if (!function_exists("file_put_contents")) {
    function file_put_contents($file, $data) {
        $fh = fopen($file, "w");
        fwrite($fh, $data);
        fclose($fh);
    }
}
/* }}} */

    /* vim:set ft=php sw=4 sts=4 fdm=marker : */
