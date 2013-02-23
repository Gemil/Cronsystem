<?php 
class IOBase {
	protected $last;

	public function __construct() {
		
	}
	public function run() {
	
	}
	public function check() {
		return false;
	}
	public function getLast() {
		return $this->last;
	}
}
?>