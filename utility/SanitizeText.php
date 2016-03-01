<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace utility;

/**
 * Description of SanitizeText
 *
 * @author andrea
 */
class SanitizeText {
   public function sanitize($string, $allow = null) {
        
        return trim(filter_var($string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW)); // Removes special chars and leading and trailing spaces
    }
}
