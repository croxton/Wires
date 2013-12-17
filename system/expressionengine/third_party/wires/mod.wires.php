<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Wire up your forms to your URI segments. Search and filter entries with clean, readable uris.
 *
 * @package             Wires
 * @author              Mark Croxton (mcroxton@hallmark-design.co.uk)
 * @copyright           Copyright (c) 2013 Hallmark Design
 * @link                http://hallmark-design.co.uk
 */

class Wires {
	
	public $EE;
	public $return_data = '';

	protected $map_delimter = ":";
	protected $map_glue = ";";
	protected $ee_uri;
	protected $id = 'default';
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
		$this->EE = get_instance();

		// a unique ID for the form
		if (FALSE === $this->id = $this->EE->TMPL->fetch_param('id', $this->id))
		{
			$this->EE->output->show_user_error('general', 'The id parameter is required');
		}
		
		if (isset(self::$cache[$this->id]))
		{
			// parse template with cached view data
			$this->return_data = $this->parse_view(self::$cache[$this->id]);
		}
		else
		{	
			// process form
			$this->return_data = $this->form();
		}
	}
	
	/** 
	 * Constructor
	 *
	 * @access public
	 * @return void
	 */
	public function form() 
	{
		// for Router/Freebie compatibility, we need to 
		// fetch the *unadulterated* URI of the current page
		$this->ee_uri = new EE_URI;
		$this->ee_uri->_fetch_uri_string(); 
		$this->ee_uri->_remove_url_suffix();
		$this->ee_uri->_explode_segments();
		$this->ee_uri->_reindex_segments();

		// url
		if (FALSE == $url = $this->EE->TMPL->fetch_param('url', FALSE))
		{
			$this->EE->output->show_user_error('general', 'The url parameter is required');
		}

		// strip hostname, if present
		$url = parse_url($url, PHP_URL_PATH);

		$url_parts = explode('/', rtrim(preg_replace('/\?.*/', '', $url),'/'));

		// form action, default to current url
		$this->action = $this->EE->TMPL->fetch_param('action', '{current_url}');

		// configure fields
		$f = array();

		foreach ($this->EE->TMPL->tagparams as $key => $value)
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

		// has the form been posted?
		$id = $this->EE->input->post('id');

		if ($id === $this->id)
		{	
			/* ================================================================
			   Form has been submitted
			   ================================================================ */   

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
							$min = $this->map($min, $field['map']);
							$max = $this->map($max, $field['map']);
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

						if ( FALSE !== $value && '' !== $value && is_array($value))
						{
							// optionally map fields to alternate values, urlencode						
							foreach($value as &$v)
							{
								if ($field['map_in'])
								{
									$v = $this->map($v, $field['map'], TRUE);
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
								$field['value'] = $this->map($field['value'], $field['map']);
							}
							$field['value'] = urlencode($field['value']);
						}

						break;

				}

				// replace into url string
				$url = str_replace(LD.$key.RD,$field['value'], $url);
			}
			unset($field); // remove reference

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

			// redirect to the url
			$url = $this->EE->functions->create_url($url);
			$this->EE->functions->redirect($url);

		}
		else
		{
			/* ================================================================
			   Map fields to url segments / query string arguments
			   ================================================================ */

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
					$field['value'] = $this->EE->input->get($key);
				}

				// make corresponding _min and _max fields for a range field
				if ($field['type'] === 'range')
				{
					$extra[$key.'_min'] = $field;
					$extra[$key.'_min']['value'] = '';
					$extra[$key.'_max'] = $field;
					$extra[$key.'_max']['value'] = '';
				}

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
							$extra[$key.'_min']['value'] = $this->map($extra[$key.'_min']['value'], $field['map'], TRUE);
							$extra[$key.'_max']['value'] = $this->map($extra[$key.'_max']['value'], $field['map'], TRUE);
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

						// implode into a pipe-delimited string for use in {if ... IN ..} conditionals - create a placeholder __field__
						$extra['__'. $key . '__']['value'] = implode('|', $field['value']);

						// prep template tagdata - replaced {if.. IN({field})} with {if.. IN({__field__})}
						$this->EE->TMPL->tagdata = str_replace(
							'IN ('. LD . $key . RD . ')', 
							'IN ('. LD . '__' . $key . '__' . RD . ')', 
							$this->EE->TMPL->tagdata
						);

						// optionally map values for output, decode and validate
						foreach($field['value'] as &$v)
						{
							if ($field['map_out'])
							{
								$v = $this->map($v, $field['map'], TRUE, $field['segment']);
							}
							$v = $this->_get_field($v, $field['match'], FALSE, $field['segment']);

							// escape for displaying safely in html
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
							$field['value'] = $this->map($field['value'], $field['map'], TRUE, $field['segment']);
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

		// cache the view data for later parsing
		self::$cache[$this->id] = $view;

		// parse template
		$output = $this->parse_view($view, TRUE);

		return $output;

	}


	// ---------------------------------------------------------
	
	/**
	 * Replace variables in the template tagdata
	 *
	 * @access protected
	 * @return string	
	 */ 
	protected function parse_view($view, $form=FALSE)
	{
		$prefix	= $this->EE->TMPL->fetch_param('prefix');
		$output = $this->EE->TMPL->tagdata;

		// parse template variables
		$output = $this->EE->TMPL->parse_variables($output, $view);

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
			    'class'           => $this->EE->TMPL->form_class,
			    'secure'          => TRUE
			);

			$form_open = $this->EE->functions->form_declaration($form_details);
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
	protected function map($value, $map, $out = FALSE)
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
		if ( ! isset($field['default_in']) && "" !== $field['default_in'])
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
	 * @return 	mixed
	 */ 
	private function _get_field($field, $match=FALSE, $dynamic=TRUE, $segment=TRUE)
	{
		if ($dynamic)
		{
			$field = $this->EE->input->get_post($field, TRUE);
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
	 * @access public
	 * @param string $prefix
	 * @param string $template
	 * @return String	
	 */ 
	function _un_prefix($prefix, $template)
	{
		// remove prefix
		$common = array('count', 'absolute_count', 'total_results', 'switch', 'no_results');

		foreach($common as $muck)
		{
			 $template = str_replace($prefix.':'.$muck, $muck,  $template);
		}

		return $template;
	}
}
<<<<<<< HEAD:system/expressionengine/third_party/wires/mod.wires.php

/* End of file mod.wires.php */
/* Location: ./system/expressionengine/third_party/wires/mod.wires.php */
=======
	<form action="" method="post">

		<fieldset>

			<label for="category">Category</label>
			<select name="category[]" id="category" multiple="multiple">
			{exp:stash:get_list name="categories" scope="site"} 
			   	<option value="{category_id}"{if category_id IN ({category})} selected="selected"{/if}>{category_name}</option>
			{/exp:stash:get_list}
			</select>

			<label for="price_min">Min price</label>
			<select name="price_min" id="price_min">
				<option value="100"{if "100" == "{price_min}"} selected="selected"{/if}>100</option>
				<option value="200"{if "200" == "{price_min}"} selected="selected"{/if}>200</option>
				<option value="300"{if "200" == "{price_min}"} selected="selected"{/if}>300</option>
			</select>

			<label for="price_max">Max price</label>
			<select name="price_max" id="price_max">
				<option value="100"{if "100" == "{price_max}"} selected="selected"{/if}>100</option>
				<option value="200"{if "200" == "{price_max}"} selected="selected"{/if}>200</option>
				<option value="300"{if "300" == "{price_max}"} selected="selected"{/if}>300</option>
			</select>

			<label for="color">Color</label>
			<select name="color" id="color">
				<option value="red"{if "red" == "{color}"} selected="selected"{/if}>Red</option>
				<option value="blue"{if "blue" == "{color}"} selected="selected"{/if}>Blue</option>
				<option value="green"{if "green" == "{color}"} selected="selected"{/if}>Green</option>
			</select>

			<label for="search">Search</label>
			<input type="text" name="search" id="search" value="{search}">

			<label for="orderby">Order by</label>
			<select name="orderby" id="orderby">
				<option value="price"{if "price" == "{orderby}"} selected="selected"{/if}>Price</option>
				<option value="title"{if "title" == "{orderby}"} selected="selected"{/if}>Title</option>
			</select>

			<label for="sort">Sort</label>
			<select name="sort" id="sort">
				<option value="asc"{if "asc" == "{sort}"} selected="selected"{/if}>Ascending</option>
				<option value="desc"{if "desc" == "{sort}"} selected="selected"{/if}>Descending</option>
			</select>

		</fieldset>

	</form>

	{exp:low_search:results 
        collection="products"
        category = "{category}"
        range:cf_price = "{price}"
        search:cf_color = "{color}"
        keywords = "{search}"
        orderby="{orderby}"
        sort="{sort}"
        limit="10"
        status="open"
        disable="member_data"
    }
        {if search:no_results}
            No results
        {/if}
        {if search:count==1}
        <table class="results">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                </tr>
            </thead>
            <tbody>
        {/if}
                <tr class="results-row{search:switch='|-alt'}">
                    <td><a href="{title_permalink='products'}">{title}</a></td>
                    <td>{cf_price}</td>
                </tr>
        {if search:count==search:total_results}    
            </tbody>
        </table>
        {/if}

    {/exp:low_search:results}
{/exp:wires}

	<?php
		$buffer = ob_get_contents();
		ob_end_clean();
		return $buffer;
	}	
}
>>>>>>> 22c29506edd1fd5d4bed63c1c68a111b5ca4e1db:system/expressionengine/third_party/wires/pi.wires.php
