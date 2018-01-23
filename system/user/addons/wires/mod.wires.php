<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Wire up your forms to URI segments. Search and filter entries with clean, readable uris.
 *
 * @package             Wires
 * @author              Mark Croxton (mcroxton@hallmark-design.co.uk)
 * @copyright           Copyright (c) 2018 Hallmark Design
 * @link                http://hallmark-design.co.uk
 */

class Wires {
	
	public $return_data = '';

	protected $map_delimter = ":";
	protected $map_glue = ";";
	protected $ee_uri;
	protected $id = 'default';
	protected $url = '';
	protected $action;
	protected static $cache = array();

	/** 
	 * Constructor
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() 
	{
		// a unique ID for the form
		if (FALSE === $this->id = ee()->TMPL->fetch_param('id', $this->id))
		{
			ee()->output->show_user_error('general', 'The id parameter is required');
		}
	}

	/*
    ================================================================
    Template tags
    ================================================================
    */

	/** 
	 * Connect
	 *
	 * @access public
	 * @return void
	 */
	public function connect() 
	{
		if (isset(self::$cache[$this->id]))
		{
			// parse template with cached view data
			return $this->_parse_view(self::$cache[$this->id]['view']);
		}
		else
		{	
			// do we want to use an earlier (cached) wires tag's parameters?
			if ($use = ee()->TMPL->fetch_param('use', FALSE))
			{
				if (isset(self::$cache[$use]))
				{
					// merge in the previous tags params, allowing them to be overridden by the current tag
					ee()->TMPL->tagparams = array_merge(self::$cache[$use]['params'], ee()->TMPL->tagparams);
				}
			}

			// setup url - fetch the *unadulterated* URI of the current page
			$this->ee_uri = new EE_URI;
			$this->ee_uri->_fetch_uri_string(); 
			$this->ee_uri->_remove_url_suffix();
			$this->ee_uri->_explode_segments();
			$this->ee_uri->_reindex_segments();

			// url
			if (FALSE == $url = ee()->TMPL->fetch_param('url', FALSE))
			{
				#ee()->output->show_user_error('general', 'The url parameter is required');
				return ee()->TMPL->tagdata; // fail gracefully
			}

			// parse {site_url} and {base_url}
			$url = str_replace(LD.'site_url'.RD, stripslashes(ee()->config->item('site_url')), $url);
			$url = str_replace(LD.'base_url'.RD, stripslashes(ee()->config->item('base_url')), $url);

			// break up into component parts
			$this->url = $this->_parse_url($url);

			// process form
			return $this->_form();
		}
	}

	/** 
	 * Uri
	 *
	 * @access public
	 * @return void
	 */
	public function url() 
	{
		// add or remove from the uri?
		$remove = (bool) preg_match('/1|on|yes|y/i', ee()->TMPL->fetch_param('remove', 'no'));

		// reset unspecified fields with their default values?
		$default = (bool) preg_match('/1|on|yes|y/i', ee()->TMPL->fetch_param('default', 'no'));

		// relative url? (default YES)
		$relative = (bool) preg_match('/1|on|yes|y/i', ee()->TMPL->fetch_param('relative', 'y'));

		// the url template
		$template = self::$cache[$this->id]['params']['url'];

		// array of raw values for the currently selected fields
		$data = self::$cache[$this->id]['raw'];

		// the fields we want to replace into the url
		$fields = array();

		foreach (ee()->TMPL->tagparams as $key => $value)
		{
			if (strncmp($key, '+', 1) == 0)
			{
				$field_key = substr($key, 1);

				if ($remove)
				{
					// removing from uri
					switch(self::$cache[$this->id]['fields'][$field_key]['type'])
					{
						case 'range' :
							// um,...

						break;

						case 'multiple' :

							if ($data[$field_key] != '' 
								&& $data[$field_key] !== self::$cache[$this->id]['fields'][$field_key]['default_in'] 
								&& $data[$field_key] !== $value)
							{
								$delimiter = self::$cache[$this->id]['fields'][$field_key]['delimiter_in'];

								// array of selected values
								$selected = explode($delimiter, $data[$field_key]);

								// remove our value and implode back into a string
								foreach($selected as $index => $selected_val)
								{
									if ($selected_val == $value)
									{
										unset($selected[$index]);
									}
								}

								$fields[$field_key] = implode($delimiter, $selected);
							}
							else
							{
								// replace with the default value
								$fields[$field_key] = self::$cache[$this->id]['fields'][$field_key]['default_in'];
							}
						break;

						default :
							// single value only possible - replace existing value with the default
							if (isset(self::$cache[$this->id]['fields'][$field_key]['default_in']))
							{
								$fields[$field_key] = self::$cache[$this->id]['fields'][$field_key]['default_in'];
							}
						break;
					}
				} 
				else
				{
					// inserting into uri
					switch(self::$cache[$this->id]['fields'][$field_key]['type'])
					{
						case 'range' :
							// um,...

						break;

						case 'multiple' :
							// append to existing value using delimiter_in specified for the field, if the value is not default
							if ($data[$field_key] != '' && $data[$field_key] !== self::$cache[$this->id]['fields'][$field_key]['default_in'])
							{
								// not default, so get an array of already-selected values
								$delimiter = self::$cache[$this->id]['fields'][$field_key]['delimiter_in'];

								$selected = explode($delimiter, $data[$field_key]);

								// add our value
								$selected[] = $value;

								// make sure the values are unique
								$selected = array_unique($selected);

								// implode back
								$fields[$field_key] = implode($delimiter, $selected);
							}
							else
							{
								// just replace default value
								$fields[$field_key] = $value;
							}

						break;

						default :
							// easy - just replace existing value
							$fields[$field_key] = $value;
						break;
					}
				}
			}	
		}

		// prepare field data
		foreach($data as $field => &$value) 
		{
			// fill in any empty values with defaults
			// if default="y", reset non-specified fields with default values too
			if ( (($default && ! isset($fields[$field])) || $value == "") 
				&& isset(self::$cache[$this->id]['fields'][$field]['default_in']) )
			{
				$value = self::$cache[$this->id]['fields'][$field]['default_in'];
			}

			// we'll always strip out these characters
			$bad	= array("\r", "\n", LD, RD, '&lt;?', '?&gt;');
			$value	= str_replace($bad, '', $value);

			// make sure values are safely url encoded
			$value = rawurlencode($value);
		}
		unset($value);

		// {base_url} and {site_url}
		$data += array(
			'site_url' => stripslashes(ee()->config->item('site_url')),
			'base_url' => stripslashes(ee()->config->item('base_url'))
		);

		// merge the arrays, overwriting the defaults with the custom field values
		$fields = array_merge($data, $fields);

		// now parse the url
		$url = ee()->TMPL->parse_variables_row($template, $fields);

		$this->url = $this->_parse_url($url);

		// make a full url needs a full scheme and host to be defined
		if (FALSE === isset($this->url['url_scheme']) || FALSE === isset($this->url['url_host']))
		{
			// add the scheme/host from config values
			$url = ee()->functions->create_url($url);
		}

		// make relative?
		if ($relative)
		{
			$this->url = $this->_parse_url($url);
			$url = $this->url['url_path'];

			if (isset($this->url['url_query'])) 
			{
				$url .= '?' . $this->url['url_query'];
			}

			if (isset($this->url['url_fragment'])) 
			{
				$url .= '#' . $this->url['url_fragment'];
			}
		}

		return $url;
	}
	
	/*
    ================================================================
    Internal methods
    ================================================================
    */

	/** 
	 * Form
	 *
	 * @access public
	 * @return void
	 */
	protected function _form() 
	{
		// we want to work with a url containing just the url path and query string
		$url = ltrim($this->url['url_path'], '/');

		if (isset($this->url['url_query'])) 
		{
			$url .= '?' . $this->url['url_query'];
		}

		// form action, default to current url
		$this->action = ee()->TMPL->fetch_param('action', '{current_url}');

		// get the field parameters
		$f = $this->_get_field_params(ee()->TMPL->tagparams);

		// setup a cache that later-parsed tags can use
		self::$cache[$this->id] = array(
			'fields' 	=> $f,
			'url' 		=> $url
		);

		// has the form been posted?
		$id = ee()->input->post('id');

		if ($id === $this->id)
		{	
			/* ================================================================
			   Form has been submitted
			   ================================================================ */

			$url = $this->_generate_url($f, $url);

			// redirect needs a full scheme and host to be defined
			if (FALSE === isset($this->url['url_scheme']) || FALSE === isset($this->url['url_host']))
			{
				// add the scheme/host from config values
				$url = ee()->functions->create_url($url);
			}
			else
			{
				$prefix = '';
				$suffix = '';

				// scheme?
				if ( ! empty($this->url['url_scheme']))
				{
					$prefix .= $this->url['url_scheme'] . ':';
				}

				$prefix .= "//";

				// host?
				if ( ! empty($this->url['url_host']))
				{
					$prefix .= $this->url['url_host'];

					// port?
					if ( ! empty($this->url['url_port']))
					{
						$prefix .= ':' . $this->url['url_port'];
					}

					$prefix .= "/";
				}

				if ( ! empty($this->url['url_fragment']))
				{
					$suffix .= '#' . $this->url['url_fragment'];
				}

				$url = $prefix . $url . $suffix;
			}

			// redirect to the url
			ee()->functions->redirect($url);

		}
		else
		{
			/* ================================================================
			   Map fields to url segments / query string arguments
			   ================================================================ */

			// get url parts
			$url_parts = $this->_get_url_parts($url);

			$extra = array();

			foreach($f as $key => &$field)
			{
				$field = $this->_defaults($field);
				$field['value'] = FALSE;
				$field['segment'] = FALSE;

				// map to uri segments
				for($i=0; $i<count($url_parts); $i++)
				{
					if (FALSE !== strpos($url_parts[$i], LD.$key.RD))
					{
						$field['value'] = $this->ee_uri->segment($i+1); // FALSE if non-existent
						$field['segment'] = TRUE;
						break;
					}
				}

				// support values passed via the query string, which are not subject to EEs 
				// segment uri character restrictions
				if (count($_GET) > 0 && FALSE === $field['segment'])
				{
					$field['value'] = ee()->input->get($key);
				}

				// make corresponding _min and _max fields for a range field
				if ($field['type'] === 'range')
				{
					$extra[$key.'_min'] = $field;
					$extra[$key.'_min']['value'] = '';
					$extra[$key.'_max'] = $field;
					$extra[$key.'_max']['value'] = '';
				}

				// cache raw values captured from segments / query string
				self::$cache[$this->id]['raw'][$key] = $field['value'];

				// rebuild real values to use in forms
				switch($field['type']) 
				{
					case 'range' :

						if (FALSE === $field['value'] || $field['default_in'] === $field['value'])
						{	
							// default in value found - map to default out
							$range = explode($field['delimiter_out'], $field['default_out']);

							// set individual fields
							if (isset($range[0]))
							{
								$extra[$key.'_min']['value'] = $range[0];
							}

							if (isset($range[1]))
							{
								$extra[$key.'_max']['value'] = $range[1];
							}

						}
						elseif(strncmp($field['value'], $field['from'], strlen($field['from'])) == 0)
						{
							// 'at least' min value passed
							$extra[$key.'_min']['value'] = substr($field['value'], strlen($field['from']));
						}
						elseif(strncmp($field['value'], $field['to'], strlen($field['to'])) == 0)
						{
							// 'at most' max value passed
							$extra[$key.'_max']['value'] = substr($field['value'], strlen($field['to']));
						}
						else
						{
							// min and max passed
							$field['value'] = explode($field['delimiter_in'], $field['value']);

							if (isset($field['value'][0]))
							{
								$extra[$key.'_min']['value'] = $field['value'][0];
							}
							if (isset($field['value'][1]))
							{
								$extra[$key.'_max']['value'] = $field['value'][1];
							}
						}

						// map min and max fields
						if ($field['map_out'])
						{
							$extra[$key.'_min']['value'] = $this->_map($extra[$key.'_min']['value'], $field['map'], TRUE);
							$extra[$key.'_max']['value'] = $this->_map($extra[$key.'_max']['value'], $field['map'], TRUE);
						}

						// validate
						$extra[$key.'_min']['value'] = $this->_get_field(
								$extra[$key.'_min']['value'], 
								$field['match'], 
								FALSE,
								$field['segment']
						);

						$extra[$key.'_max']['value'] = $this->_get_field(
								$extra[$key.'_max']['value'], 
								$field['match'], 
								FALSE,
								$field['segment']
						);

						// escape for displaying safely in html
						$extra[$key.'_min']['value'] = htmlspecialchars($extra[$key.'_min']['value']);
						$extra[$key.'_max']['value'] = htmlspecialchars($extra[$key.'_max']['value']);

						// set combined field value of the field with the out delimiter
						if ( ! empty($extra[$key.'_min']['value']) && ! empty($extra[$key.'_max']['value'])) 
						{
							$field['value'] = $extra[$key.'_min']['value'] . $field['delimiter_out'] . $extra[$key.'_max']['value'];
						} 
						else
						{
							$field['value'] = $extra[$key.'_min']['value'] . $extra[$key.'_max']['value'];
						}		

					break;

					case 'multiple' :

						// default in value found - map to default out
						if ( FALSE === $field['value'] || $field['default_in'] === $field['value'])
						{
							$field['value'] = explode($field['delimiter_out'], $field['default_out']);
						}
						else
						{
							// multiple values separated by known delimiter
							$field['value'] = explode($field['delimiter_in'], $field['value']);
						}

						// optionally map values for output, decode and validate
						foreach($field['value'] as &$v)
						{
							if ($field['map_out'])
							{
								$v = $this->_map($v, $field['map'], TRUE, $field['segment']);
							}
							$v = $this->_get_field($v, $field['match'], FALSE, $field['segment']);
						}
						unset($v);

						// implode into a pipe-delimited string for use in {if ... IN ..} conditionals - create a placeholder __field__
						$extra['__'. $key . '__']['value'] = implode('|', $field['value']);

						// prep template tagdata - replaced {if.. IN({field})} with {if.. IN({__field__})}
						ee()->TMPL->tagdata = str_replace(
							'IN ('. LD . $key . RD . ')', 
							'IN ('. LD . '__' . $key . '__' . RD . ')', 
							ee()->TMPL->tagdata
						);

						// escape for displaying safely in html
						foreach($field['value'] as &$v)
						{
							$v = htmlspecialchars($v);
						}
						unset($v);

						// implode into the desired ouput format
						$field['value'] = implode($field['delimiter_out'], $field['value']);

						break;

					case 'single' : default :

						if ( FALSE === $field['value'] || $field['default_in'] === $field['value'])
						{
							$field['value'] = $field['default_out'];
						}

						$field['value'] = $field['value'];

						if ($field['map_out'])
						{
							$field['value'] = $this->_map($field['value'], $field['map'], TRUE, $field['segment']);
						}

						$field['value'] = $this->_get_field($field['value'], $field['match'], FALSE, $field['segment']);

						// escape for displaying safely in html
						$field['value'] = htmlspecialchars($field['value']);

						break;
				}	
			}

			unset($field); // remove reference

			$f = $f + $extra;
		}

		// build an array to replace into the template
		$view = array();

		foreach($f as $key => $field)
		{
			$view[0][$key] = $field['value'];
		}

		// add the url path
		$url_path = $this->ee_uri->uri_string();

		// add base url and components to our view data
		$view[0] += array('url_path' => $url_path);

		// cache the view data and parameters for use by separate other tags
		self::$cache[$this->id] += array(
			'view' 	 => $view,
			'params' => ee()->TMPL->tagparams
		);

		// parse template?
		$output = '';
		$render_output = (bool) preg_match('/1|on|yes|y/i', ee()->TMPL->fetch_param('output', '1'));

		if ($render_output)
		{
			// do we want to wrap the output with a form?
			$form = (bool) preg_match('/1|on|yes|y/i', ee()->TMPL->fetch_param('form', '1'));

			$output = $this->_parse_view($view, $form);
		}

		return $output;
	}


	// ---------------------------------------------------------
	
	/**
	 * Replace variables in the template tagdata
	 *
	 * @access protected
	 * @return string	
	 */ 
	protected function _parse_view($view, $form=FALSE)
	{
		$prefix	= ee()->TMPL->fetch_param('prefix');
		$output = ee()->TMPL->tagdata;

		// parse template variables
		$output = ee()->TMPL->parse_variables($output, $view);

		// prep IN conditionals
		$output = $this->_prep_in_conditionals($output);

		// un-prefix common variables
		if (FALSE !== $prefix)
		{
			$output = $this->_un_prefix($prefix, $output);
		}

		// enclose in a form?
		if ($form)
		{	
			$form_details = array(
			    'action'          => $this->action,
			    'name'            => 'upload',
			    'hidden_fields'   => array('id' => $this->id),
			    'id'           	  => ee()->TMPL->form_id,
			    'class'           => ee()->TMPL->form_class,
			    'secure'          => TRUE
			);

			$form_open = ee()->functions->form_declaration($form_details);
			$form_close = "</form>";
			$output  = $form_open . $output . $form_close;
		}

		return $output;
	}

	// ---------------------------------------------------------
	
	/**
	 * Map a field value to an alternative, and back again
	 *
	 * @access protected
	 * @param string $value
	 * @param array|boolean $map
	 * @param boolean $out
	 * @return string	
	 */ 
	protected function _map($value, $map, $out = FALSE)
	{
		if ($map)
		{	
			if ( ! $out) 
			{
				$map = array_flip($map);
			}

			if (isset($map[$value]))
			{
				$value = $map[$value];
			}
		}
		return $value;
	}


	// ---------------------------------------------------------
	
	/**
	 * Set default field values
	 *
	 * @access private
	 * @param array $field
	 * @return array	
	 */ 
	private function _defaults($field = array())
	{
		// from
		if ( ! isset($field['from']))
		{
			$field['from'] = 'at-least-';
		}

		// to
		if ( ! isset($field['to']))
		{
			$field['to'] = 'at-most-';
		}

		// delimiters - cannot be empty
		if ( isset($field['delimiter_in']) && "" !== $field['delimiter_in'])
		{
			$field['delimiter_in'] = $field['delimiter_in'];
		}
		else
		{
			if ($field['type'] == 'range')
			{
				$field['delimiter_in'] = '-to-';
			}
			else
			{
				$field['delimiter_in'] = '-or-';
			}	
		}

		if ( isset($field['delimiter_out']))
		{
			$field['delimiter_out'] = $field['delimiter_out'];
		}
		else
		{	
			if ($field['type'] == 'range')
			{
				$field['delimiter_out'] = ';';
			}
			else
			{
				$field['delimiter_out'] = '|';
			}
		}

		// default_in - cannot be empty
		if ( ! isset($field['default_in']) )
		{
			$field['default_in'] = 'any';
		}

		// default_out
		if ( ! isset($field['default_out']))
		{
			$field['default_out'] = '';
		}

		// match an expected value
		if ( ! isset($field['match']))
		{
			$field['match'] = FALSE;
		}

		// map
		if ( ! isset($field['map']))
		{
			$field['map'] = FALSE;
		}
		else
		{
			// prep map
			$map = explode($this->map_glue, $field['map']);
			$map_formatted = array();
			foreach($map as $map_row)
			{
				$map_row = explode($this->map_delimter, $map_row);

				if ( isset($map_row[0]) && isset($map_row[1]) )
				{
					$map_formatted[$map_row[0]] = $map_row[1];
				}
			}

			$field['map'] = $map_formatted;
		}

		// map_in
		if ( ! isset($field['map_in']))
		{
			$field['map_in'] = TRUE;
		}
		else
		{
			$field['map_in'] = (bool) preg_match('/1|on|yes|y/i', $field['map_in']);
		}

		// map_out
		if ( ! isset($field['map_out']))
		{
			$field['map_out'] = TRUE;
		}
		else
		{
			$field['map_out'] = (bool) preg_match('/1|on|yes|y/i', $field['map_out']);
		}

		return $field;
	}


	// ---------------------------------------------------------
	
	/**
	 * Register, validate and sanitize a field
	 *
	 * @access private
	 * @param string|array $field
	 * @param boolean $match
	 * @param boolean $dynamic
	 * @param boolean $segment
	 * @param string $delimiter
	 * @return 	mixed
	 */ 
	private function _get_field($field, $match=FALSE, $dynamic=TRUE, $segment=TRUE)
	{
		if ($dynamic)
		{
			$field = ee()->input->get_post($field, TRUE);
		}
		
		$pass = TRUE;

		if (FALSE !== $field && FALSE !== $match)
		{	
			$pass = $this->_validate_field($field, $match);
		}

		if ($pass)
		{	
			return $this->_sanitize_field($field, $segment);
		}
		else return FALSE;
	}

	// ---------------------------------------------------------
	
	/**
	 * Validate a field's value
	 *
	 * @access private
	 * @param string|array $field
	 * @param string $match - regular expression to match
	 * @return 	boolean
	 */ 
	private function _validate_field($field, $match)
	{
		if (FALSE === $match)
		{
			return TRUE;
		}

		if ( ! is_array($field))
		{
			$field= array($field);
		}

		foreach($field as $value)
		{
			if ( ! preg_match($match, $value))
			{
				return FALSE;
			}
		}

		return TRUE;
	}

	/**
	 * Sanitize a field's value
	 *
	 * EE_URI and EE_Input classes impose restrictions on what can be passed as URI segments / GET values.
	 * Unfortunately any problems with the uri will output an error with show_error(), instead of an 
	 * exception that we could potentially catch gracefully. 
	 *
	 * Therefore, we will strip out strings that EE thinks are nefarious in advance. 
	 * There is one set of rules for segments and another for values passed in the $_GET array.
	 *
	 * @access private
	 * @param string|array $field
	 * @param boolean $segment
	 * @return string|array
	 */ 
	private function _sanitize_field($field, $segment=TRUE)
	{
		$is_str = FALSE;

		if ( ! is_array($field))
		{
			$is_str = TRUE;
			$field = array($field);
		}

		foreach($field as &$value)
		{
			// we'll always strip out these characters
			$bad	= array("\r", "\n", LD, RD, '&lt;?', '?&gt;');
			$value	= str_replace($bad, '', $value);

			if ($segment)
			{
				// SEGMENT
				$bad	= array('%28',	'%29', 	'%3A',	'%3a',	'/', 	'$', 	'%');
				$good	= array('',		'', 	':', 	':', 	'', 	'',		'');
				$value 	= str_replace($bad, $good, $value);

				$value 	= preg_replace("#(\(|\)|;|\?|{|}|<|>|http:\/\/|https:\/\/|\w+:/*[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})#i", '', $value);
			}
			else
			{
				// GET
				$value 	= preg_replace("#(;|\?|exec\s*\(|system\s*\(|passthru\s*\(|cmd\s*\(|[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})#i", '', $value);
			}	
		}
		unset($value);

		if ($is_str)
		{
			return $field[0];
		}
		else
		{
			return $field;
		}
	}

	// ---------------------------------------------------------
	
	/**
	 * Prep {if var IN (array)} conditionals
	 * 
	 * Used with the permission of Lodewijk Schutte
	 * http://gotolow.com/addons/low-search
	 *
	 * @access private
	 * @param string $tagdata
	 * @return String	
	 */ 
	private function _prep_in_conditionals($tagdata = '')
	{
		if (preg_match_all('#'.LD.'if (([\w\-_]+)|((\'|")(.+)\\4)) (NOT)?\s?IN \((.*?)\)'.RD.'#', $tagdata, $matches))
		{
			foreach ($matches[0] as $key => $match)
			{
				$left	 = $matches[1][$key];
				$operand = $matches[6][$key] ? '!=' : '==';
				$andor	 = $matches[6][$key] ? ' AND ' : ' OR ';
				$items	 = preg_replace('/(&(amp;)?)+/', '|', $matches[7][$key]);
				$cond	 = array();

				foreach (explode('|', $items) as $right)
				{
					$tmpl	= preg_match('#^(\'|").+\\1$#', $right) ? '%s %s %s' : '%s %s "%s"';
					$cond[] = sprintf($tmpl, $left, $operand, $right);
				}

				// replace {if var IN (1|2|3)} with {if var == '1' OR var == '2' OR var == '3'}
				$tagdata = str_replace($match, LD.'if '.implode($andor, $cond).RD, $tagdata);
			}
		}
		return $tagdata;
	}


	// ---------------------------------------------------------
	
	/**
	 * remove a given prefix from common variables in the template tagdata
	 * 
	 * @access private
	 * @param string $prefix
	 * @param string $template
	 * @return String	
	 */ 
	private function _un_prefix($prefix, $template)
	{
		// remove prefix
		$common = array('count', 'absolute_count', 'total_results', 'absolute_results', 'switch', 'no_results');

		foreach($common as $muck)
		{
			 $template = str_replace($prefix.':'.$muck, $muck,  $template);
		}

		return $template;
	}

	// ---------------------------------------------------------
	
	/**
	 * Parse the current url
	 * 
	 * @access private
	 * @param string $url
	 * @return array	
	 */ 
	private function _parse_url($url) 
	{
		// break up into component parts
		return array(
			'url_base'		=> $url,
			'url_scheme' 	=> parse_url($url, PHP_URL_SCHEME),
			'url_host' 		=> parse_url($url, PHP_URL_HOST),
			'url_user' 		=> parse_url($url, PHP_URL_USER),
			'url_pass' 		=> parse_url($url, PHP_URL_PASS),
			'url_port' 		=> parse_url($url, PHP_URL_PORT),
			'url_path' 		=> parse_url($url, PHP_URL_PATH),
			'url_query' 	=> parse_url($url, PHP_URL_QUERY),
			'url_fragment' 	=> parse_url($url, PHP_URL_FRAGMENT)
		);
	}

	/**
	 * Parse field parameters
	 * 
	 * @access private
	 * @param array $params
	 * @return array	
	 */ 
	private function _get_field_params($params) {

		// configure fields
		$f = array();

		foreach ($params as $key => $value)
		{
			if (strncmp($key, '+', 1) == 0)
			{
				$field_key = substr($key, 1);
				$field_key = explode(':', $field_key);

				if ( ! isset($f[$field_key[0]]))
				{
					$f[$field_key[0]] = array();
				}

				if(isset($field_key[1]))
				{
					$f[$field_key[0]][$field_key[1]] = $value;
				}
				else
				{
					$f[$field_key[0]]['type'] = $value;
				}
			}	
		}

		return $f;
	}

	/**
	 * Get url parts
	 * 
	 * @access private
	 * @param string $url
	 * @return array	
	 */ 
	private function _get_url_parts($url)
	{
		return explode('/', rtrim(preg_replace('/\?.*/', '', $url),'/'));
	}

	/**
	 * Populate a url template with selected field values, with fallback to default values
	 * 
	 * @access private
	 * @param array $f fields to retreive data for
	 * @param string $url the URL template
	 * @return string	
	 */ 
	private function _generate_url($f, $url)
	{
		// get url parts
		$url_parts = $this->_get_url_parts($url);

		// generate an array of populated field => values
		$field_values = $this->_populate_field_values($f, $url_parts);

		// replace into uri string
		$url = ee()->TMPL->parse_variables_row($url, $field_values);

		// cleanup the query string, removing any empty arguments
		$url = $this->_cleanup_url($url);

		return $url;
	}

	/**
	 * Populate field values, with fallback to default values
	 * 
	 * @access private
	 * @param array $f fields to retreive data for
	 * @param array $url array of url parts
	 * @return array	
	 */ 
	private function _populate_field_values($f, $url_parts) {

		if ( ! isset(self::$cache[$this->id]['field_values']) )
		{
			foreach($f as $key => &$field)
			{
				// determine if the field is a segment or passed in the query string
				$field['segment'] = FALSE;
				for($i=0; $i<count($url_parts); $i++)
				{
					if (FALSE !== strpos($url_parts[$i], LD.$key.RD))
					{
						$field['segment'] = TRUE;
						break;
					}
				}

				// make sure default parameters are set
				$field = $this->_defaults($field);

				// set default value
				$field['value'] = $field['default_in'];

				// try to retrieve the posted value of the field
				$value = $this->_get_field($key, $field['match'], TRUE, $field['segment']);

				switch($field['type']) 
				{
					case 'range' :

						$min = $this->_get_field($key.'_min', $field['match'], TRUE, $field['segment']);
						$max = $this->_get_field($key.'_max', $field['match'], TRUE, $field['segment']);

						// optionally maps fields to alternate values
						if ($field['map_in'])
						{
							$min = $this->_map($min, $field['map']);
							$max = $this->_map($max, $field['map']);
						}

						// make url safe
						$min = urlencode($min);
						$max = urlencode($max);

						if ( (FALSE === $min || '' === $min) && (FALSE === $max || '' === $max))
						{
							$field['value'] = $field['default_in'];
						}
						elseif(FALSE === $min || '' === $min)
						{
							$field['value'] = $field['to'] . $max;
						}
						elseif(FALSE === $max || '' === $max)
						{
							$field['value'] = $field['from'] . $min;
						}
						else
						{
							$field['value'] = $min . $field['delimiter_in'] . $max;
						}

						break;

					case 'multiple' :

						if ( FALSE !== $value && '' !== $value)
						{	
							// string may have been passed in
							if ( ! is_array($value))
							{
								// explode array by out delimiter
								$value = explode($field['delimiter_out'], $value);
							}

							// optionally map fields to alternate values, urlencode						
							foreach($value as &$v)
							{
								if ($field['map_in'])
								{
									$v = $this->_map($v, $field['map']);
								}
								$v = urlencode($v);
							}
							unset($v);

							$field['value'] = implode($field['delimiter_in'], $value);
						} 

						break;	

					case 'single' : default :

						if ( FALSE !== $value && '' !== $value)
						{
							$field['value'] = $value;

							if ($field['map_in'])
							{
								$field['value'] = $this->_map($field['value'], $field['map']);
							}
							$field['value'] = urlencode($field['value']);
						}

						break;

				}

				// replace into url string
				#$url = str_replace(LD.$key.RD,$field['value'], $url);
				self::$cache[$this->id]['field_values'][$key] = $field['value'];

			} // end foreach
			
			unset($field); // remove reference
		}

		return self::$cache[$this->id]['field_values'];
	}

	/**
	 * Cleanup the url
	 * 
	 * @access private
	 * @param string
	 * @return string	
	 */ 
	private function _cleanup_url($url)
	{
		// cleanup the query string, removing any empty arguments
		$query_string = end(explode('/', $url));

		if (strncmp($query_string, '?', 1) == 0)
		{
			$query_string = substr($query_string, 1);
			$url = str_replace($query_string, '', $url);

			// make sure ampersands aren't encoded
			$query_string = str_replace('&amp;', '&', $query_string);
			$qparts = explode('&', $query_string);

			$rebuilt_qparts = array();
			foreach($qparts as $part)
			{
				$args = explode('=', $part);
				if (isset($args[1]) && "" !== $args[1])
				{
					$rebuilt_qparts[] = $part;
				}
			}
			$url .= implode('&', $rebuilt_qparts);

			// trim final ? if no args at all
			$url = rtrim($url, '?');
		}

		return $url;
	}
}

/* End of file mod.wires.php */
/* Location: ./system/user/addons/wires/mod.wires.php */