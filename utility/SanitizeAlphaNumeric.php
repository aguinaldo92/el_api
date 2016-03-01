<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace utility;

/**
 * Description of SanitizeAlphaNumeric
 *
 * @author andrea
 */
class SanitizeAlphaNumeric implements SanitizeInterface {

    public function sanitize($string, $allow = null) {
        switch ($allow) {
            case "space":
                $pattern = '/[^A-Za-z0-9 \_]/';
                break;
            default:
                $pattern = '/[^A-Za-z0-9\_]/';
                break;
        }
        return trim(preg_replace($pattern, '', $string)); // Removes special chars and leading and trailing spaces
    }

}
