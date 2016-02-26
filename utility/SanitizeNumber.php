<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace utility;

/**
 * Description of SanitizeNumber
 *
 * @author andrea
 */
class SanitizeNumber implements SanitizeInterface {

    public function sanitize($number, $type = "int") {
        $pattern = '/[^0-9]/';
        if ($type === "float") {
            $pattern = '/[^0-9\.]/';
        }
        return trim(preg_replace($pattern, '', $number)); // Removes special chars and leading and trailing spaces
    }

}
