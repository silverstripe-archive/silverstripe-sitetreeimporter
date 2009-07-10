<?php

class SiteTreeImporter extends Page_Controller {
	static $allowed_actions = array(
		'Form',
		'bulkimport',
		'complete'
	);

	function __construct() {
		$dataRecord = new Page();
		$dataRecord->Title = $this->Title();
		$dataRecord->URLSegment = get_class($this);
		$dataRecord->ID = -1;
		parent::__construct($dataRecord);
	}

	function init() {
		parent::init();
		if(!Permission::check('ADMIN')) Security::permissionFailure();
	}
	
	function Title() {
		return "Site Tree Importer";
	}
	
	function Content() {
		return <<<HTML
<p>This tool will let you generate pages from a text file that has a site-tree represented with tabs.  For example, the
file may contain the following content:</p>

<pre>
Home
About
	Staff
		Sam
		Sig
Products
	Laptops
		Macbook
		Macbook Pro
	Phones
		iPhone
Contact Us
</pre>

<p><b>Note:</b> Please make sure that your file contains actual tab character (rather than sequences of spaces), and that there
is a page called 'Home'.</p>
HTML;
	}
	
	function Form() {
		return new Form($this, "Form", new FieldSet(
			new FileField("SourceFile", "Tab-delimited file"),
			new CheckboxField("DeleteExisting", "Clear out all existing content?"),
			new CheckboxField("PublishAll", "Publish everything after the import?")
		), new FieldSet(
			new FormAction("bulkimport", "Import pages")
		));
	}
	
	function bulkimport($data, $form) {
		$fh = fopen($data['SourceFile']['tmp_name'],'r');


		if(isset($data['DeleteExisting']) && $data['DeleteExisting']) {
			DB::query("DELETE FROM SiteTree");
			DB::query("DELETE FROM SiteTree_Live");
		}
		
		Versioned::reading_stage('Stage');

		$parentRefs = array();

		while($line = fgets($fh, 10000)) {
			if(preg_match("/^(\t*)([^\t].*)/", $line, $matches)) {
				$numTabs = strlen($matches[1]);
				$title = trim($matches[2]);
				
				$newPage = new Page();
				$newPage->Title = $title;
				
				// If we've got too many tabs, then outdent until we find a page to attach to.
				while(!isset($parentRefs[$numTabs-1]) && $numTabs > 0) $numTabs--;

				// Set parent based on parentRefs;
				if($numTabs > 0) $newPage->ParentID = $parentRefs[$numTabs-1];

				$newPage->write();
				if(isset($data['PublishAll']) && $data['PublishAll']) $newPage->doPublish();
				echo "<li>Written #$newPage->ID: $newPage->Title (child of $newPage->ParentID)</li>";

				// Populate parentRefs with the most recent page at every level.   Necessary to build tree
				// Children of home should be placed at the top level
				if(strtolower($title) == 'home') $parentRefs[$numTabs] = 0;
				else $parentRefs[$numTabs] = $newPage->ID;

				// Remove no-longer-relevant children from the parentRefs.  Allows more graceful acceptance of files
				// with errors
				for($i=sizeof($parentRefs)-1;$i>$numTabs;$i--) unset($parentRefs[$i]);

				// Memory cleanup
				$newPage->destroy();
				unset($newPage);
			}
		}
		
		//Director::redirect($this->Link() . 'complete');		
	}
	
	function complete() {
		return array(
			"Content" => "<p>Thanks! Your site tree has been imported.</p>",
			"Form" => " ",
		);
	}
	
}
