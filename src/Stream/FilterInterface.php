<?php
/**
* League.csv - A lightweight CSV Coder/Decoder library
*
* @author Ignace Nyamagana Butera <nyamsprod@gmail.com>
* @copyright 2014 Ignace Nyamagana Butera
* @link https://github.com/nyamsprod/League.csv
* @license http://opensource.org/licenses/MIT
* @version 5.4.0
* @package League.csv
*
* MIT LICENSE
*
* Permission is hereby granted, free of charge, to any person obtaining
* a copy of this software and associated documentation files (the
* "Software"), to deal in the Software without restriction, including
* without limitation the rights to use, copy, modify, merge, publish,
* distribute, sublicense, and/or sell copies of the Software, and to
* permit persons to whom the Software is furnished to do so, subject to
* the following conditions:
*
* The above copyright notice and this permission notice shall be
* included in all copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
* EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
* MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
* NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
* LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
* OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
* WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/
namespace League\Csv\Stream;

/**
 * A CSV Stream Filter Interface to modify a stream prior to its handling
 * The class that implements this interface MUST extend PHP php_user_filter class
 *
 * @package League.csv
 * @since  5.4.0
 *
 */
interface FilterInterface
{
    /**
     * Tell if the stream filter is already registered
     *
     * @return boolean
     */
    public static function isRegistered();

    /**
     * Return the stream filter registering name
     *
     * @return string
     */
    public static function getName();

    /**
     * set the filter path for a given file path
     *
     * @param string $path the original file path
     *
     * @return string
     */
    public function fetchPath($path);

    /**
     * Called when creating the filter
     *
     * @return boolean
     */
    public function onCreate();

    /**
     * Called when closing the filter
     */
    public function onClose();

    /**
     * This method is called whenever data is read from or written to the attached stream
     *
     * @param resource $in
     * @param resource $out
     * @param integer  $consumed
     * @param boolean  $closing
     *
     * @return integer
     */
    public function filter($in, $out, &$consumed, $closing);
}
