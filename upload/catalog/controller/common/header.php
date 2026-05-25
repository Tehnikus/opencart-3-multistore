<?php
class ControllerCommonHeader extends Controller {
	public function index() {
		// Analytics
		$this->load->model('setting/extension');

		$data['analytics'] = array();
		// $data['text_compare'] = sprintf($this->language->get('text_compare'), (isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0));


		$analytics = $this->model_setting_extension->getExtensions('analytics');

		foreach ($analytics as $analytic) {
			if ($this->config->get('analytics_' . $analytic['code'] . '_status')) {
				$data['analytics'][] = $this->load->controller('extension/analytics/' . $analytic['code'], $this->config->get('analytics_' . $analytic['code'] . '_status'));
			}
		}

		if ($this->request->server['HTTPS']) {
			$server = $this->config->get('config_ssl');
		} else {
			$server = $this->config->get('config_url');
		}

		if (is_file(DIR_IMAGE . $this->config->get('config_icon'))) {
			$this->document->addLink($server . 'image/' . $this->config->get('config_icon'), 'icon');
		}

		// Add essential styles
		$this->document->addStyle('catalog/view/theme/default/css/main.css', 'stylesheet preload', 'screen', 'header');
		
		// Add essential scripts
		$this->document->addScript('catalog/view/theme/default/js/main.js', 'header');

		$data['title'] 				= $this->document->getTitle();
		$data['description'] 	= $this->document->getDescription();
		$data['keywords'] 		= $this->document->getKeywords();
		$data['links'] 				= $this->document->getLinks();
		$data['styles'] 			= $this->document->getStyles();
		$data['scripts'] 			= $this->document->getScripts('header');
		$data['jsonLd']				= $this->document->getJsonLd();
		
		$data['lang'] 				= $this->language->get('code');
		$data['direction'] 		= $this->language->get('direction');
		$data['name'] 				= $this->config->get('config_name');

		$this->load->language('common/header');

		// Wishlist
		if ($this->customer->isLogged()) {
			$this->load->model('account/wishlist');
			$data['text_wishlist'] = sprintf($this->language->get('text_wishlist'), $this->model_account_wishlist->getTotalWishlist());
		} else {
			$data['text_wishlist'] = sprintf($this->language->get('text_wishlist'), (isset($this->session->data['wishlist']) ? count($this->session->data['wishlist']) : 0));
		}

		$data['text_logged'] = sprintf($this->language->get('text_logged'), $this->url->link('account/account', '', true), $this->customer->getFirstName(), $this->url->link('account/logout', '', true));
		$data['logged'] 				= $this->customer->isLogged();
		
		$data['language'] = $this->load->controller('common/language');
		$data['currency'] = $this->load->controller('common/currency');

		$data = array_merge($data, $this->getCachedData());

		return $this->load->view('common/header', $data);
	}

	private function getCachedData() : array {
		
		$language_id 	= (int) $this->config->get('config_language_id');
		$store_id 		= (int) $this->config->get('config_store_id');
		$cacheSetting = (bool) $this->config->get('cache_header');
		$cacheName 		= "header.store_{$store_id}.language_{$language_id}";

		if ($cacheSetting) {
			$data = $this->cache->get($cacheName);
		}

		if ($data) {
			return $data;
		}

		$data = [];

		if ($this->request->server['HTTPS']) {
			$server = $this->config->get('config_ssl');
		} else {
			$server = $this->config->get('config_url');
		}
		if (is_file(DIR_IMAGE . $this->config->get('config_logo'))) {
			$data['logo'] = $server . 'image/' . $this->config->get('config_logo');
		} else {
			$data['logo'] = '';
		}

		$data['base'] 					= $server;
		$data['home'] 					= $this->url->link('common/home');
		$data['wishlist'] 			= $this->url->link('account/wishlist', '', true);
		$data['account'] 				= $this->url->link('account/account', '', true);
		$data['register'] 			= $this->url->link('account/register', '', true);
		$data['login'] 					= $this->url->link('account/login', '', true);
		$data['order'] 					= $this->url->link('account/order', '', true);
		$data['transaction'] 		= $this->url->link('account/transaction', '', true);
		$data['download'] 			= $this->url->link('account/download', '', true);
		$data['logout'] 				= $this->url->link('account/logout', '', true);
		$data['shopping_cart'] 	= $this->url->link('checkout/cart');
		$data['checkout'] 			= $this->url->link('checkout/checkout', '', true);
		$data['contact'] 				= $this->url->link('information/contact');
		$data['telephone'] 			= $this->config->get('config_telephone');
		$data['search'] 				= $this->load->controller('common/search');
		$data['cart'] 					= $this->load->controller('common/cart');
		$data['menu'] 					= $this->load->controller('common/menu');

		if ($cacheSetting) {
			$this->cache->set($cacheName, $data);
		}

		return $data;

	}
}
