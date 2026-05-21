<?php
class ControllerCommonDeveloper extends Controller {

	private $cacheSettings = [];
	public function __construct($registry) {
		parent::__construct($registry);

		$this->cacheSettings = [
			'product' 		=> ['path' => 'product',			'config' => 'cache_products'],
			'category' 		=> ['path' => 'category',			'config' => 'cache_categories'],
			'filter_page' => ['path' => 'filter_page',	'config' => 'cache_filter_pages'],
			'filter' 			=> ['path' => 'filter',				'config' => 'cache_filter_list'],
			'module' 			=> ['path' => 'module',				'config' => 'cache_module'],
			'header' 			=> ['path' => 'header',				'config' => 'cache_header'],
			'footer' 			=> ['path' => 'footer',				'config' => 'cache_footer'],
			'all' 				=> ['path' => 'cache'],
			'twig' 				=> ['path' => 'template'],
			'html'				=> ['path' => 'html'],
			'session' 		=> ['path' => 'session'],
		];
	}
	public function index() {

		$data['user_token'] = $this->session->data['user_token'];
		
		foreach ($this->cacheSettings as $key => $value) {
			$data['cacheSettings'][$key] = $value;
			$data['cacheSettings'][$key]['name'] = $this->language->get('developer_setting_' . $key);
			if (isset($value['config'])) {
				$data['cacheSettings'][$key]['configValue'] = (int) $this->config->get($value['config']);
			}
		}

		$data['cacheEngine'] = $this->config->get('cache_engine');

		$this->response->setOutput($this->load->view('common/developer', $data));
	}

	public function edit() {
		$this->load->language('common/developer');

		$json = array();

		if (!$this->user->hasPermission('modify', 'common/developer')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('developer', $this->request->post, (int) $this->session->data['store_id']);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function theme() {
		$this->load->language('common/developer');

		$json = array();

		if (!$this->user->hasPermission('modify', 'common/developer')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$directories = glob(DIR_CACHE . '/template/*', GLOB_ONLYDIR);

			if ($directories) {
				foreach ($directories as $directory) {
					$files = glob($directory . '/*');

					foreach ($files as $file) { 
						if (is_file($file)) {
							unlink($file);
						}
					}

					if (is_dir($directory)) {
						rmdir($directory);
					}
				}
			}

			$json['success'] = sprintf($this->language->get('text_cache'), $this->language->get('text_theme'));
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
