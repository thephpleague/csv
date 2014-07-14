<?php
namespace League\Csv\Filter;

use php_user_filter;

class TranscodeFilter extends php_user_filter
{

	const FILTER_NAME = 'convert.transcode.';

	protected $encodingFrom = 'auto';

	protected $encodingTo;

	/**
	 * @return bool Success
	 */
	public function onCreate() {
		if (strpos($this->filtername, self::FILTER_NAME) !== 0) {
			return false;
		}

		$params = substr($this->filtername, strlen(self::FILTER_NAME));
		if (!preg_match('/^([-\w]+)(:([-\w]+))?$/', $params, $matches)) {
			return false;
		}

		if (isset($matches[1])) {
			$this->encodingFrom = $matches[1];
		}

		$this->encodingTo = mb_internal_encoding();
		if (isset($matches[3])) {
			$this->encodingTo = $matches[3];
		}

		$this->params['locale'] = setlocale(LC_CTYPE, '0');
		if (stripos($this->params['locale'], 'UTF-8') === false) {
			setlocale(LC_CTYPE, 'en_US.UTF-8');
		}

		return true;
	}

	/**
	 * @return void
	 */
	public function onClose() {
		setlocale(LC_CTYPE, $this->params['locale']);
	}

	/**
	 * TranscodeFilter::filter()
	 *
	 * @param resource $in
	 * @param resource $out
	 * @param int $consumed
	 * @param bool $closing
	 * @return int
	 */
	public function filter($in, $out, &$consumed, $closing) {
		while ($res = stream_bucket_make_writeable($in)) {
			$res->data = @mb_convert_encoding($res->data, $this->encodingTo, $this->encodingFrom);
			$consumed += $res->datalen;
			stream_bucket_append($out, $res);
		}

		return PSFS_PASS_ON;
	}

}
