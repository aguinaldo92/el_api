<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace utility;

/**
 * Description of SanitizeString
 *
 * @author andrea
 */
class SanitizeString implements SanitizeInterface {

    public function sanitize($string, $allow = null) {
        $pattern = '/[^A-Za-z0-9_]/';
        if ($allow === "space") {
            $pattern = '/[^A-Za-z0-9 _]/';
        }
        return trim(preg_replace($pattern, '', $string)); // Removes special chars and leading and trailing spaces
    }

}
