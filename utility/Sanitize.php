<?php namespace utility;

class Sanitize
{
    private $input;

    public function setInput(SanitizeInterface $inputType)
    {
        $this->input = $inputType;
    }

    public function loadInput($string)
    {
        return $this->input->sanitize($string);
    }
}