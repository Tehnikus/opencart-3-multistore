<?php
class ControllerProductProduct extends Controller {
	private $error = array();
	

	public function index() {
		$this->load->model('catalog/product');

		if (!isset($this->request->get['product_id'])) {
			return $this->response->redirect($this->url->link('common/not_found', '', true));
		}

		$data = $this->model_catalog_product->getProduct((int) $this->request->get['product_id']);

		if ($data) {

			$this->model_catalog_product->updateViewed($this->request->get['product_id']);
			
			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('product/product', $data));
		} else {
			$this->response->redirect($this->url->link('common/not_found', '', true));
		}
	}

	// public function review() {
	// 	$this->load->language('product/product');

	// 	$this->load->model('catalog/review');

	// 	if (isset($this->request->get['page'])) {
	// 		$page = (int)$this->request->get['page'];
	// 	} else {
	// 		$page = 1;
	// 	}

	// 	$data['reviews'] = array();

	// 	$review_total = $this->model_catalog_review->getTotalReviewsByProductId($this->request->get['product_id']);

	// 	$results = $this->model_catalog_review->getReviewsByProductId($this->request->get['product_id'], ($page - 1) * 5, 5);

	// 	foreach ($results as $result) {
	// 		$data['reviews'][] = array(
	// 			'author'     => $result['author'],
	// 			'text'       => nl2br($result['text']),
	// 			'rating'     => (int)$result['rating'],
	// 			'date_added' => date($this->language->get('date_format_short'), strtotime($result['date_added']))
	// 		);
	// 	}

	// }

	// public function write() {
	// 	$this->load->language('product/product');

	// 	$json = array();

	// 	if (isset($this->request->get['product_id']) && $this->request->get['product_id']) {
	// 		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
	// 			if ((utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 25)) {
	// 				$json['error'] = $this->language->get('error_name');
	// 			}

	// 			if ((utf8_strlen($this->request->post['text']) < 25) || (utf8_strlen($this->request->post['text']) > 1000)) {
	// 				$json['error'] = $this->language->get('error_text');
	// 			}
			
	// 			if (empty($this->request->post['rating']) || $this->request->post['rating'] < 0 || $this->request->post['rating'] > 5) {
	// 				$json['error'] = $this->language->get('error_rating');
	// 			}

	// 			// Captcha
	// 			if ($this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('review', (array)$this->config->get('config_captcha_page'))) {
	// 				$captcha = $this->load->controller('extension/captcha/' . $this->config->get('config_captcha') . '/validate');

	// 				if ($captcha) {
	// 					$json['error'] = $captcha;
	// 				}
	// 			}

	// 			if (!isset($json['error'])) {
	// 				$this->load->model('catalog/review');

	// 				$this->model_catalog_review->addReview($this->request->get['product_id'], $this->request->post);

	// 				$json['success'] = $this->language->get('text_success');
	// 			}
	// 		}
	// 	} else {
	// 		$json['error'] = $this->language->get('error_product');
	// 	} 

	// 	$this->response->addHeader('Content-Type: application/json');
	// 	$this->response->setOutput(json_encode($json));
	// }

	// public function getRecurringDescription() {
	// 	$this->load->language('product/product');
	// 	$this->load->model('catalog/product');

	// 	if (isset($this->request->post['product_id'])) {
	// 		$product_id = $this->request->post['product_id'];
	// 	} else {
	// 		$product_id = 0;
	// 	}

	// 	if (isset($this->request->post['recurring_id'])) {
	// 		$recurring_id = $this->request->post['recurring_id'];
	// 	} else {
	// 		$recurring_id = 0;
	// 	}

	// 	if (isset($this->request->post['quantity'])) {
	// 		$quantity = $this->request->post['quantity'];
	// 	} else {
	// 		$quantity = 1;
	// 	}

	// 	$product_info = $this->model_catalog_product->getProduct($product_id);
		
	// 	$recurring_info = $this->model_catalog_product->getProfile($product_id, $recurring_id);

	// 	$json = array();

	// 	if ($product_info && $recurring_info) {
	// 		if (!$json) {
	// 			$frequencies = array(
	// 				'day'        => $this->language->get('text_day'),
	// 				'week'       => $this->language->get('text_week'),
	// 				'semi_month' => $this->language->get('text_semi_month'),
	// 				'month'      => $this->language->get('text_month'),
	// 				'year'       => $this->language->get('text_year'),
	// 			);

	// 			if ($recurring_info['trial_status'] == 1) {
	// 				$price = $this->currency->format($this->tax->calculate($recurring_info['trial_price'] * $quantity, $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
	// 				$trial_text = sprintf($this->language->get('text_trial_description'), $price, $recurring_info['trial_cycle'], $frequencies[$recurring_info['trial_frequency']], $recurring_info['trial_duration']) . ' ';
	// 			} else {
	// 				$trial_text = '';
	// 			}

	// 			$price = $this->currency->format($this->tax->calculate($recurring_info['price'] * $quantity, $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);

	// 			if ($recurring_info['duration']) {
	// 				$text = $trial_text . sprintf($this->language->get('text_payment_description'), $price, $recurring_info['cycle'], $frequencies[$recurring_info['frequency']], $recurring_info['duration']);
	// 			} else {
	// 				$text = $trial_text . sprintf($this->language->get('text_payment_cancel'), $price, $recurring_info['cycle'], $frequencies[$recurring_info['frequency']], $recurring_info['duration']);
	// 			}

	// 			$json['success'] = $text;
	// 		}
	// 	}

	// 	$this->response->addHeader('Content-Type: application/json');
	// 	$this->response->setOutput(json_encode($json));
	// }
}
