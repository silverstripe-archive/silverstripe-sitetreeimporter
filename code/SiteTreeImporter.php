<?php
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Core\Convert;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FileField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Dev\Debug;

/**
 * Generate or update pages in the site-tree from a tab indented text file.
 *
 * Data fields on the pages can be populated, or updated for existing pages, via
 * specifying json encoded properties for each item in the tabbed import file.
 */
class SiteTreeImporter extends ContentController
{
    private static $allowed_actions = array(
        'Form',
        'bulkimport',
        'complete'
    );

    protected function init()
    {
        parent::init();
        if (!Permission::check('ADMIN')) {
            Security::permissionFailure();
        }
    }

    public function Title()
    {
        return "Site Tree Importer";
    }

    public function Content()
    {
        return <<<HTML
<p>This tool will let you generate pages from a text file that has a site-tree represented with tabs.</p>
<p>You can optionally supply JSON encoded data to populate fields in the site-tree pages.</p>
<p>For example, the file may contain the following content:</p>

<pre>
Home
About {"URLSegment": "about-us", "Label": "About Us", "MetaDescription": "About our company"}
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

<p><b>Note:</b> Please make sure that your file contains actual tab character
(rather than sequences of spaces), and that there is a page called 'Home'.</p>
HTML;
    }

    public function Form()
    {
        return new Form($this, "Form", new FieldList(
            new FileField("SourceFile", "Tab-delimited file"),
            new CheckboxField("DeleteExisting", "Clear out all existing content?"),
            new CheckboxField("PublishAll", "Publish everything after the import?")
        ), new FieldList(
            new FormAction("bulkimport", "Import pages")
        ));
    }

    public function bulkimport($data, $form)
    {
        $fh = fopen($data['SourceFile']['tmp_name'], 'r');

        Versioned::set_stage('Stage');

        if (isset($data['DeleteExisting']) && $data['DeleteExisting']) {
            foreach (Page::get() as $page) {
                $page->deleteFromStage('Stage');
                $page->deleteFromStage('Live');
            }
        }

        $parentRefs = array();

        while ($line = fgets($fh, 10000)) {
            // Skip comments
            if (preg_match('/^#/', $line)) {
                continue;
            }

            // Split up indentation, title and optional JSON
            if (preg_match("/^(\t*)([^{]*)({.*})?/", $line, $matches)) {
                $numTabs = strlen($matches[1]);
                $title = trim($matches[2]);
                $json = (isset($matches[3]) && trim($matches[3])) ? json_decode(trim($matches[3]), true) : array();

                // Either extract the URL from provided meta data, or generate it
                $url = (array_key_exists('URLSegment', $json)) ? $json['URLSegment'] : $title;
                $url = Convert::raw2url($url);

                // Allow custom classes based on meta data
                $className = (array_key_exists('ClassName', $json)) ? $json['ClassName'] : 'Page';

                // If we've got too many tabs, then outdent until we find a page to attach to.
                while (!isset($parentRefs[$numTabs-1]) && $numTabs > 0) {
                    $numTabs--;
                }

                $parentID = ($numTabs > 0) ? $parentRefs[$numTabs-1] : 0;

                // Try to find an existing page, or create a new one
                $page = Page::get()->filter(array(
                    'URLSegment' => $url,
                    'ParentID' => $parentID
                ))->First();
                if (!$page) {
                    $page = new $className();
                }

                // Apply any meta data properties to the page
                $page->ParentID = $parentID;
                $page->Title = $title;
                $page->URLSegment = $url;
                if ($json) {
                    $page->update($json);
                }

                $page->write();

                // Optionall publish
                if (isset($data['PublishAll']) && $data['PublishAll']) {
                    $page->publish('Stage', 'Live');
                }

				echo "<li>Written ID# $page->ID: $page->Title";
				if ($page->ParentID) {
					echo " (ParentID# $page->ParentID)";
				}
				echo "</li>";

                // Populate parentRefs with the most recent page at every level.   Necessary to build tree
                // Children of home should be placed at the top level
                if (strtolower($title) == 'home') {
                    $parentRefs[$numTabs] = 0;
                } else {
                    $parentRefs[$numTabs] = $page->ID;
                }

                // Remove no-longer-relevant children from the parentRefs.  Allows more graceful acceptance of files
                // with errors
                for ($i=sizeof($parentRefs)-1;$i>$numTabs;$i--) {
                    unset($parentRefs[$i]);
                }

                // Memory cleanup
                $page->destroy();
                unset($page);
            }
        }

		$complete = $this->complete();
		echo $complete['Content'];

    }

    public function complete()
    {
        return array(
            "Content" => "<p>Thanks! Your site tree has been imported.</p>",
            "Form" => " ",
        );
    }
}
