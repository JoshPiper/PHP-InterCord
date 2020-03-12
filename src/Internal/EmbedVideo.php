<?php


namespace Internet\InterCord\Internal;


use JsonSerializable;

/**
 * Represents an embedded video.
 * Class EmbedVideo
 * @package Internet\InterCord\Internal
 */
class EmbedVideo implements JsonSerializable {
	private $url;
	private $height;
	private $width;

	/**
	 * EmbedVideo constructor.
	 * @param string $url Video URL
	 * @param int $width Width of the video.
	 * @param int $height Height of the video.
	 */
	public function __construct(string $url = '', int $width = 0, int $height = 0){
		$this->url = $url;
		$this->height = $height;
		$this->width = $width;
	}

	/**
	 * Convert the object into an associative array.
	 * @return array
	 */
	public function jsonSerialize(){
		return array_filter([
			'url' => $this->url,
			'height' => $this->height,
			'width' => $this->width
		]);
	}
}