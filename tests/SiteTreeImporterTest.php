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

		$data = <<<YML
# Comments are skipped
Parent1
Parent2
	Child2_1
		Grandchild2_1_1
	Child2_2
Parent3
YML;
		$this->import($data);

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
		$data = <<<YML
Parent1
Parent2
	Child2_1
		Grandchild2_1_1
	Child2_2
Parent3
YML;
		$this->import($data, array('PublishAll' => 1));

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

		$data = <<<YML
Parent1
YML;
		$this->import($data, array('DeleteExisting' => 1));

		$this->assertInstanceOf('Page', Page::get()->find('Title', 'Parent1'));
		$this->assertNull(Page::get()->find('Title', 'ShouldBeDeleted'));
	}

	function testImportSkipsComments() {
		$data = <<<YML
# Comments are skipped
Parent1
YML;
		$this->import($data);

		$this->assertNull(Page::get()->find('Title', '# Comments are skipped'));
	}

	function testImportWithJSON() {
		// create sample record
		$page = new Page();
		$page->Title = 'ShouldBeExisting';
		$page->URLSegment = 'existing';
		$page->write();
		$page->publish('Stage', 'Live');

		$data = <<<YML
Parent1
Parent2
	Child2_1
		Grandchild2_1_1
	Child2_2 {"URLSegment": "my-url", "MenuTitle": "my-menu-title", "ClassName": "SiteTreeImporterTest_TestPage"}
Parent3
YML;
		$this->import($data);

		$child2_2 = Page::get()->find('Title', 'Child2_2');
		$this->assertInstanceOf('Page', $child2_2);
		$this->assertEquals($child2_2->URLSegment, 'my-url');
		$this->assertEquals($child2_2->MenuTitle, 'my-menu-title');
		$this->assertEquals($child2_2->ClassName, 'SiteTreeImporterTest_TestPage');
	}

	function testDetectsDuplicatesByParent() {
		$page = new Page();
		$page->Title = 'MyPage';
		$page->URLSegment = 'mypage';
		$page->write();
		$page->publish('Stage', 'Live');

		// Import page with same name but a different hierarchy
		$data = <<<YML
Parent
	MyPage
YML;
		$this->import($data);

		$pages = Page::get()->filter('Title', 'MyPage')->sort('Created');
		
		$existingPage = $pages->filter('ID', $page->ID)->First();
		$this->assertNotNull($existingPage);
		$this->assertEquals($existingPage->Title, 'MyPage');
		$this->assertEquals((int)$existingPage->ParentID, (int)$page->ParentID);
		
		$newPage = $pages->exclude('ID', $page->ID)->First();
		$this->assertNotNull($newPage);
		$this->assertEquals($newPage->Title, 'MyPage');
		$this->assertNotEquals((int)$newPage->ParentID, (int)$page->ParentID);
	}

	protected function import($yml, $data = null) {
		$data = $data ? $data : array();
		$data['SourceFile'] = array();
		$tmpFile = tempnam('SiteTreeImporterTest', 'SiteTreeImporterTest');
		file_put_contents($tmpFile, $yml);
		$data['SourceFile']['tmp_name'] = $tmpFile;
		$importer = new SiteTreeImporter();
		$response = $importer->bulkimport($data, null);
		unlink($tmpFile);

	}
}

class SiteTreeImporterTest_TestPage extends Page implements HiddenClass {}