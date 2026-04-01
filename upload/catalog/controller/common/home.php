<?php
class ControllerCommonHome extends Controller {
	public function index() {
		// Get current language id
		$config_language_id = $this->config->get('config_language_id');

		// Set multilang homepage meta title
		$config_meta_title = $this->config->get('config_meta_title');
		$lang_meta_title = (is_array($config_meta_title) && isset($config_meta_title[$config_language_id])) ? $config_meta_title[$config_language_id] : '';

		// Set multilang homepage meta description
		$config_meta_description = $this->config->get('config_meta_description');
		$lang_meta_description = (is_array($config_meta_description) && isset($config_meta_description[$config_language_id])) ? $config_meta_description[$config_language_id] : '';
		
		// Set multilang homepage meta keywords
		$config_meta_keywords = $this->config->get('config_meta_keyword');
		$lang_meta_keywords = (is_array($config_meta_keywords) && isset($config_meta_keywords[$config_language_id])) ? $config_meta_keywords[$config_language_id] : '';
		
		$this->document->setTitle($lang_meta_title);
		$this->document->setDescription($lang_meta_description);
		$this->document->setKeywords($lang_meta_keywords);

		if (isset($this->request->get['route'])) {
			$this->document->addLink($this->config->get('config_url'), 'canonical');
		}

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('common/home', $data));
	}
}
