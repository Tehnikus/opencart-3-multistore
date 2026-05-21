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

	public function fetchEditCacheSettings() {
		$json = [];

		if (!$this->user->hasPermission('modify', 'common/developer')) {
			$json['error'] = $this->language->get('developer_error_permission');
		} else {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('cache', $this->request->post, (int) $this->session->data['store_id']);
			$json['post'] = $this->request->post;

			$json['success'] = $this->language->get('developer_message_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function fetchClearCache() : void {
		$cacheTypeKey = $this->request->post['cacheType'] ?? '';

		if (!$cacheTypeKey || !isset($this->cacheSettings[$cacheTypeKey])) {
			$json['error'] = 'Invalid cache type';
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}

		$cacheType = $this->cacheSettings[$cacheTypeKey];
		$json = [];

		// Fastfile
		if ($this->config->get('cache_engine') === 'fastfile') {
			$path = $cacheType['path'];
			if (in_array($path, ['product', 'category', 'filter_page', 'filter', 'module', 'header', 'footer'])) {
				$path = DIR_CACHE . 'cache/' . $path;
			} elseif ($path === 'session') {
				$path = DIR_SESSION;
			} else {
				$path = DIR_CACHE . $path;
			}

			$json['path'] = $path;

			if ($this->clearDirectory($path)) {
				$json['success'] = sprintf($this->language->get('developer_message_cleared'), $this->language->get('developer_setting_' . $cacheTypeKey));
			} else {
				$json['error'] =  $this->language->get('developer_error_chmod');
			}
		}

		// Redis
		if ($this->config->get('cache_engine') === 'redis') {
			// Delete cache entries by prefixes
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function clearDirectory($dir) : bool {
		if (!is_readable($dir) || !is_writable($dir)) return false;
		$it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
		$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
		foreach($files as $file) {
			if ($file->isDir()){
				rmdir($file->getRealPath());
			} else {
				unlink($file->getRealPath());
			}
		}
		return true;
	}
}
