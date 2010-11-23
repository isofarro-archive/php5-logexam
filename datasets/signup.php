<?php
/**********************************************************************
*
* Filter signup URLs
*
**********************************************************************/

class SignupLogFilter extends LogFilterBase {
      var $startTime;
      var $endTime;

      public function __construct() {
      	     $this->startTime = strtotime('2010-11-18T14:30:00');
	     $this->endTime   = strtotime('2010-11-20T10:00:00');
      }	

	public function filter($entry) {
	       if (
	       	  preg_match('/^\/signup\//',                   $entry->url) ||
		  preg_match('/^\/ajax\/signup\//',             $entry->url) ||
		  preg_match('/^\/visitor\/sign_up/',           $entry->url) ||
		  preg_match('/^\/lovefilm\/visitor\/sml_pop/', $entry->url) ||
		  preg_match('/^\/account\/confirmation/',      $entry->url)
	       ) {
	       	 //echo "{$entry->url}\n";
		 if ($entry->date >= $this->startTime && $entry->date <= $this->endTime) {
		    return true;
		 }
	       }

	       return false;
	}
	
}

$filter = new SignupLogFilter();

?>