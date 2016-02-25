<?php

interface SanitizeInterface {

    public function sanitize($string);
}

class SanitizeString implements SanitizeInterface {

    public function sanitize($string, $allow = null) {
        $pattern = '/[^A-Za-z0-9]/';
        if ($allow === "space") { 
            $pattern = '/[^A-Za-z0-9 ]/';
            $string = trim($string); // gli spazi iniziali e finali non soono cmq ammessi
        }
        return  preg_replace($pattern, '', $string); // Removes special chars.
    }

}
class SanitizeNumber implements SanitizeInterface {

    public function sanitize($number, $type = "int") {
        $pattern = '/[^0-9]/';
        if ($type === "float") {
            $pattern = '/[^0-9\.]/';
        }
        return  preg_replace($pattern, '', $number); // Removes special chars.
    }

}
