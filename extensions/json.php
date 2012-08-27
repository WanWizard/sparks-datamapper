<?php

/**
 * Json Extension for DataMapper classes.
 *
 * Quickly convert DataMapper models to-and-from JSON syntax.
 *
 * @license 	MIT License
 * @package		DMZ-Included-Extensions
 * @category	DMZ
 * @author  	Phil DeJarnett
 * @link    	http://www.overzealous.com/dmz/pages/extensions/json.html
 * @version 	1.1
 */

// --------------------------------------------------------------------------

/**
 * DMZ_Json Class
 *
 * @package		DMZ-Included-Extensions
 */
class DMZ_Json {

	/**
	 * Convert a DataMapper model into JSON code.
	 *
	 * @param	DataMapper $object The DataMapper Object to convert
	 * @param	array $fields Array of fields to include.  If empty, includes all database columns.
	 * @param	boolean $pretty_print Format the JSON code for legibility.
	 * @param	boolean $include Recurse into the defined related objects. Supports deep relationships
	 * @param	boolean	$no_encode Internal use only. if true, return the result without encoding
	 * @return	string A JSON formatted String, or FALSE if an error occurs.
	 */
	public function to_json($object, $fields = '', $pretty_print = FALSE, $include = array(), $no_encode = FALSE)
	{
		// make sure the fields array is defined
		empty($fields) and $fields = $object->fields;
		is_array($fields) or $fields = array($fields);

		// same for the include array
		empty($include) and $include = array();
		is_array($include) or $include = array($include);

		$result = array();

		// process the fields
		foreach($fields as $f)
		{
			// handle related fields
			if(array_key_exists($f, $object->has_one) || array_key_exists($f, $object->has_many))
			{
				// add it to the include array if not present yet
				in_array($f, $include) or $include[] = $f;
			}
			else
			{
				// add the field to the result
				$result[$f] = $object->{$f};
			}
		}

		// process the relations
		foreach($include as $f)
		{
			// is this a valid relation?
			if(array_key_exists($f, $object->has_one) || array_key_exists($f, $object->has_many))
			{
				// create an include array for deeper relations
				$deep_includes = array();
				foreach($include as $i)
				{
					// add it if this is a deep relation of the current relation
					($f !== $i and stripos($i, $f.'/') === 0) and $deep_includes[] = substr($i, strlen($f)+1);
				}
				// fetch the related objects, include all fields
				$result[$f] = $object->{$f}->all_to_json('', FALSE, $deep_includes, TRUE);
			}
		}

		if ($no_encode)
		{
			return $result;
		}

		$json = json_encode($result);

		if($json === FALSE)
		{
			return FALSE;
		}

		$pretty_print and $json = $this->_json_format($json);

		return $json;
	}

	/**
	 * Convert the entire $object->all array result set into JSON code.
	 *
	 * @param	DataMapper $object The DataMapper Object to convert
	 * @param	array $fields Array of fields to include.  If empty, includes all database columns.
	 * @param	boolean $pretty_print Format the JSON code for legibility.
	 * @param	boolean $include Recurse into the defined related objects. Supports deep relationships
	 * @return	string A JSON formatted String, or FALSE if an error occurs.
	 */
	public function all_to_json($object, $fields = '', $pretty_print = FALSE, $include = array())
	{
		$result = array();
		foreach($object as $o)
		{
			$result[] = $o->to_json($fields, FALSE, $include, TRUE);
		}

		$json = json_encode($result);
		if($json === FALSE)
		{
			return FALSE;
		}
		if($pretty_print)
		{
			$json = $this->_json_format($json);
		}
		return $json;
	}

	/**
	 * Convert a JSON object back into a DataMapper model.
	 *
	 * @param	DataMapper $object The DataMapper Object to save to.
	 * @param	string $json_code A string that contains JSON code.
	 * @param	array $fields Array of 'safe' fields.  If empty, only include the database columns.
	 * @return	bool TRUE or FALSE on success or failure of converting the JSON string.
	 */
	public function from_json($object, $json_code, $fields = '')
	{
		if(empty($fields))
		{
			$fields = $object->fields;
		}
		$data = json_decode($json_code);
		if($data === FALSE)
		{
			return FALSE;
		}
		foreach($data as $k => $v) {
			if(in_array($k, $fields))
			{
				$object->{$k} = $v;
			}
		}
		return TRUE;
	}

	/**
	 * Sets the HTTP Content-Type header to application/json
	 *
	 * @param	DataMapper $object
	 */
	public function set_json_content_type($object)
	{
		$CI =& get_instance();
		$CI->output->set_header('Content-Type: application/json');
	}

	/**
	 * Formats a JSON string for readability.
	 *
	 * From @link http://php.net/manual/en/function.json-encode.php
	 *
	 * @param string $json Unformatted JSON
	 * @return string Formatted JSON
	 */
	private function _json_format($json)
	{
		$tab = "  ";
		$new_json = "";
		$indent_level = 0;
		$in_string = false;

		$json_obj = json_decode($json);

		if($json_obj === false)
			return false;

		$json = json_encode($json_obj);
		$len = strlen($json);

		for($c = 0; $c < $len; $c++)
		{
			$char = $json[$c];
			switch($char)
			{
				case '{':
				case '[':
					if(!$in_string)
					{
						$new_json .= $char . "\n" . str_repeat($tab, $indent_level+1);
						$indent_level++;
					}
					else
					{
						$new_json .= $char;
					}
					break;
				case '}':
				case ']':
					if(!$in_string)
					{
						$indent_level--;
						$new_json .= "\n" . str_repeat($tab, $indent_level) . $char;
					}
					else
					{
						$new_json .= $char;
					}
					break;
				case ',':
					if(!$in_string)
					{
						$new_json .= ",\n" . str_repeat($tab, $indent_level);
					}
					else
					{
						$new_json .= $char;
					}
					break;
				case ':':
					if(!$in_string)
					{
						$new_json .= ": ";
					}
					else
					{
						$new_json .= $char;
					}
					break;
				case '"':
					if($c > 0 && $json[$c-1] != '\\')
					{
						$in_string = !$in_string;
					}
				default:
					$new_json .= $char;
					break;
			}
		}

		return $new_json;
	}

}

/* End of file json.php */
/* Location: ./application/datamapper/json.php */