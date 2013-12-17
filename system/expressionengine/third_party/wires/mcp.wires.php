<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Wire up your forms to your URI segments.
 *
 * @package             Wires
 * @author              Mark Croxton (mcroxton@hallmark-design.co.uk)
 * @copyright           Copyright (c) 2013 Hallmark Design
 * @link                http://hallmark-design.co.uk
 */

class Wires_mcp {
    
    /**
     * Stash_mcp
     * 
     * @access  public
     * @return  void
     */
    public function __construct() 
    {
        $this->EE = get_instance();
    }
    
    /**
     * index
     * 
     * @access  public
     * @return  void
     */
    public function index()
    {
    }
}

/* End of file mcp.stash.php */
/* Location: ./system/expressionengine/third_party/wires/mcp.wires.php */