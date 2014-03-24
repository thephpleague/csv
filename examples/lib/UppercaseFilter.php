<?php

namespace lib;

use php_user_filter;
use League\Csv\Stream\FilterInterface;

class UppercaseFilter extends php_user_filter implements FilterInterface
{
    private static $name = 'csv.strtoupper';

    private static $is_registered = false;

    public function __construct()
    {
        if (self::isRegistered()) {
            return;
        }
        stream_filter_register(self::$name, __CLASS__);
        self::$is_registered = true;
    }

    public static function isRegistered()
    {
        return self::$is_registered;
    }

    public static function getName()
    {
        return self::$name;
    }

    public function fetchPath($path)
    {
        return 'php://filter/'.self::$name.'/resource='.$path;
    }

    public function onCreate()
    {
        return $this->filtername == self::$name;
    }

    public function onClose()
    {
        return null;
    }

    public function filter($in, $out, &$consumed, $closing)
    {
        while ($res = stream_bucket_make_writeable($in)) {
            $res->data = strtoupper($res->data);
            $consumed += $res->datalen;
            stream_bucket_append($out, $res);
        }

        return PSFS_PASS_ON;
    }
}
