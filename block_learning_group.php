<?php
class block_learning_group extends block_list
{
	public function init() 
	{
		$this->title = get_string('mylearninggrouptitle', 'block_learning_group');
	}
    function has_config() {return true;}
	public function get_content() 
	{
		global $CFG, $DB;
		
		if ($this->content !== null) 
		{
			return $this->content;
		}
		

		
		$this->content         =  new stdClass;
        $this->content->icons = array();
		

		$this->content->items = array();
		$this->content->items[]='<a  href="' . $CFG->wwwroot . '/blocks/learning_group/view.php">'.get_string('see_block_learning_group', 'block_learning_group').'</a>';
		
		return $this->content;
	}
}
?>