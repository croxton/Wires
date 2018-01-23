<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Wire up your forms to URI segments.
 *
 * @package             Wires
 * @author              Mark Croxton (mcroxton@hallmark-design.co.uk)
 * @copyright           Copyright (c) 2018 Hallmark Design
 * @link                http://hallmark-design.co.uk
 */

class Wires_upd {
    
    public $name    = 'Wires';
    public $version = '3.0.0';
    
    /**
     * constructor
     * 
     * @access  public
     * @return  void
     */
    public function __construct()
    {
    }
    
    /**
     * install
     * 
     * @access  public
     * @return  void
     */
    public function install()
    {   
        $sql = array();
        
        // install module 
        ee()->db->insert(
            'modules',
            array(
                'module_name' => $this->name,
                'module_version' => $this->version, 
                'has_cp_backend' => 'n',
                'has_publish_fields' => 'n'
            )
        );
        
        return TRUE;
    }
    
    /**
     * uninstall
     * 
     * @access  public
     * @return  void
     */
    public function uninstall()
    {
        $query = ee()->db->get_where('modules', array('module_name' => 'Wires'));
        
        if ($query->row('module_id'))
        {
            ee()->db->delete('module_member_groups', array('module_id' => $query->row('module_id')));
        }

        ee()->db->where('module_name', 'Wires')->delete('modules');

        return TRUE;
    }
    
    /**
     * update
     * 
     * @access  public
     * @param   mixed $current = ''
     * @return  void
     */
    public function update($current = '')
    {
        if ($current == '' OR version_compare($current, $this->version) === 0)
        {
            // up to date
            return FALSE;
        }

        // update version number
        return TRUE; 
    }
}

/* End of file upd.wires.php */
/* Location: ./system/user/addons/wires/upd.stash.php */