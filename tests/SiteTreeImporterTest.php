<?php
class SiteTreeImporterTest extends FunctionalTest {

	protected $extraDataObjects = array(
		'Page' // Hack to get SapphireTest refreshing the DB
	);

	protected static $use_draft_site = true;

	function testImport() {
		// create sample record
		$page = new Page();
		$page->Title = 'ShouldBeExisting';
		$page->write();

		$data = array();
		$data['SourceFile'] = array();
		$data['SourceFile']['tmp_name'] = BASE_PATH . '/sitetreeimporter/tests/SiteTreeImporterTest.txt';

		$importer = singleton('SiteTreeImporter');
		$importer->bulkimport($data, null);

		$existing = Page::get()->find('Title', 'ShouldBeExisting');
		$parent1 = Page::get()->find('Title', 'Parent1');
		$parent2 = Page::get()->find('Title', 'Parent2');
		$parent3 = Page::get()->find('Title', 'Parent3');
		$child2_1 = Page::get()->find('Title', 'Child2_1');
		$child2_2 = Page::get()->find('Title', 'Child2_2');
		$grandchild2_1_1 = Page::get()->find('Title', 'Grandchild2_1_1');

		$this->assertInstanceOf('Page', $existing);
		$this->assertInstanceOf('Page', $parent1);
		$this->assertInstanceOf('Page', $parent2);
		$this->assertInstanceOf('Page', $parent3);
		$this->assertInstanceOf('Page', $child2_1);
		$this->assertInstanceOf('Page', $child2_2);
		$this->assertInstanceOf('Page', $grandchild2_1_1);
		$this->assertEquals($parent2->ID, $child2_1->ParentID);
		$this->assertEquals($parent2->ID, $child2_2->ParentID);
		$this->assertEquals($child2_1->ID, $grandchild2_1_1->ParentID);
	}

	function testImportPublishAll() {
		$data = array();
		$data['PublishAll'] = '1';
		$data['SourceFile'] = array();
		$data['SourceFile']['tmp_name'] = BASE_PATH . '/sitetreeimporter/tests/SiteTreeImporterTest.txt';

		$importer = singleton('SiteTreeImporter');
		$importer->bulkimport($data, null);

		$parent1 = Versioned::get_one_by_stage('Page', 'Live', "\"Title\" = 'Parent1'");
		$parent2 = Versioned::get_one_by_stage('Page', 'Live', "\"Title\" = 'Parent2'");
		$parent3 = Versioned::get_one_by_stage('Page', 'Live', "\"Title\" = 'Parent3'");
		$child2_1 = Versioned::get_one_by_stage('Page', 'Live', "\"Title\" = 'Child2_1'");
		$child2_2 = Versioned::get_one_by_stage('Page', 'Live', "\"Title\" = 'Child2_2'");
		$grandchild2_1_1 = Versioned::get_one_by_stage('Page', 'Live', "\"Title\" = 'Grandchild2_1_1'");

		$this->assertInstanceOf('Page', $parent1);
		$this->assertInstanceOf('Page', $parent2);
		$this->assertInstanceOf('Page', $parent3);
		$this->assertInstanceOf('Page', $child2_1);
		$this->assertInstanceOf('Page', $child2_2);
		$this->assertInstanceOf('Page', $grandchild2_1_1);
	}

	function testImportDeleteExisting() {
		// create sample record
		$page = new Page();
		$page->Title = 'ShouldBeDeleted';
		$page->write();

		$data = array();
		$data['DeleteExisting'] = '1';
		$data['SourceFile'] = array();
		$data['SourceFile']['tmp_name'] = BASE_PATH . '/sitetreeimporter/tests/SiteTreeImporterTest.txt';

		$importer = singleton('SiteTreeImporter');
		$importer->bulkimport($data, null);

		$this->assertInstanceOf('Page', Page::get()->find('Title', 'Parent1'));
		$this->assertNull(Page::get()->find('Title', 'ShouldBeDeleted'));
	}

	function testImportSkipsComments() {
		$data = array();
		$data['SourceFile'] = array();
		$data['SourceFile']['tmp_name'] = BASE_PATH . '/sitetreeimporter/tests/SiteTreeImporterTest.txt';

		$importer = singleton('SiteTreeImporter');
		$importer->bulkimport($data, null);

		$this->assertNull(Page::get()->find('Title', '# Comments are skipped'));
	}

	function testImportWithJSON() {
		// create sample record
		$page = new Page();
		$page->Title = 'ShouldBeExisting';
		$page->URLSegment = 'existing';
		$page->write();
		$page->publish('Stage', 'Live');

		$data = array();
		$data['SourceFile'] = array();
		$data['SourceFile']['tmp_name'] = BASE_PATH . '/sitetreeimporter/tests/SiteTreeImporterTest.txt';

		$importer = singleton('SiteTreeImporter');
		$importer->bulkimport($data, null);

		$child2_2 = Page::get()->find('Title', 'Child2_2');
		$this->assertInstanceOf('Page', $child2_2);
		$this->assertEquals($child2_2->URLSegment, 'my-url');
		$this->assertEquals($child2_2->MenuTitle, 'my-menu-title');
		$this->assertEquals($child2_2->ClassName, 'SiteTreeImporterTest_TestPage');
	}
}

class SiteTreeImporterTest_TestPage extends Page implements HiddenClass {}