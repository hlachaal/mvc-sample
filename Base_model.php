<?php

class Base_model extends CI_Model {
	var $CI;
	var $_ci;
	
	var $_tablename;
	
	function __construct() {
		parent::__construct();
		$this->CI = & get_instance();
		$this->_ci = & get_instance();
	}
	
	function easy_query($sql = NULL) {
		$retval = array();
		$stmt = $this->easy_query_stmt($sql);
		if ($stmt !== FALSE) {
			while ($row = $stmt->result_array())
				$retval[] = $row;
		}
		return $retval;
	}
	
	function easy_query_stmt($sql = NULL) {
		if ($sql !== NULL)
			$stmt = $this->db->query($sql);
		else
			$stmt = $this->db->get();
		if (!$stmt)
			return FALSE;
		if ($stmt->num_rows() <= 0)
			return FALSE;
		return $stmt;
	}
	
	//
	// Simple query...
	//
	function query($query) {
		$stmt = $this->query_stmt($query);
		return $this->_produce_result($stmt);
	}
	
	//
	// Query stmt...
	//
	function query_stmt($query) {
		try {
			$this->_lastquery = $query;
			$query = $this->db->query($query);
		} catch (Exception $exc) {
			echo "DB Error."; exit(0);
		};
		return $query;
	}
	/*
	public function produce_results($tablename = TRUE, $key_field = '') {
		return $this->get_query_results($tablename, $key_field);
	}
	*/
	function get_query_results($tablename = TRUE, $key_field = '') {
		if ($tablename !== '' && $tablename !== FALSE) {
			if ($tablename === TRUE)
				$tablename = $this->_tablename;
			$this->db->from($tablename);
		}
		
		try {
			$query = $this->db->get();
			$this->_lastquery = $this->db->last_query();
		} catch (Exception $exc) {
			echo "DB Error."; exit(0);
		};
		
		if ($key_field == '') {
			$key_field = $this->determine_primary_key($tablename);
		}
		$retval = array();
		$i = 0;
		while ($row = $query->unbuffered_row('array')) {
			if (isset($row[$key_field]))
				$f = $row[$key_field];
			else
				$f = (++$i);
			$retval[$f] = $row;
		}
		
		return $retval;
	}
	
	function _produce_result(&$query, $key_field = '') {
		$this->_result = $query->result_array();
		if (!is_array($this->_result))
			$this->_result = array();
		return $this->_result;
	}
	
	function get_single_result($offset = 0, $tablename = TRUE) {
		$rows = array_values($this->get_query_results($tablename));
		if (!isset($rows[$offset]))
			return FALSE;
		return $rows[$offset];
	}

	function easy_gets($tablename= TRUE, $key_field = TRUE) {
		if ($key_field === TRUE)
			$key_field = $this->determine_primary_key($tablename);
		return $this->get_query_results($tablename, $key_field);
	}
	
	public function easy_get($tablename = TRUE, $id = 0, $key_field = TRUE) {
		if ($id !== NULL) {
			if (!is_array($id)) {
				if ($key_field === TRUE)
					$key_field = $this->determine_primary_key($tablename);
				$id = array($key_field => $id);
			}
			$this->_set_where($id);
		}
		return $this->get_single_result(0, $tablename);
	}

	public function easy_get_field($tablename = TRUE, $field = 0, $default = NULL) {
		$row = $this->get_single_result(0, $tablename);
		if (!safe_count($row) <= 0)
			return $default;
		if (is_numeric($field))
			$row = array_values($row);
		return safe_arrval($field, $row, $default);
	}
	
	//
	// This will return the single primary key. Or an empty string if
	// unable to determine.
	//
	function determine_primary_key($tablename = TRUE, $ret_all = FALSE) {
		if ($tablename === TRUE)
			$tablename = $this->_tablename;
		$fields = $this->db->field_data($tablename);
		$retval = array();
		foreach ($fields as $field) {
			if ($field->primary_key) {
				$retval[] = $field->name;
			}
		}
		if ($ret_all === TRUE)
			return $retval;
		if (count($retval) != 1)
			return "";
		return $retval[0];
	}
	
	function easy_get_var($tablename = TRUE, $where = array(),
		$field = 0, $offset = 0, $default_value = NULL) {
		//
		// Set where...
		//
		if (safe_count($where) > 0)
			$this->_set_where($where);
		
		//
		// Set tablename...
		//
		if ($tablename === TRUE)
			$tablename = $this->_tablename;
		if ($tablename !== "" && $tablename !== FALSE)
			$this->db->from($tablename);
			
		//
		// Produce query object...
		//
		$query = $this->db->get();
		if ($query->num_rows() <= 0)
			return $default_value;
		$rows = $this->_produce_result($query);
		
		if (safe_count($rows) < ($offset + 1))
			return $default_value;

		$row = $rows[$offset];
		
		if (is_numeric($field))
			$row = array_values($row);
		$retval = safe_arrval($field, $row, $default_value);
		return $retval;
	}
	
	function _set_where($where, $v = NULL, $prefix = "") {
		if ($where === NULL || $where === FALSE)
			return;
		if (is_array($where)) {
			foreach ($where as $k => $v)
				$this->_set_where($k, $v, $prefix);
		} else {
			if (is_array($v))
				$this->db->where_in($prefix . $where, $v);
			else
				$this->db->where($prefix . $where, $v);
		}
	}

	function last_row_field($field = 'id',
		$tablename = TRUE,
		$way = "DESC",
		$default_value = "") {
		if ($field != '')
			$this->db->order_by($field, $way);
		$this->db->select($field);
		$row = $this->get_single_result(0, $tablename);
		return safe_arrval($field, $row, $default_value);
	}
	
	function dt_fmt($timestamp = TRUE) {
		if ($timestamp === TRUE)
			$timestamp = NOW;
		return date("Y-m-d H:i:s", $timestamp);
	}
	
	function easy_insert($tablename, $data) {
		$data = $this->filter_fields($data, $tablename);
		$this->db->insert($tablename, $data);
		return $this->db->insert_id();
	}
	
	function easy_update($tablename, $where, $data) {
	
	//	$data = $this->filter_fields($data, $tablename);
	//	$where = $this->filter_fields($where, $tablename);
		$this->_set_where($where);
		$this->db->update($tablename, $data);
		return $this->db->affected_rows();
	}
	
	public function easy_upsert($tablename, $where, $data) {
		$this->db->where($where);
		$c = $this->db->count_all_results($tablename);
		if ($c <= 0) {
			$this->db->insert($tablename, $data);
			return $this->db->insert_id();
		}
		$this->db->where($where);
		$this->db->update($tablename, $data);
		return $this->db->affected_rows();
	}
	
	public function filter_fields($row, $tablename = TRUE) {
		$this->load->helper('strfunc');
		if ($tablename === TRUE)
			$tablename = $this->_tablename;
		if (is_array($tablename)) {
			$retval = array();
			foreach ($tablename as $t) {
				$fields_ = $this->filter_fields($row, $t);
				foreach ($fields_ as $k => $v)
					$retval[$t . "." . $k] = $v;
			}
			return $retval;
		}
		$fields = $this->db->list_fields($tablename);
		
		$retval = array();
		
		foreach ($row as $f => $v) {
			$fn = strfunc_before($f, " ", 0, $f);
			if (array_search($fn, $fields) !== FALSE)
				$retval[$f] = $v;
		}
		return $retval;
	}
	
	public function easy_count($tablename, $where) {
		$where = $this->filter_fields($where, $tablename);
		if (count($where) <= 0)
			return $this->db->count_all($tablename);
		$this->_set_where($where);
		return $this->db->count_all_results($tablename);
	}
	
	public function easy_delete($tablename, $where, $delete_all = FALSE) {
		$where = $this->filter_fields($where);
		if (safe_count($where) <= 0) {
			if ($delete_all === FALSE)
				return 0;
			$this->db->empty_table($tablename);
		} else {
			$this->_set_where($where);
			$this->db->delete($tablename);
		}
		return $this->db->affected_rows();
	}
}
