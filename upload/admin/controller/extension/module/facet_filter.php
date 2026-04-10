<?php
class ControllerExtensionModuleFacetFilter extends Controller {
	private $error = [];

	public function index() {
		$this->document->addScript('view/javascript/niftyAutocomplete.js');
		$this->document->addStyle('view/stylesheet/niftyAutocomplete.css');
		$this->load->language('extension/module/facet_filter');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('catalog/facet');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			// Save new settings
			$this->model_setting_setting->editSetting('module_facet_filter', $this->request->post, (int) $this->session->data['store_id']);
			// Show success message
			$this->session->data['success'] = $this->language->get('text_success');
			// Redirect to extensions list
			// $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		// Errors
		$data['error_warning'] = $this->error['warning'] ?? '';
		// Get settings by store id
		$settings = $this->model_setting_setting->getSetting('module_facet_filter', (int) $this->session->data['store_id']);

		// Form data
		$data['module_facet_filter_status'] = $this->request->post['module_facet_filter_status'] ?? $settings['module_facet_filter_status'] ?? [];
		$data['settings'] 									= $this->request->post['module_facet_filter_settings'] ?? $settings['module_facet_filter_settings'] ?? [];
		$data['pageTypes'] 								  = ['category', 'manufacturer', 'special', 'search'];
		$data['facetTypes'] 								= array_flip($this->model_catalog_facet->getFacetTypes());
		$data['user_token'] 								= $this->session->data['user_token'];

		// Get category name for saved categories
		if (isset($data['settings']['distinct_categories'])) {
			$this->load->model('catalog/category');
			foreach ($data['settings']['distinct_categories'] as $category_id => &$category) {
				$categoryData 		= $this->model_catalog_category->getCategory($category_id);
				$category['name'] = $categoryData['name'];
			}
		}
		
		// Buttons
		$data['action'] 		 = $this->url->link('extension/module/facet_filter', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] 		 = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
		// Common interface
		$data['header'] 		 = $this->load->controller('common/header');
		$data['footer'] 		 = $this->load->controller('common/footer');
		$data['column_left'] = $this->load->controller('common/column_left');

		$this->response->setOutput($this->load->view('extension/module/facet_filter', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/facet_filter')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	public function fetchRebuildFacetIndex() {
		$storeId	= (int) $this->session->data['store_id'];

		$this->load->model('catalog/facet');
		$this->model_catalog_facet->buildFacetIndex(store_id: $storeId);
		$this->model_catalog_facet->buildFacetSorts(store_id: $storeId);
		$this->model_catalog_facet->buildFacetNames(store_id: $storeId);

		header('Content-Type: application/json');
		echo(json_encode(['success' => true]));
	}
}