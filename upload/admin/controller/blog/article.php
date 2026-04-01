<?php
class ControllerBlogArticle extends Controller {
  public function index() {
    $this->load->language('blog/article');
    $this->load->model('blog/article');
    $this->document->setTitle($this->language->get('heading_title'));

    $this->getList();
  }

  public function getList() {
    $this->load->model('setting/store');
    $this->load->model('localisation/language');
    $this->load->model('blog/article');
    $user_token = $this->session->data['user_token'];

    $data = [
      'articles'      => [],
      'column_left'   => $this->load->controller('common/column_left'),
      'footer'        => $this->load->controller('common/footer'),
      'header'        => $this->load->controller('common/header'),
      'breadcrumbs'   => $this->displayBreadcrumbs(),
      'pagination'    => $this->getPaginamtion()['pagination'],
      'results'       => $this->getPaginamtion()['results'],
      'user_token'    => $user_token,
      'add'           => $this->url->link('blog/article/add', 'user_token=' . $user_token, true),
      'delete'        => $this->url->link('blog/article/delete', 'user_token=' . $user_token, true),
    ];

    $this->response->setOutput($this->load->view('blog/article_list', $data));
  }

  public function getForm() : void {
    $this->load->model('setting/store');
    $this->load->model('localisation/language');
    $this->load->model('blog/article');
    
    $data       = [];
    $id         = $this->request->get['article_id'] ?? null;
    $user_token = $this->session->data['user_token'];
    $url        = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['sort', 'order', 'page'])));

    $data = [
      'description'   => $this->model_blog_article->getArticleDescriptions($id),
      'column_left'   => $this->load->controller('common/column_left'),
      'footer'        => $this->load->controller('common/footer'),
      'header'        => $this->load->controller('common/header'),
      'languages'     => $this->model_localisation_language->getLanguages(),
      'stores'        => $this->model_setting_store->getMultistores(),
      'action'        => $id ? $this->url->link('catalog/category/edit', 'user_token=' . $user_token . '&category_id=' . $id . $url, true) : $this->url->link('catalog/category/add', 'user_token=' . $user_token . $url, true),
      'cancel'        => $this->url->link('catalog/category', 'user_token=' . $user_token . $url, true),
    ];
    $this->response->setOutput($this->load->view('blog/article_form', $data));
  }

  public function add() {
    $this->load->language('blog/article');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('blog/article');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_blog_article->addArticle($this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
      $url = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['sort', 'order', 'page'])));

			$this->response->redirect($this->url->link('blog/article', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
  }

	public function edit() {
		$this->load->language('blog/article');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('blog/article');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_blog_article->editArticle($this->request->get['article_id'], $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

      $url = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['sort', 'order', 'page'])));

			$this->response->redirect($this->url->link('blog/article', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getForm();
	}

	public function delete() {
		$this->load->language('blog/article');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('blog/article');

		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $article_id) {
				$this->model_blog_article->deleteArticle($article_id);
			}

			$this->session->data['success'] = $this->language->get('text_success_deleted');

      $url = '&' . http_build_query(array_intersect_key($this->request->get, array_flip(['sort', 'order', 'page'])));

			$this->response->redirect($this->url->link('blog/article', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}

		$this->getList();
	}

  public function displayBreadcrumbs() {
    $this->load->language('blog/article');
    $breadcrumbs = [];
    $breadcrumbs[] = [
      'text' => $this->language->get('text_home'),
      'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
    ];
    $breadcrumbs[] = [
      'text' => $this->language->get('heading_title'),
      'href' => $this->url->link('blog/article', 'user_token=' . $this->session->data['user_token'], true)
    ];
    return $breadcrumbs;
  }

  public function getPaginamtion() {
    $this->load->model('blog/article');
    $total = $this->model_blog_article->getArticleTotal();
    $page = (int) ($this->request->get['page'] ?? 1);

    $params = ['sort', 'order'];

    $query = [];
    
    foreach ($params as $param) {
      if (isset($this->request->get[$param])) {
        $query[$param] = $this->request->get[$param];
      }
    }
    
    $url = $query ? '&' . http_build_query($query) : '';

    $pagination = new Pagination();
		$pagination->total = $total;
		$pagination->page  = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('blog/article', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf($this->language->get('text_pagination'), ($total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($total - $this->config->get('config_limit_admin'))) ? $total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $total, ceil($total / $this->config->get('config_limit_admin')));

    return $data;
  }

  protected function validateForm() {
		if (!$this->user->hasPermission('modify', 'blog/article')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		foreach ($this->request->post['article_description'] as $language_id => $value) {
			if ((utf8_strlen($value['name']) < 1) || (utf8_strlen($value['name']) > 255)) {
				$this->error['name'][$language_id] = $this->language->get('error_name');
			}
		}

		// if ($this->request->post['article_seo_url']) {
		// 	$this->load->model('design/seo_url');

		// 	foreach ($this->request->post['article_seo_url'] as $store_id => $language) {
		// 		foreach ($language as $language_id => $keyword) {
		// 			if (!empty($keyword)) {
		// 				if (count(array_keys($language, $keyword)) > 1) {
		// 					$this->error['keyword'][$store_id][$language_id] = $this->language->get('error_unique');
		// 				}

		// 				$seo_urls = $this->model_design_seo_url->getSeoUrlsByKeyword($keyword);

		// 				foreach ($seo_urls as $seo_url) {
		// 					if (($seo_url['store_id'] == $store_id) && (!isset($this->request->get['article_id']) || ($seo_url['query'] != 'article_id=' . $this->request->get['article_id']))) {
		// 						$this->error['keyword'][$store_id][$language_id] = $this->language->get('error_keyword');

		// 						break;
		// 					}
		// 				}
		// 			}
		// 		}
		// 	}
		// }

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_warning');
		}

		return !$this->error;
	}

	protected function validateDelete() {
		if (!$this->user->hasPermission('modify', 'blog/article')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}
}
