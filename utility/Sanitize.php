<?php

namespace utility;

class Sanitize {

    private $input;

    public function setInput(SanitizeInterface $inputType) {
        $this->input = $inputType;
    }

    public function loadInput($string,$type) {
        return $this->input->sanitize($string,$type);
    }

}
