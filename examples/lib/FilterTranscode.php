<?php

namespace lib;

class FilterTranscode extends php_user_filter
{
    private static $name = 'convert.transcode.';

    private $encoding_from = 'auto';

    private $encoding_to;

    public function onCreate()
    {
        if (strpos($this->filtername, self::$name) !== 0) {
            return false;
        }

        $params = substr($this->filtername, strlen(self::$name));
        if (! preg_match('/^([-\w]+)(:([-\w]+))?$/', $params, $matches)) {
            return false;
        }

        if (isset($matches[1])) {
            $this->encoding_from = $matches[1];
        }

        $this->encoding_to = mb_internal_encoding();
        if (isset($matches[3])) {
            $this->encoding_to = $matches[3];
        }

        $this->params['locale'] = setlocale(LC_CTYPE, '0');
        if (stripos($this->params['locale'], 'UTF-8') === false) {
            setlocale(LC_CTYPE, 'en_US.UTF-8');
        }

        return true;
    }

    public function onClose()
    {
        setlocale(LC_CTYPE, $this->params['locale']);
    }

    public function filter($in, $out, &$consumed, $closing)
    {
        while ($res = stream_bucket_make_writeable($in)) {
            $res->data = @mb_convert_encoding($res->data, $this->encoding_to, $this->encoding_from);
            $consumed += $res->datalen;
            stream_bucket_append($out, $res);
        }

        return PSFS_PASS_ON;
    }
}
