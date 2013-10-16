# Site Tree Importer Module

[![Build Status](https://secure.travis-ci.org/silverstripe-labs/silverstripe-sitetreeimporter.png?branch=master)](http://travis-ci.org/silverstripe-labs/silverstripe-sitetreeimporter)

## Maintainer Contact

 * Sam Minnee (Nickname: sminnee) 
   <sam (at) silverstripe (dot) com>

## Requirements
 
 * SilverStripe 3.0 or later

## Installation

No installation required.

## Usage 

Make a tabbed-out file as directed in the form that appears above. Make sure that you use tabs, not spaces.
Visit `http://localhost/SiteTreeImporter?flush=1`.
Select your tabbed-out file in the file field, and tick the other two boxes as appropriate:

*Clear out all existing content?* - This will delete everything from your site before running the importer. 
Use with caution! If you don't tick this, then the pages will be added to the existing site.

*Publish everything after the import?* - This will publish each of the pages that the importer creates. 
If you don't tick this, then the pages will be left as draft-only.

## Format

The site tree import module lets you take a tabbed out file of the following format, 
and load it into your CMS as a site tree.

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
			
## Related

See the [static importer module](http://silverstripe.org/static-importer-module/) for a more sophisticated
importer based on crawling existing HTML pages, and extracting content via XPATH.
