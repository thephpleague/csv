<?php

namespace lib;

use League\Csv\Filter\TranscodeFilter;

class AnsiTranscodeFilter extends TranscodeFilter
{

    protected $encoding_from = 'iso-8859-1';

}
