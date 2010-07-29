<?php
class SiteTreeImporterTest extends FunctionalTest {
	
	protected $extraDataObjects = array(
		'SiteTree' // Hack to get SapphireTest refreshing the DB
	);
	
	function testImport() {
		// create sample record
		$page = new SiteTree();
		$page->Title = 'ShouldBeExisting';
		$page->write();
		
		$data = array();
		$data['SourceFile'] = array();
		$data['SourceFile']['tmp_name'] = BASE_PATH . '/sitetreeimporter/tests/SiteTreeImporterTest.txt';
		
		$importer = singleton('SiteTreeImporter');
		$importer->bulkimport($data, null);
	
		$existing = DataObject::get_one('SiteTree', "\"Title\" = 'ShouldBeExisting'");
		$parent1 = DataObject::get_one('SiteTree', "\"Title\" = 'Parent1'");
		$parent2 = DataObject::get_one('SiteTree', "\"Title\" = 'Parent2'");
		$parent3 = DataObject::get_one('SiteTree', "\"Title\" = 'Parent3'");
		$child2_1 = DataObject::get_one('SiteTree', "\"Title\" = 'Child2_1'");
		$child2_2 = DataObject::get_one('SiteTree', "\"Title\" = 'Child2_2'");
		$grandchild2_1_1 = DataObject::get_one('SiteTree', "\"Title\" = 'Grandchild2_1_1'");
		
		$this->assertType('SiteTree', $existing);
		$this->assertType('SiteTree', $parent1);
		$this->assertType('SiteTree', $parent2);
		$this->assertType('SiteTree', $parent3);
		$this->assertType('SiteTree', $child2_1);
		$this->assertType('SiteTree', $child2_2);
		$this->assertType('SiteTree', $grandchild2_1_1);
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
		
		$parent1 = Versioned::get_one_by_stage('SiteTree', 'Live', "\"Title\" = 'Parent1'");
		$parent2 = Versioned::get_one_by_stage('SiteTree', 'Live', "\"Title\" = 'Parent2'");
		$parent3 = Versioned::get_one_by_stage('SiteTree', 'Live', "\"Title\" = 'Parent3'");
		$child2_1 = Versioned::get_one_by_stage('SiteTree', 'Live', "\"Title\" = 'Child2_1'");
		$child2_2 = Versioned::get_one_by_stage('SiteTree', 'Live', "\"Title\" = 'Child2_2'");
		$grandchild2_1_1 = Versioned::get_one_by_stage('SiteTree', 'Live', "\"Title\" = 'Grandchild2_1_1'");
		
		$this->assertType('SiteTree', $parent1);
		$this->assertType('SiteTree', $parent2);
		$this->assertType('SiteTree', $parent3);
		$this->assertType('SiteTree', $child2_1);
		$this->assertType('SiteTree', $child2_2);
		$this->assertType('SiteTree', $grandchild2_1_1);
	}
	
	function testImportDeleteExisting() {
		// create sample record
		$page = new SiteTree();
		$page->Title = 'ShouldBeDeleted';
		$page->write();
		
		$data = array();
		$data['DeleteExisting'] = '1';
		$data['SourceFile'] = array();
		$data['SourceFile']['tmp_name'] = BASE_PATH . '/sitetreeimporter/tests/SiteTreeImporterTest.txt';
		
		$importer = singleton('SiteTreeImporter');
		$importer->bulkimport($data, null);
		
		$this->assertType('SiteTree', DataObject::get_one('SiteTree', "`Title` = 'Parent1'"));
		$this->assertFalse(DataObject::get_one('SiteTree', "`Title` = 'ShouldBeDeleted'"));
	}
}
?>