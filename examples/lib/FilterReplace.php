<?php

namespace lib;

use php_user_filter;

class FilterReplace extends php_user_filter
{
    const FILTER_NAME = 'convert.replace.';

    private $search;

    private $replace;

    public function onCreate()
    {
        if( strpos( $this->filtername, self::FILTER_NAME ) !== 0 ){
            return false;
        }

        $params = substr( $this->filtername, strlen( self::FILTER_NAME ) );

        if( !preg_match( '/([^:]+):([^$]+)$/', $params, $matches ) ){
            return false;
        }

        $this->search  = $matches[1];
        $this->replace = $matches[2];

        return true;
    }

    public function filter( $in, $out, &$consumed, $closing )
    {
        while( $res = stream_bucket_make_writeable( $in ) ){

            $res->data = str_replace( $this->search, $this->replace, $res->data );

            $consumed += $res->datalen;

            /** @noinspection PhpParamsInspection */
            stream_bucket_append( $out, $res );
        }

        return PSFS_PASS_ON;
    }
}
