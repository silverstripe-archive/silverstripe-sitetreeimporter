<?php
/**
 * Generate or update pages in the site-tree from a tab indented text file.
 *
 * Data fields on the pages can be populated, or updated for existing pages, via
 * specifying json encoded properties for each item in the tabbed import file.
 *
 * URL redirects can be created for each imported page using the LegacyURL json data key,
 * this feature requires the redirectedurls module.
 * Optionally, add LegacyURL as a text field in your Page type to display the imported url.
 *
 * {@link: https://github.com/silverstripe-labs/silverstripe-redirectedurls}
 *
 *
 * @author Sam Minnée
 * @author Michael Parkhill
 */
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
<p>This tool will let you generate pages from a text file that has a site-tree represented with tabs.</p>
<p>You can optionally supply JSON encoded data to populate fields in the site-tree pages.</p>
<p>If your json data contains the key "LegacyURL" then entries will be created in the Redirects module
for each, this feature assumes the Page db array contains a text field named "LegacyURL".</p>
<p>For example, the file may contain the following content:</p>

<pre>
Home
About {"URLSegment": "about-us", "MetaDescription": "About our company"}
	Staff
		Sam
		Sig
Products
	Laptops
		Macbook
		Macbook Pro
	Phones
		iPhone {"URLSegment": "apple-iphone", "LegacyURL": "/iphones"}
Contact Us
</pre>

<p><b>Note:</b> Please make sure that your file contains actual tab character
(rather than sequences of spaces), and that there is a page called 'Home'.</p>
HTML;
	}

	function Form() {
		return new Form($this, "Form", new FieldList(
			new FileField("SourceFile", "Tab-delimited file"),
			new CheckboxField("DeleteExisting", "Clear out all existing content?"),
			new CheckboxField("PublishAll", "Publish everything after the import?"),
			new CheckboxField("DeleteRedirects", "Clear all all existing Redirects?"),
			new CheckboxField("CreateRedirects", "Create Redirects for LegacyURL's?")
		), new FieldList(
			new FormAction("bulkimport", "Import pages")
		));
	}

	function bulkimport($data, $form) {
		$fh = fopen($data['SourceFile']['tmp_name'],'r');

		if(isset($data['DeleteExisting']) && $data['DeleteExisting']) {
			// TODO Remove subtables
			DB::query('DELETE FROM "SiteTree"');
			DB::query('DELETE FROM "SiteTree_Live"');
		}

		if(class_exists('RedirectedURL') && isset($data['DeleteRedirects']) && $data['DeleteRedirects']) {
			DB::query('DELETE FROM "RedirectedURL"');
		}

		Versioned::reading_stage('Stage');

		$parentRefs = array();

		while($line = fgets($fh, 10000)) {
			// Skip comments
			if(preg_match('/^#/', $line)) continue;

			if(preg_match("/^(\t*)([^\t].*)/", $line, $matches)) {
				$numTabs = strlen($matches[1]);
				$title = trim($matches[2]);
				$json = null;
				$urlsegment = null;
				$json = null;
				$decodedJson = null;

				// e.g. http://regexr.com/39u7j
				preg_match('/(.*)(?:\s)({.*})?$/', $title, $matches);

				if($matches) {
					$title = $matches[1];
					if($matches[2]) {
						$json = $matches[2];
						$decodedJson = json_decode($json, true);
						if(array_key_exists('URLSegment', $decodedJson)) {
							$urlsegment = $decodedJson['URLSegment'];
						}
					}
				}

				// try to find the existing page by matching the URLsegment generated from the imported page title
				$page = DataObject::get_one('SiteTree', sprintf('"URLSegment"=\'%s\'', Convert::raw2url($title)));

				// page not found, create a new page
				if(!$page) {
					$page = new Page();
					$page->Title = Convert::raw2xml($title);
					if($urlsegment) {
						$page->URLSegment = Convert::raw2url($urlsegment);
					}
				}

				// If we've got too many tabs, then outdent until we find a page to attach to.
				while(!isset($parentRefs[$numTabs-1]) && $numTabs > 0) $numTabs--;

				// Set parent based on parentRefs;
				if($numTabs > 0) $page->ParentID = $parentRefs[$numTabs-1];

				// Apply any json data properties to the page
				if($decodedJson) {
					$page->update($decodedJson);
				}

				$page->write();
				if(isset($data['PublishAll']) && $data['PublishAll']) $page->publish('Stage', 'Live');

				// create Redirect
				if($decodedJson && array_key_exists('LegacyURL', $decodedJson)
					&& isset($data['CreateRedirects']) && $data['CreateRedirects']) {
					$this->createRedirectedURL($decodedJson['LegacyURL'], null, '/'.$page->RelativeLink());
				}

				if(!SapphireTest::is_running_test()) {
					echo "<li>Written ID# $page->ID: $page->Title";
					if($page->ParentID) echo " (ParentID# $page->ParentID)";
					echo "</li>";
				}

				// Populate parentRefs with the most recent page at every level.   Necessary to build tree
				// Children of home should be placed at the top level
				if(strtolower($title) == 'home') $parentRefs[$numTabs] = 0;
				else $parentRefs[$numTabs] = $page->ID;

				// Remove no-longer-relevant children from the parentRefs.  Allows more graceful acceptance of files
				// with errors
				for($i=sizeof($parentRefs)-1;$i>$numTabs;$i--) unset($parentRefs[$i]);

				// Memory cleanup
				$page->destroy();
				unset($page);
			}
		}

		if(!SapphireTest::is_running_test()) {
			$complete = $this->complete();
			echo $complete['Content'];
		}
		else {
			Controller::redirect(Controller::join_links($this->Link(), 'complete'));
		}
	}

	function complete() {
		return array(
			"Content" => "<p>Thanks! Your site tree has been imported.</p>",
			"Form" => " ",
		);
	}

	/**
	 * Creates a RedirectedURL data object
	 *
	 * @param string $fromBase The relative url to redirect from
	 * @param string $to The Relative url to redirect to
	 */
	protected function createRedirectedURL($fromBase=null, $fromQuerystring=null, $to=null) {
		if(class_exists('RedirectedURL')) {
			$redirectedURL = new RedirectedURL();
			$redirectedURL->FromBase = $fromBase;
			$redirectedURL->FromQuerystring = $fromQuerystring;
			$redirectedURL->To = $to;
			$redirectedURL->write();

			if(!SapphireTest::is_running_test()) {
				$from = $fromBase . ($fromQuerystring) ? "?".$fromQuerystring : null;
				echo "<li> Redirect written: $from -> $to </li>";
			}
		}
	}
}
