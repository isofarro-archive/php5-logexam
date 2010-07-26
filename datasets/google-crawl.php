<?php
/**********************************************************************
*
* Keeping track of Google crawl activities
*
**********************************************************************/

class GoogleLogFilter extends LogFilterBase {
	
	public function filter($entry) {
		if (preg_match('/Googlebot/', $entry->userAgent)) {
			//echo '$';
			return true;
		}
	}
	
}

$filter = new GoogleLogFilter();

?>