<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Wire up your forms to your URI segments.
 *
 * @package             Wires
 * @author              Mark Croxton (mcroxton@hallmark-design.co.uk)
 * @copyright           Copyright (c) 2013 Hallmark Design
 * @link                http://hallmark-design.co.uk
 */

class Wires_upd {
    
    public $name    = 'Wires';
    public $version = '2.0.0';
    
    /**
     * Stash_upd
     * 
     * @access  public
     * @return  void
     */
    public function __construct()
    {
        $this->EE = get_instance();
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
        $this->EE->db->insert(
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
        $query = $this->EE->db->get_where('modules', array('module_name' => 'Wires'));
        
        if ($query->row('module_id'))
        {
            $this->EE->db->delete('module_member_groups', array('module_id' => $query->row('module_id')));
        }

        $this->EE->db->where('module_name', 'Wires')->delete('modules');

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
/* Location: ./system/expressionengine/third_party/wires/upd.stash.php */