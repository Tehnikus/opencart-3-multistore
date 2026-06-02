<?php
/**
 * @package		OpenCart
 * @author		Daniel Kerr
 * @copyright	Copyright (c) 2005 - 2017, OpenCart, Ltd. (https://www.opencart.com/)
 * @license		https://opensource.org/licenses/GPL-3.0
 * @link		https://www.opencart.com
*/

/**
* Document class
*/
class Document {
	private $title;
	private $description;
	private $keywords;

	private $links 		= [];
	private $styles 	= [];
	private $scripts 	= [];
	private $jsonLd  	= [];
	private $robots 	= [];

	public function setTitle($title) {
		$this->title = $title;
	}

	public function getTitle() {
		return $this->title;
	}

	public function setDescription($description) {
		$this->description = $description;
	}

	public function getDescription() {
		return $this->description;
	}

	public function setKeywords($keywords) {
		$this->keywords = $keywords;
	}

	public function getKeywords() {
		return $this->keywords;
	}
	
	public function addLink($href, $rel) {
		$this->links[$href] = array(
			'href' => $href,
			'rel'  => $rel
		);
	}

	public function getLinks() {
		return $this->links;
	}

	public function addStyle($href, $rel = 'stylesheet', $media = 'screen', $position = 'header') {
		$this->styles[$position][$href] = array(
			'href'  => $href,
			'rel'   => $rel,
			'media' => $media
		);
	}

	public function getStyles($position = 'header') {
		if (isset($this->styles[$position])) {
			return $this->styles[$position];
		} else {
			return [];
		}
	}

	public function addScript($href, $position = 'header') {
		$this->scripts[$position][$href] = $href;
	}

	public function getScripts($position = 'header') {
		if (isset($this->scripts[$position])) {
			return $this->scripts[$position];
		} else {
			return [];
		}
	}

	/**
	 * Set document JSON-LD microdata
	 * @param array $data
	 * @return void
	 */
	public function addJsonLd(array $data = []) : void {
		if (!empty($data)) {
			$this->jsonLd[] = json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_QUOT|JSON_HEX_APOS);
		}
	}

	public function getJsonLd() : array {
		return $this->jsonLd;
	}

	public function setRobots($bot, $indexFollow) {
		$this->robots[$bot] = $indexFollow;
	}

	public function getRobots() : array {
		return $this->robots;
	}
}