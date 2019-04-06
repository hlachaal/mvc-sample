<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/*
	Gamegoon frontend controller...
*/
include_once(APPPATH . "controllers/base_controller.php");

class Authorize extends Base_controller {

	var $_gginc;
	var $_content_view_files = NULL;

	public function __construct() {
		parent::__construct();

		$this->load->library("nonce");
		
		$this->load->model("user_model");
		
		$this->load->helper("mylink");
		$this->load->helper("inflector");
		$this->load->helper("form");

		$this->_data['links'] = array(
			"/asset/gamegoon_style.css",
			"/asset/gamegoon_style.js"
		);

		$this->_gginc = "authorize/";
		
		$this->_init_menu();
	}

	public function index()
	{
		$this->login();
		//$this->load->view('welcome_message');
	}
	
	public function login() {
	
		$this->load->library("form_validation");
		
		$this->form_validation->set_rules(
			"screenName", "Screen Name",
			"trim|required|max_length[18]"
		);
		$this->form_validation->set_rules(
			"password", "Password",
			"trim|required|max_length[50]"
		);
		$this->form_validation->set_rules(
			"rurl", "RURL",
			"trim|max_length[50]"
		);
		
		if ($this->form_validation->run() !== FALSE
		//	&& $this->nonce->confirm_nonce($_POST)
		) {
			$result = $this->auth->login(
				$_POST['screenName'],
				$_POST['password']
			);

			if (!$result) {
				$this->_data['gg_form_error'] = "Invalid user credentials.";
			} else {
				$rurl = trim(safe_arrval("rurl", $_POST, ""));
				if ($rurl == "" || substr($rurl, 0, 1) != "/")
					redirect(home_page_link());
				redirect(xsite_url($rurl));
				return;
			}
		}

		$this->_data['value'] = $_REQUEST;

		$this->_data['hidden_fields'] = $this->nonce->generate_nonce("", TRUE);

		$this->_data['action_url'] = xsite_url($this->_bdir . "/login");
		
		$this->_data['gg_subtitle'] = "Use this screen to login.";
		
		$this->_render_view($this->_gginc . "login");
	}

	public function logout() {
		$this->auth->logout();
		redirect($this->_bdir . "/login");
	}
	
	public function signup() {
		redirect($this->_bdir . "/register");
		return;
	}
	
	public function register() {
		if (is_logged_in()) {
			redirect($this->_bdir . "/login");
			return;
		}
		
		$this->load->model("captcha_model");
		
		$this->load->library("form_validation");
		$this->load->helper("form");
		
		$this->form_validation->set_rules(
			"screenName",
			"Screen Name",
			"trim|required|min_length[5]|max_length[18]|callback_validate_screenName"
		);
		
		$this->form_validation->set_rules(
			"firstName",
			"First Name",
			"trim|required|max_length[50]"
		);
		$this->form_validation->set_rules(
			"lastName",
			"Last Name",
			"trim|required|max_length[50]"
		);
		$this->form_validation->set_rules(
			"dob",
			"Date of Birth",
			"trim|required|callback_dob_check"
		);
		
		$this->form_validation->set_rules(
			"email",
			"Email",
			"trim|required|valid_email"
		);
		
		$this->form_validation->set_rules(
			"captcha",
			"Captcha",
			"trim|required|callback_validate_captcha"
		);
		
		$this->form_validation->set_rules(
			"state",
			"State",
			"trim|required|callback_validate_state"
		);
		
		$this->form_validation->set_rules(
			"country",
			"Country",
			"trim|required|callback_validate_country"
		);

		$this->form_validation->set_rules(
			"accept_terms",
			"Accept Terms",
			"trim|required|callback_accept_terms"
		);
		
		$this->form_validation->set_rules(
			'pwd',
			"Password",
			"trim|required|max_length[33]"
		);
		
		if ($this->form_validation->run() === FALSE ||
			$this->nonce->confirm_nonce($_POST) !== TRUE
			) {

			//
			// Render register view...
			//
			$this->load->model("country_model");
			$this->load->model("state_model");
			
			$data = array(
				"captcha" => $this->captcha_model->generate_word(),
				"action_url" => site_url($this->_bdir . "/register"),
				"countries" => $this->country_model->get_all(true),
				"states" => $this->state_model->get_all(),
				"terms_n_conditions_link" => page_page_link("terms_and_conditions", "page"),
				'hidden_fields' => $this->nonce->generate_nonce()
			);

			$this->_render_view($this->_gginc . "register", $data);
		} else {
		
			$this->load->library("auth");

			$this->load->model("User_model");

			$user = $_POST;
			$user['dob'] = $this->_translate_dob($_POST['dob']);

			$result = intval($this->auth->create_user($user));

			if ($result <= 0) {
				redirect($this->_bdir . "/login");
			}
			
			redirect($this->_bdir . "/register_success/" . $result);
		}
	}

	public function register_success($userId = 0, $token = 0, $hash = "") {
		$this->_render_view($this->_gginc . "register_success");
	}
	
	//
	// TEMP.
	//
	public function register_email($user_id) {
		if (ENVIRONMENT != 'development') {
			show_404();
			return;
		}
		show_404();
		//
		// Send notification email for user id...
		//
		$this->load->library("auth");
		$resp = $this->auth->email_notify_user("verity_register", $user_id);
		echo "RESPONSE: $resp<br/>"; exit(0);
	}
	
	public function register_confirm($userId, $activate_key) {
		$this->load->library("auth");
		
		$resp = $this->auth->confirm_registration($userId, $activate_key);

		if (!$resp) {
			redirect($this->_bdir . "/login");
			return;
		}
		
		$this->_data['user'] = $this->user_model->get_user_by_id(
			$userId
		);
		
		$this->_render_view($this->_gginc . "account_activated");
	}
	
	public function validate_state($val) {
		$where = array(
			"abbrev" => $val,
			"country" => safe_arrval("country", $_POST, "")
		);
		$this->load->model("state_model");
		return ($this->state_model->count_states($where) > 0);
	}
	public function validate_country($val) {
		$where = array(
			"iso" => $val
		);
		$this->load->model("country_model");
		return ($this->country_model->count_countries($where) > 0);
	}
	
	public function validate_screenName($val) {
		$this->load->model("user_model");
		$c = $this->user_model->count_users($val);
		return ($c <= 0);
	}
	
	public function validate_captcha($captcha) {
		$captcha_id = substr(strval(safe_arrval("captcha_id", $_REQUEST, "")), 0, 31);
		$this->load->model("captcha_model");
		$c = $this->captcha_model->check_captcha(
			$captcha,
			$captcha_id
		);
		$this->captcha_model->delete_by_id($captcha_id, TRUE);
		return ($c > 0);
	}
	
	public function accept_terms($val) {
		return (intval($val) > 0);
	}
	
	protected function _translate_dob($val) {
		$c = sscanf($val, "%2d/%2d/%4d", $month, $day, $year);
		if ($c < 3) {
			return FALSE;
		}
		$month = minmax($month, 1, 12);
		$day = minmax($day, 1, 31);
		$year = minmax($year, 0, date('Y', NOW));
		if ($year < 100)
			$year += 1900;
		$val = $year . "-" . $month . "-" . $day . " 00:00:00";
		return $val;
	}
	
	public function dob_check($val) {
		$val = $this->_translate_dob($val);
		if ($val === FALSE) {
			return FALSE;
		}
		return TRUE;
	}
	
	protected function _render_view($central_widget = '', $central_widget_data = TRUE) {
		if (!isset($this->_data['gg_content_title'])) {
			$this->_data['gg_content_title'] = humanize($this->mylink->segment(1, "Auth"));
		}
	
		if ($central_widget != "") {
			$this->_data['central_widget'] = $central_widget;
			if ($central_widget_data === TRUE)
				$central_widget_data = $this->_data;
			$this->_data['central_widget_data'] = $central_widget_data;
		}
		$this->load->view($this->_incdir . "head", $this->_data);
		$this->load->view($this->_incdir . "body-authorize", $this->_data);
		$this->load->view($this->_incdir . "foot", $this->_data);
	}
}
