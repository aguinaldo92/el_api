<?php namespace utility;

interface SanitizeInterface {

    public function sanitize($string);
}

class SanitizeString implements SanitizeInterface {

    public function sanitize($string, $allow = null) {
        $pattern = '/[^A-Za-z0-9_]/';
        if ($allow === "space") { 
            $pattern = '/[^A-Za-z0-9 _]/';
        }
        return  trim(preg_replace($pattern, '', $string)); // Removes special chars and leading and trailing spaces
    }

}
class SanitizeNumber implements SanitizeInterface {

    public function sanitize($number, $type = "int") {
        $pattern = '/[^0-9]/';
        if ($type === "float") {
            $pattern = '/[^0-9\.]/';
        }
        return  trim(preg_replace($pattern, '', $number)); // Removes special chars and leading and trailing spaces
    }

}
