<?php
class ControllerCommonFooter extends Controller {
	public function index() {

		$language_id 	= (int) $this->config->get('config_language_id');
		$store_id			= (int) $this->config->get('config_store_id');
		$cacheName = "footer.store_{$store_id}.language_{$language_id}";
		
		$data = $this->cache->get($cacheName);
		if (!$data) {
			$data = $this->getData();
			$this->cache->set($cacheName, $data);
		}
		
		$data['language_id'] = $language_id;
		$data['store_id'] 	 = $store_id;
		
		return $this->load->view('common/footer', $data);
	}

	private function getData() : array {
		$this->load->language('common/footer');
		$this->load->model('catalog/information');

		$data['informations'] = [];

		foreach ($this->model_catalog_information->getInformations() as $result) {
			if ($result['bottom']) {
				$data['informations'][] = [
					'title' => $result['title'],
					'href'  => $this->url->link('information/information', 'information_id=' . $result['information_id'])
				];
			}
		}

		$data['contact'] 			= $this->url->link('information/contact');
		$data['return'] 			= $this->url->link('account/return/add', '', true);
		$data['sitemap'] 			= $this->url->link('information/sitemap');
		$data['tracking'] 		= $this->url->link('information/tracking');
		$data['manufacturer'] = $this->url->link('product/manufacturer/list');
		$data['voucher'] 			= $this->url->link('account/voucher', '', true);
		$data['affiliate'] 		= $this->url->link('affiliate/login', '', true);
		$data['special'] 			= $this->url->link('product/special');
		$data['bestseller'] 	= $this->url->link('product/bestseller');
		$data['account'] 			= $this->url->link('account/account', '', true);
		$data['order'] 				= $this->url->link('account/order', '', true);
		$data['wishlist'] 		= $this->url->link('account/wishlist', '', true);
		$data['newsletter'] 	= $this->url->link('account/newsletter', '', true);
		$data['powered'] 			= sprintf($this->language->get('text_powered'), $this->config->get('config_name'), date('Y', time()));
		$data['scripts'] 			= $this->document->getScripts('footer');
		$data['styles'] 			= $this->document->getStyles('footer');

		return $data;
	}
}
